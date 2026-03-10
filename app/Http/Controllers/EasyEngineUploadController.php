<?php

namespace App\Http\Controllers;

use App\Models\S3UploadJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use App\Jobs\EasyEngineProcessUpload;


class EasyEngineUploadController extends Controller
{
    public function form()
    {
        return view('easyengine.upload', [
            'uploadConfig' => $this->uploadConfig(),
        ]);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:1048576', // 1 GB
                'mimes:csv,txt',
            ],

            'state'     => ['required', 'string', 'max:10'],
            'drop_date' => ['required', 'date'],
        ]);

        $file  = $request->file('file');
        $date  = Carbon::parse($data['drop_date']);
        $state = strtoupper(trim($data['state']));

        $targetBuckets = $this->resolveTargetBuckets();
        if ($targetBuckets === []) {
            abort(500, 'Missing EE_S3_LEGACY_BUCKET in .env');
        }
        $primaryBucket = $targetBuckets[0];

        $safeOriginal = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file->getClientOriginalName());

        $dir = 'tmp/easyengine/' . now()->format('Ymd');
        Storage::disk('local')->makeDirectory($dir);
        $dirFullPath = Storage::disk('local')->path($dir);
        if (is_dir($dirFullPath)) {
            @chmod($dirFullPath, 0770);
        }
        Log::info('EE upload start', ['name'=>$safeOriginal, 'size'=>$file->getSize()]);

        $storedPath = $file->storeAs(
            $dir,
            now()->format('Ymd_His') . '_' . Str::random(8) . '_' . $safeOriginal,
            'local'
        );
        Log::info('EE upload stored', ['storedPath'=>$storedPath]);

        $fullPath = Storage::disk('local')->path($storedPath);
        if (!is_file($fullPath)) abort(500, "Upload saved path missing: {$fullPath}");
        @chmod($fullPath, 0660);

        $sha256 = hash_file('sha256', $fullPath);

        $baseNameNoExt  = pathinfo(basename($fullPath), PATHINFO_FILENAME);
        $parquetName    = $baseNameNoExt . '.parquet';

        $dayFolder = $date->format('Y_m_d');
        $key = sprintf(
            "inbound/intent/day=%s/state=%s/%s",
            $dayFolder,
            $state,
            $parquetName
        );

        $job = S3UploadJob::create([
            'uploader'      => $request->getUser(),
            'request_ip'    => $request->ip(),
            'original_name' => $file->getClientOriginalName(),
            'stored_path'   => $storedPath,
            'mime'          => $file->getMimeType(),
            'size'          => $file->getSize(),
            'sha256'        => $sha256,
            'drop_date'     => $date->toDateString(),
            'state'         => $state,
            's3_bucket'     => $primaryBucket,
            's3_key'        => $key,
            'status'        => S3UploadJob::STATUS_QUEUED, // add this const if missing
            'meta'          => [
                'phase' => 'queued',
                'target_buckets' => $targetBuckets,
            ],
        ]);

        EasyEngineProcessUpload::dispatch($job->id);

        $destinations = implode(', ', array_map(
            static fn (string $bucket): string => "s3://{$bucket}/{$key}",
            $targetBuckets
        ));

        return back()->with('ok', "Queued job #{$job->id}. It will upload parquet to {$destinations}");
    }

    private function resolveTargetBuckets(): array
    {
        $buckets = [];

        $bucket = trim((string) env('EE_S3_LEGACY_BUCKET', ''));
        if ($bucket !== '') {
            $buckets[] = $bucket;
        }

        return array_values(array_unique($buckets));
    }

    private function uploadConfig(): array
    {
        $appMaxBytes = 1024 * 1024 * 1024;
        $phpUploadMaxBytes = $this->parseIniSize((string) ini_get('upload_max_filesize'));
        $phpPostMaxBytes = $this->parseIniSize((string) ini_get('post_max_size'));
        $effectiveBytes = min(
            $this->normalizeIniLimit($phpUploadMaxBytes),
            $this->normalizeIniLimit($phpPostMaxBytes)
        );

        return [
            'app_max_bytes' => $appMaxBytes,
            'app_max_label' => $this->formatBytes($appMaxBytes),
            'php_upload_max_label' => $this->formatIniLimit($phpUploadMaxBytes),
            'php_post_max_label' => $this->formatIniLimit($phpPostMaxBytes),
            'effective_label' => $this->formatIniLimit($effectiveBytes),
            'supports_target_upload' => $effectiveBytes >= $appMaxBytes,
        ];
    }

    private function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round((float) $value),
        };
    }

    private function normalizeIniLimit(int $bytes): int
    {
        return $bytes > 0 ? $bytes : PHP_INT_MAX;
    }

    private function formatIniLimit(int $bytes): string
    {
        if ($bytes <= 0 || $bytes === PHP_INT_MAX) {
            return 'unlimited';
        }

        return $this->formatBytes($bytes);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf($size >= 10 || $unitIndex === 0 ? '%.0f %s' : '%.1f %s', $size, $units[$unitIndex]);
    }

    private function makeParquetPath(string $csvStoredPath, string $parquetFileName): string
    {
        // Put parquet next to csv under tmp/easyengine_parquet/...
        // e.g. tmp/easyengine/20260106/file.csv -> tmp/easyengine_parquet/20260106/file.parquet
        $csvDir = dirname($csvStoredPath); // tmp/easyengine/20260106
        $dateFolder = basename($csvDir);   // 20260106
        return "tmp/easyengine_parquet/{$dateFolder}/{$parquetFileName}";
    }

    private function runCsvToParquet(string $csvFullPath, string $parquetFullPath): void
    {

        $python = env('EE_PYTHON_BIN', base_path('.venv/bin/python'));
        $script = env('EE_PARQUET_SCRIPT', base_path('scripts/csv_to_parquet.py'));

        if (!is_file($python)) {
            throw new \RuntimeException("Python not found: {$python}");
        }
        if (!is_file($script)) {
            throw new \RuntimeException("Parquet script not found: {$script}");
        }

        $cmd = [$python, $script, $csvFullPath, $parquetFullPath];

        $p = new Process($cmd, base_path());
        $p->setTimeout(180);
        $p->run();

        if (!$p->isSuccessful()) {
            $err = $p->getErrorOutput() ?: $p->getOutput();
            throw new \RuntimeException('Parquet conversion failed: ' . trim($err));
        }
    }

    private function awsS3Cp(string $localPath, string $bucket, string $key, string $region): string
    {
        $cmd = ['aws', 's3', 'cp', $localPath, "s3://{$bucket}/{$key}"];

        $process = new Process($cmd, base_path(), [
            'AWS_DEFAULT_REGION'    => $region,
            'AWS_ACCESS_KEY_ID'     => env('AWS_ACCESS_KEY_ID'),
            'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY'),
        ]);

        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            $err = $process->getErrorOutput() ?: $process->getOutput();
            throw new \RuntimeException('S3 upload failed: ' . trim($err));
        }

        return $process->getOutput();
    }
}
