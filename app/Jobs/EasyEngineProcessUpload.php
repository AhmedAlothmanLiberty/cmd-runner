<?php

namespace App\Jobs;

use App\Models\S3UploadJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

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

        $bucket = $job->s3_bucket;
        $key    = $job->s3_key;
        $region = env('EE_S3_REGION', 'us-east-2');

        $csvFullPath = Storage::disk('local')->path($job->stored_path);

        if (!is_file($csvFullPath)) {
            $this->failJob($job, "CSV missing on disk: {$csvFullPath}");
            return;
        }

        $job->update([
            'status' => S3UploadJob::STATUS_PROCESSING,
            'started_at' => now(),
            'meta' => array_merge($job->meta ?? [], ['phase' => 'converting_to_parquet']),
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
        $p->setTimeout(180);
        $p->run();

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
            ]),
        ]);

        // Upload parquet (not csv)
        $aws = new Process(['aws', 's3', 'cp', $parquetFullPath, "s3://{$bucket}/{$key}"], base_path(), [
            'AWS_DEFAULT_REGION'    => $region,
            'AWS_ACCESS_KEY_ID'     => env('AWS_ACCESS_KEY_ID'),
            'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY'),
        ]);

        $aws->setTimeout(180);
        $aws->run();

        if (!$aws->isSuccessful()) {
            $err = trim($aws->getErrorOutput() ?: $aws->getOutput());
            $this->failJob($job, "S3 upload failed: {$err}");
            return;
        }

        $job->update([
            'status' => S3UploadJob::STATUS_UPLOADED,
            'finished_at' => now(),
            'meta' => array_merge($job->meta ?? [], [
                'phase' => 'done',
                'aws_output' => trim($aws->getOutput()),
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
}
