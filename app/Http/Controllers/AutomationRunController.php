<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutomationRunController extends Controller
{
    public function run(Automation $automation): RedirectResponse
    {
        $start = microtime(true);
        $log = null;

        try {
            $log = DB::transaction(function () use ($automation): AutomationLog {
                return AutomationLog::create([
                    'automation_id' => $automation->id,
                    'started_at' => now(),
                    'status' => 'running',
                    'runtime_ms' => 0,
                    'triggered_by' => 'manual:'.(auth()->user()->email ?? 'system'),
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

            return back()->with('status', 'Automation ran successfully.');
        } catch (Throwable $e) {
            $runtimeMs = (int) round((microtime(true) - $start) * 1000);
            if ($log) {
                $log->update([
                    'finished_at' => now(),
                    'status' => 'failed',
                    'runtime_ms' => $runtimeMs,
                    'error' => $e->getMessage(),
                ]);
            }

            $automation->update([
                'last_run_at' => now(),
                'last_run_status' => 'failed',
                'last_runtime_ms' => $runtimeMs,
            ]);

            Log::error('Manual automation run failed', [
                'automation_id' => $automation->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('status', 'Automation failed: '.$e->getMessage());
        }
    }
}
