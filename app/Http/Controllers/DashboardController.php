<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\PackageUpdateLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $isSuperAdmin = $user?->hasRole('super-admin') ?? false;

        if (! $isSuperAdmin) {
            $status = $request->input('status');
            $assignedTo = $request->input('assigned_to');

            $taskQuery = Task::query()->with(['assignedTo', 'labels']);

            if (! empty($assignedTo)) {
                $taskQuery->where('assigned_to', $assignedTo);
            } else {
                $taskQuery->whereNotNull('assigned_to');
            }

            if (in_array($status, ['todo', 'in_progress', 'done', 'blocked'], true)) {
                $taskQuery->where('status', $status);
            }

            $tasks = $taskQuery
                ->orderByDesc('updated_at')
                ->paginate(10)
                ->appends($request->query());

            $taskCounts = Task::query()
                ->where('assigned_to', $user?->id)
                ->selectRaw("status, COUNT(*) as total")
                ->groupBy('status')
                ->pluck('total', 'status');

            $taskWidgets = [
                ['label' => 'To do', 'value' => (int) ($taskCounts['todo'] ?? 0), 'status' => 'todo'],
                ['label' => 'In progress', 'value' => (int) ($taskCounts['in_progress'] ?? 0), 'status' => 'in_progress'],
                ['label' => 'Done', 'value' => (int) ($taskCounts['done'] ?? 0), 'status' => 'done'],
                ['label' => 'Blocked', 'value' => (int) ($taskCounts['blocked'] ?? 0), 'status' => 'blocked'],
            ];

            $users = User::query()->orderBy('name')->get(['id', 'name']);
            $filters = [
                'status' => $status,
                'assigned_to' => $assignedTo,
            ];

            return view('dashboard', [
                'isSuperAdmin' => false,
                'taskWidgets' => $taskWidgets,
                'tasks' => $tasks,
                'users' => $users,
                'filters' => $filters,
            ]);
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
            ->latest()
            ->limit(8)
            ->get();

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
            'activity' => $activity,
            'latestAutomations' => $latestAutomations,
            'lastPackageUpdate' => $lastPackageUpdate,
            'latestTasks' => $latestTasks,
        ]);
    }
}
