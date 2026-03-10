<?php

namespace App\Jobs;

use App\Models\S3UploadJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Throwable;

class EasyEngineProcessUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $jobId;

    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        $job = S3UploadJob::query()->findOrFail($this->jobId);

        $key = $job->s3_key;
        $region = env('EE_S3_REGION', 'us-east-2');
        $awsBinary = trim((string) env('EE_AWS_BIN', env('EE_AWS', 'aws')));
        $convertTimeout = $this->resolveTimeout('EE_PARQUET_TIMEOUT', 180);
        $uploadTimeout = $this->resolveTimeout('EE_S3_UPLOAD_TIMEOUT', 180);
        $targetBuckets = $this->resolveTargetBuckets($job);

        if ($targetBuckets === []) {
            $this->failJob($job, 'No S3 bucket configured. Set EE_S3_BUCKET (or EE_S3_BUCKETS).');
            return;
        }

        $primaryBucket = $targetBuckets[0];
        if ($job->s3_bucket !== $primaryBucket) {
            $job->update(['s3_bucket' => $primaryBucket]);
        }

        $csvFullPath = Storage::disk('local')->path($job->stored_path);

        if (!is_file($csvFullPath)) {
            $this->failJob($job, "CSV missing on disk: {$csvFullPath}");
            return;
        }

        $job->update([
            'status' => S3UploadJob::STATUS_PROCESSING,
            'started_at' => now(),
            'meta' => array_merge($job->meta ?? [], [
                'phase' => 'converting_to_parquet',
                'target_buckets' => $targetBuckets,
            ]),
        ]);

        // Build parquet path
        $base = pathinfo(basename($csvFullPath), PATHINFO_FILENAME);
        $parquetRelPath = "tmp/easyengine_parquet/" . now()->format('Ymd') . "/{$base}.parquet";
        Storage::disk('local')->makeDirectory(dirname($parquetRelPath));

        $parquetFullPath = Storage::disk('local')->path($parquetRelPath);

        // Convert
        $python = env('EE_PYTHON_BIN', base_path('.venv/bin/python'));
        $script = env('EE_PARQUET_SCRIPT', base_path('scripts/csv_to_parquet.py'));

        if (!is_file($python)) {
            $this->failJob($job, "Python not found: {$python}");
            return;
        }
        if (!is_file($script)) {
            $this->failJob($job, "Parquet script not found: {$script}");
            return;
        }

        $p = new Process([$python, $script, $csvFullPath, $parquetFullPath], base_path());
        $p->setTimeout($convertTimeout);

        try {
            $p->run();
        } catch (ProcessSignaledException $e) {
            $signal = $e->getSignal();
            $this->failJob(
                $job,
                "Parquet conversion process was killed by signal {$signal}. This is usually OOM or an external kill; try lowering EE_PARQUET_CHUNK_ROWS."
            );
            return;
        } catch (Throwable $e) {
            $this->failJob($job, 'Parquet conversion failed: ' . $e->getMessage());
            return;
        }

        if (!$p->isSuccessful()) {
            $err = trim($p->getErrorOutput() ?: $p->getOutput());
            $this->failJob($job, "Parquet conversion failed: {$err}");
            return;
        }

        if (!is_file($parquetFullPath)) {
            $this->failJob($job, "Parquet not created: {$parquetFullPath}");
            return;
        }

        $parquetSha = hash_file('sha256', $parquetFullPath);

        $job->update([
            'parquet_path' => $parquetRelPath,
            'parquet_sha256' => $parquetSha,
            'meta' => array_merge($job->meta ?? [], [
                'phase' => 'uploading_to_s3',
                'parquet_output' => trim($p->getOutput()),
                'target_buckets' => $targetBuckets,
            ]),
        ]);

        $uploadResults = [];

        foreach ($targetBuckets as $bucket) {
            $aws = new Process([$awsBinary, 's3', 'cp', $parquetFullPath, "s3://{$bucket}/{$key}"], base_path(), [
                'AWS_DEFAULT_REGION'    => $region,
                'AWS_ACCESS_KEY_ID'     => env('AWS_ACCESS_KEY_ID'),
                'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY'),
            ]);

            $aws->setTimeout($uploadTimeout);

            try {
                $aws->run();
            } catch (ProcessSignaledException $e) {
                $signal = $e->getSignal();
                $this->failJob($job, "S3 upload to {$bucket} was killed by signal {$signal}.");
                return;
            } catch (Throwable $e) {
                $this->failJob($job, "S3 upload to {$bucket} failed: {$e->getMessage()}");
                return;
            }

            if (!$aws->isSuccessful()) {
                $err = trim($aws->getErrorOutput() ?: $aws->getOutput());
                $this->failJob($job, "S3 upload failed for {$bucket}: {$err}");
                return;
            }

            $uploadResults[] = [
                'bucket' => $bucket,
                'output' => trim($aws->getOutput()),
            ];
        }

        $job->update([
            'status' => S3UploadJob::STATUS_UPLOADED,
            'finished_at' => now(),
            'meta' => array_merge($job->meta ?? [], [
                'phase' => 'done',
                'target_buckets' => $targetBuckets,
                'uploads' => $uploadResults,
            ]),
        ]);
    }

    private function failJob(S3UploadJob $job, string $message): void
    {
        $job->update([
            'status' => S3UploadJob::STATUS_FAILED,
            'error' => $message,
            'finished_at' => now(),
            'meta' => array_merge($job->meta ?? [], ['phase' => 'failed']),
        ]);
    }

    private function resolveTargetBuckets(S3UploadJob $job): array
    {
        $buckets = [];

        $metaBuckets = $job->meta['target_buckets'] ?? [];
        if (is_array($metaBuckets)) {
            foreach ($metaBuckets as $bucket) {
                $bucket = trim((string) $bucket);
                if ($bucket !== '') {
                    $buckets[] = $bucket;
                }
            }
        }

        $configuredBucketList = trim((string) env('EE_S3_BUCKETS', ''));
        if ($configuredBucketList !== '') {
            foreach (explode(',', $configuredBucketList) as $bucket) {
                $bucket = trim($bucket);
                if ($bucket !== '') {
                    $buckets[] = $bucket;
                }
            }
        }

        foreach ([env('EE_S3_BUCKET'), env('EE_S3_LEGACY_BUCKET'), $job->s3_bucket] as $bucket) {
            $bucket = trim((string) $bucket);
            if ($bucket !== '') {
                $buckets[] = $bucket;
            }
        }

        return array_values(array_unique($buckets));
    }

    private function resolveTimeout(string $envKey, int $default): int
    {
        $value = (int) env($envKey, $default);

        return $value > 0 ? $value : $default;
    }
}
