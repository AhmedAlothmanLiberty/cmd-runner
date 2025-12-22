<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class EasyEngineParquetTest extends Command
{
    protected $signature = 'ee:parquet-test';
    protected $description = 'Generate a test parquet(snappy) file using the local venv python script';

    public function handle(): int
    {
        $csv = storage_path('app/tmp/test.csv');
        $parquet = storage_path('app/tmp/test.parquet');

        @mkdir(dirname($csv), 0775, true);

        file_put_contents($csv, "a,b\n1,2\n");

        $python = base_path('.venv/bin/python');
        $script = base_path('scripts/csv_to_parquet.py');

        $process = new Process([$python, $script, $csv, $parquet]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput() ?: $process->getOutput());
            return self::FAILURE;
        }

        $this->info('OK: ' . $parquet);
        $this->line(trim($process->getOutput()));

        return self::SUCCESS;
    }
}
