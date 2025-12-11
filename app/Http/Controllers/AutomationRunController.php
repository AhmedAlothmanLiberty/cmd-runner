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
    $executor = app(\App\Services\AutomationExecutor::class);

    $executor->run(
        automation: $automation,
        triggeredBy: 'manual:' . (auth()->user()->email ?? 'system')
    );

    return back()->with('status', 'Automation executed.');
}

}
