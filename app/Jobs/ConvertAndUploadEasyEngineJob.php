<?php
namespace App\Jobs;

use App\Models\EasyEngineJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Throwable;

class ConvertAndUploadEasyEngineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobId) {}

    public function handle(): void
    {
        $job = EasyEngineJob::findOrFail($this->jobId);

        $job->update(['status' => 'converting', 'error' => null]);

        $csvFull = Storage::disk('local')->path($job->csv_path);

        // output parquet path
        $parquetRel = preg_replace('/\.csv$/i', '.parquet', $job->csv_path);
        if ($parquetRel === $job->csv_path) {
            $parquetRel = $job->csv_path.'.parquet';
        }
        $parquetFull = Storage::disk('local')->path($parquetRel);

        // ✅ run python (venv)
        $python = env('EE_PYTHON', base_path('.venv/bin/python'));
        $script = base_path('scripts/csv_to_parquet.py');

        $result = Process::timeout(120)->run([$python, $script, $csvFull, $parquetFull]);

        if (!$result->successful()) {
            $job->update([
                'status' => 'failed',
                'error' => "Parquet convert failed: ".$result->output()."\n".$result->errorOutput(),
            ]);
            return;
        }

        if (!is_file($parquetFull)) {
            $job->update(['status' => 'failed', 'error' => "Parquet file not created: {$parquetFull}"]);
            return;
        }

        $parquetSha = hash_file('sha256', $parquetFull);

        $job->update([
            'status' => 'converted',
            'parquet_path' => $parquetRel,
            'parquet_sha256' => $parquetSha,
        ]);

        // ✅ build S3 key partition: /yyyy=YYYY/mm=MM/dd=DD/state=state/
        $d = ($job->drop_date ?? now())->toDateString();
        [$Y,$m,$dd] = explode('-', $d);
        $state = $job->state ?: 'NA';

        $prefix = trim(env('EE_S3_PREFIX', 'inbound/intent'), '/'); // inbound/intent
        $keyDir = "{$prefix}/yyyy={$Y}/mm={$m}/dd={$dd}/state={$state}";
        $filename = basename($parquetRel);
        $s3Key = "{$keyDir}/{$filename}";

        $bucket = env('EE_S3_BUCKET'); // 754724219978-lending-towers-data

        // ✅ upload (use aws-cli OR Storage disk)
        // Option A (fast): aws cli via Process
        $aws = env('EE_AWS', '/usr/local/bin/aws');
        $region = env('AWS_DEFAULT_REGION', 'us-east-2');

        $upload = Process::timeout(120)->run([
            $aws, 's3', 'cp',
            $parquetFull,
            "s3://{$bucket}/{$s3Key}",
            '--region', $region,
        ]);

        if (!$upload->successful()) {
            $job->update([
                'status' => 'failed',
                'error' => "S3 upload failed: ".$upload->output()."\n".$upload->errorOutput(),
            ]);
            return;
        }

        $job->update([
            'status' => 'uploaded_s3',
            's3_bucket' => $bucket,
            's3_key' => $s3Key,
        ]);
    }

    public function failed(Throwable $e): void
    {
        EasyEngineJob::whereKey($this->jobId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}
