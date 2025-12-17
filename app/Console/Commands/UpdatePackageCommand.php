<?php

namespace App\Console\Commands;

use App\Models\PackageUpdateLog;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class UpdatePackageCommand extends Command
{
    protected $signature = 'package:update
        {name=liberty-cmd/reports : Package name}
        {--dev=dev-main : Constraint/branch for non-production (ex: dev-main)}
        {--prod=^1.2 : Constraint for production (ex: ^1.2)}
        {--triggered-by= : Actor label/email for audit log}
        {--no-clear : Skip php artisan optimize:clear}
    ';

    protected $description = 'Update a composer package safely (dev branch on non-prod, stable on prod)';

    public function handle(): int
    {
        $package = $this->argument('name');
        $env = app()->environment();
        $constraint = $env === 'production'
            ? $this->option('prod')
            : $this->option('dev');

        $this->info("Updating package: {$package}");
        $this->line("Env: {$env}");
        $this->line("Constraint: {$constraint}");
        $this->newLine();

        // Put composer cache somewhere writable (avoids permission issues on servers)
        $composerHome = storage_path('composer');
        if (! is_dir($composerHome)) {
            @mkdir($composerHome, 0775, true);
        }

        $outputLines = [];
        $status = 0;

        // 1) Enforce the desired constraint in composer.json + update lock
        // composer require vendor/package:constraint -W
        $status = $this->runProcess(
            ['composer', 'require', "{$package}:{$constraint}", '-W', '--no-interaction'],
            base_path(),
            ['COMPOSER_HOME' => $composerHome],
            $outputLines,
            900
        );

        // 2) Clear caches (optional but recommended after package update)
        if ($status === 0 && ! $this->option('no-clear')) {
            $status = $this->runProcess(
                ['php', 'artisan', 'optimize:clear'],
                base_path(),
                [],
                $outputLines,
                180
            );
        }

        // 3) Audit log
        $triggeredBy = $this->option('triggered-by') ?: 'system/cli';

        try {
            PackageUpdateLog::query()->create([
                'package' => $package,
                'branch' => (string) $constraint,
                'env' => $env,
                'status' => $status === 0 ? 'success' : 'failure',
                'output' => implode("\n", $outputLines),
                'triggered_by' => (string) $triggeredBy,
            ]);
        } catch (\Throwable $e) {
            $this->warn('Could not write audit log: '.$e->getMessage());
        }

        $this->newLine();
        if ($status === 0) {
            $this->info('Done. Package updated via Composer.');
            return self::SUCCESS;
        }

        $this->error('Update failed. Check output above and package_update_logs.');
        return self::FAILURE;
    }

    private function runProcess(
        array $cmd,
        string $cwd,
        array $env,
        array &$outputLines,
        int $timeoutSeconds
    ): int {
        $this->line('> ' . implode(' ', $cmd));

        $process = new Process($cmd, $cwd, array_merge($_ENV, $env), null, $timeoutSeconds);

        $process->run(function ($type, $buffer) use (&$outputLines) {
            $buffer = rtrim($buffer, "\r\n");
            if ($buffer !== '') {
                $outputLines[] = $buffer;
                $this->line($buffer);
            }
        });

        return $process->isSuccessful() ? 0 : 1;
    }
}
