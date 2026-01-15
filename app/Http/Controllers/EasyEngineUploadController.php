<?php

namespace App\Http\Controllers;

use App\Models\S3UploadJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use App\Jobs\EasyEngineProcessUpload;


class EasyEngineUploadController extends Controller
{
    public function form()
    {
        return view('easyengine.upload');
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:1048576', // 1 GB
                'mimetypes:text/plain,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],

            'state'     => ['required', 'string', 'max:10'],
            'drop_date' => ['required', 'date'],
        ]);

        $file  = $request->file('file');
        $date  = \Carbon\Carbon::parse($data['drop_date']);
        $state = strtoupper(trim($data['state']));

        $bucket = env('EE_S3_BUCKET');
        if (!$bucket) abort(500, 'Missing EE_S3_BUCKET in .env');

        $safeOriginal = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file->getClientOriginalName());

        $dir = 'tmp/easyengine/' . now()->format('Ymd');
        Storage::disk('local')->makeDirectory($dir);
        Log::info('EE upload start', ['name'=>$safeOriginal, 'size'=>$file->getSize()]);

        $storedPath = $file->storeAs(
            $dir,
            now()->format('Ymd_His') . '_' . Str::random(8) . '_' . $safeOriginal,
            'local'
        );
Log::info('EE upload stored', ['storedPath'=>$storedPath]);

        $fullPath = Storage::disk('local')->path($storedPath);
        if (!is_file($fullPath)) abort(500, "Upload saved path missing: {$fullPath}");

        $sha256 = hash_file('sha256', $fullPath);

        $baseNameNoExt  = pathinfo(basename($fullPath), PATHINFO_FILENAME);
        $parquetName    = $baseNameNoExt . '.parquet';

        $key = sprintf(
            "inbound/intent/yyyy=%s/mm=%s/dd=%s/state=%s/%s",
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
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
            's3_bucket'     => $bucket,
            's3_key'        => $key,
            'status'        => S3UploadJob::STATUS_QUEUED, // add this const if missing
            'meta'          => ['phase' => 'queued'],
        ]);

        EasyEngineProcessUpload::dispatch($job->id);

        return back()->with('ok', "Queued job #{$job->id}. It will upload parquet to s3://{$bucket}/{$key}");
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
