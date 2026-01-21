<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\PackageUpdateLog;
use App\Models\Task;
use App\Models\TaskLabel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->hasRole('automation')) {
            return redirect()->route('admin.automations.index');
        }

        if ($user && $user->hasRole('developer')) {
            return redirect()->route('admin.tasks.index');
        }

        if (! ($user?->hasAnyRole(['admin', 'super-admin']) ?? false)) {
            if ($user?->can('view-tasks') || $user?->can('manage-tasks')) {
                return redirect()->route('admin.tasks.index');
            }

            if ($user?->can('view-backlog')) {
                return redirect()->route('admin.tasks.backlog');
            }

            if ($user?->can('view-all-tasks')) {
                return redirect()->route('admin.tasks.all');
            }

            return redirect('/');
        }

        $totalAutomations = Automation::count();
        $activeAutomations = Automation::where('is_active', true)->count();

        $lastAutomationLog = AutomationLog::with('automation')
            ->latest('started_at')
            ->first();

        $lastPackageUpdate = PackageUpdateLog::latest()->first();

        $latestAutomations = Automation::query()
            ->where('command', '!=', 'app:test-automation-command')
            ->latest()
            ->limit(6)
            ->get();

        $latestTasks = Task::query()
            ->with(['assignedTo', 'labels'])
            ->latest('updated_at')
            ->limit(3)
            ->get();

        $taskCounts = Task::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $taskWidgets = [];
        $statusLabels = Task::statusLabels();
        foreach ($statusLabels as $statusKey => $label) {
            $taskWidgets[] = [
                'label' => $label,
                'value' => (int) ($taskCounts[$statusKey] ?? 0),
                'status' => $statusKey,
            ];
        }

        $onlineUsers = User::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->orderByDesc('last_seen_at')
            ->limit(8)
            ->get(['id', 'name', 'email', 'last_seen_at', 'last_login_at']);

        $lastOnlineUsers = User::query()
            ->whereNotNull('last_seen_at')
            ->orderByDesc('last_seen_at')
            ->limit(8)
            ->get(['id', 'name', 'email', 'last_seen_at']);

        $latestUserUpdates = User::query()
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'name', 'email', 'updated_at']);

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
            'isSuperAdmin' => true,
            'highlights' => $highlights,
            'taskWidgets' => $taskWidgets,
            'activity' => $activity,
            'latestAutomations' => $latestAutomations,
            'lastPackageUpdate' => $lastPackageUpdate,
            'latestTasks' => $latestTasks,
            'onlineUsers' => $onlineUsers,
            'lastOnlineUsers' => $lastOnlineUsers,
            'latestUserUpdates' => $latestUserUpdates,
        ]);
    }
}
