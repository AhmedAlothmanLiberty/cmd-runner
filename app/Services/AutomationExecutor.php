<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutomationExecutor
{
    public function run(Automation $automation, string $triggeredBy = 'system'): AutomationLog
    {
        $start = microtime(true);

        // Create log entry
        $log = AutomationLog::create([
            'automation_id' => $automation->id,
            'started_at'    => now(),
            'status'        => 'running',
            'runtime_ms'    => 0,
            'triggered_by'  => $triggeredBy,
        ]);

        try {

            $kernel = app(\Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();
            $kernel->commandLoader->load($kernel->all());

            Artisan::call($automation->command);


            // Execution results
            $output     = Artisan::output();
            $runtimeMs  = (int) round((microtime(true) - $start) * 1000);

            // Update log
            $log->update([
                'finished_at' => now(),
                'status'      => 'success',
                'runtime_ms'  => $runtimeMs,
                'output'      => $output,
            ]);

            // Update automation info
            $automation->update([
                'last_run_at'     => now(),
                'last_run_status' => 'success',
                'last_runtime_ms' => $runtimeMs,
            ]);
        } catch (Throwable $e) {
            $runtimeMs  = (int) round((microtime(true) - $start) * 1000);

            $log->update([
                'finished_at' => now(),
                'status'      => 'failed',
                'runtime_ms'  => $runtimeMs,
                'error'       => $e->getMessage(),
            ]);

            $automation->update([
                'last_run_at'     => now(),
                'last_run_status' => 'failed',
                'last_runtime_ms' => $runtimeMs,
            ]);

            Log::error('Automation failed', [
                'automation_id' => $automation->id,
                'error'         => $e->getMessage(),
            ]);
        }

        return $log;
    }
}
