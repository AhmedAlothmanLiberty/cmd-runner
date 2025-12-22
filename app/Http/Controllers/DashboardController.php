<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\PackageUpdateLog;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $totalAutomations = Automation::count();
        $activeAutomations = Automation::where('is_active', true)->count();

        $lastAutomationLog = AutomationLog::with('automation')
            ->latest('started_at')
            ->first();

        $lastPackageUpdate = PackageUpdateLog::latest()->first();

        $highlights = [
            [
                'label' => 'Automations',
                'value' => (string) $totalAutomations,
                'icon' => 'gear',
            ],
            [
                'label' => 'Active Automations',
                'value' => (string) $activeAutomations,
                'icon' => 'play-fill',
            ],
            [
                'label' => 'Last Package Update',
                'value' => $lastPackageUpdate
                    ? ($lastPackageUpdate->status . ' · ' . optional($lastPackageUpdate->created_at)->diffForHumans())
                    : 'No runs yet',
                'icon' => 'arrow-repeat',
            ],
            [
                'label' => 'Last Automation Run',
                'value' => $lastAutomationLog
                    ? (($lastAutomationLog->automation?->name ?? 'Automation') . ' · ' . optional($lastAutomationLog->started_at)->diffForHumans())
                    : 'No runs yet',
                'icon' => 'cpu',
            ],
        ];

        $activity = [];

        if ($lastPackageUpdate) {
            $activity[] = [
                'title' => 'Package update',
                'detail' => $lastPackageUpdate->package . ' (' . $lastPackageUpdate->status . ')',
                'time' => optional($lastPackageUpdate->created_at)->diffForHumans() ?? 'Just now',
                'icon' => 'arrow-repeat',
            ];
        }

        if ($lastAutomationLog) {
            $activity[] = [
                'title' => 'Automation run',
                'detail' => ($lastAutomationLog->automation?->name ?? 'Automation') . ' (' . $lastAutomationLog->status . ')',
                'time' => optional($lastAutomationLog->started_at)->diffForHumans() ?? 'Just now',
                'icon' => 'cpu',
            ];
        }

        return view('dashboard', [
            'highlights' => $highlights,
            'activity' => $activity,
        ]);
    }
}
