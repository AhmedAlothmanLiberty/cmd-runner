<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Models\AutomationLog;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunAutomations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automations:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run due automations based on their cron expression';

    public function handle(): int
    {
        $now = now();
        $this->info("Scanning automations at {$now->toDateTimeString()}");

        $automations = Automation::query()
            ->where('is_active', true)
            ->get();

        foreach ($automations as $automation) {
            if (! $this->isDue($automation->cron_expression, $now)) {
                continue;
            }

            $this->runAutomation($automation);
        }

        return self::SUCCESS;
    }

    protected function isDue(string $cron, $now): bool
    {
        try {
            $expression = new CronExpression($cron);

            return $expression->isDue($now);
        } catch (Throwable $e) {
            Log::warning('Invalid cron expression', ['cron' => $cron, 'error' => $e->getMessage()]);

            return false;
        }
    }

    protected function runAutomation(Automation $automation): void
    {
        $this->info("Running {$automation->name} ({$automation->command})");

        $log = null;
        $start = microtime(true);

        try {
            $log = DB::transaction(function () use ($automation): AutomationLog {
                return AutomationLog::create([
                    'automation_id' => $automation->id,
                    'started_at' => now(),
                    'status' => 'running',
                    'runtime_ms' => 0,
                    'triggered_by' => 'cron',
                ]);
            });

            Artisan::call($automation->command);

            $output = Artisan::output();
            $runtimeMs = (int) round((microtime(true) - $start) * 1000);

            $log->update([
                'finished_at' => now(),
                'status' => 'success',
                'runtime_ms' => $runtimeMs,
                'output' => $output,
            ]);

            $automation->update([
                'last_run_at' => now(),
                'last_run_status' => 'success',
                'last_runtime_ms' => $runtimeMs,
            ]);
        } catch (Throwable $e) {
            $runtimeMs = (int) round((microtime(true) - $start) * 1000);
            if ($log) {
                $log->update([
                    'finished_at' => now(),
                    'status' => 'failed',
                    'runtime_ms' => $runtimeMs,
                    'error' => $e->getMessage(),
                    'output' => $log->output,
                ]);
            }

            $automation->update([
                'last_run_at' => now(),
                'last_run_status' => 'failed',
                'last_runtime_ms' => $runtimeMs,
            ]);

            Log::error('Automation failed', [
                'automation_id' => $automation->id,
                'command' => $automation->command,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
