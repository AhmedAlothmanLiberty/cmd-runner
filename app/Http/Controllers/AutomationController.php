<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutomationRequest;
use App\Http\Requests\UpdateAutomationRequest;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\User;
use App\Notifications\AutomationEventNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Automation::query();

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('command', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        }

        $automations = $query
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->appends($request->query());

        $userEmails = $automations->getCollection()
            ->pluck('created_by')
            ->merge($automations->getCollection()->pluck('updated_by'))
            ->filter()
            ->unique()
            ->values();

        $userNamesByEmail = $userEmails->isEmpty()
            ? []
            : User::query()
                ->whereIn('email', $userEmails)
                ->pluck('name', 'email')
                ->toArray();

        $filters = [
            'search' => $search,
            'status' => $status,
        ];

        return view('admin.automations.index', compact('automations', 'filters', 'userNamesByEmail'));
    }

    public function create(): View
    {
        return view('admin.automations.create');
    }

    public function store(StoreAutomationRequest $request): RedirectResponse
    {
        $userEmail = auth()->user()->email ?? 'system';

        $data = $this->normalizeSchedulePayload($request->validated());

        $automation = Automation::create(array_merge($data, [
            'created_by' => $userEmail,
            'updated_by' => $userEmail,
        ]));

        $this->notifyAdminAutomationEvent($automation, 'automation_created');

        return redirect()->route('admin.automations.index')->with('status', 'Automation created.');
    }

    public function edit(Automation $automation): View
    {
        return view('admin.automations.edit', compact('automation'));
    }

    public function update(UpdateAutomationRequest $request, Automation $automation): RedirectResponse
    {
        $userEmail = auth()->user()->email ?? 'system';

        $data = $this->normalizeSchedulePayload($request->validated());

        $automation->update(array_merge($data, [
            'updated_by' => $userEmail,
        ]));

        $this->notifyAdminAutomationEvent($automation, 'automation_updated');

        return redirect()->route('admin.automations.index')->with('status', 'Automation updated.');
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $this->notifyAdminAutomationEvent($automation, 'automation_deleted');
        $automation->delete();

        return redirect()->route('admin.automations.index')->with('status', 'Automation deleted.');
    }

    public function toggle(Automation $automation): RedirectResponse
    {
        $automation->update(['is_active' => ! $automation->is_active]);

        $this->notifyAdminAutomationEvent($automation, 'automation_toggled');

        return back()->with('status', 'Automation status updated.');
    }

    public function logs(Automation $automation): View
    {
        $logs = $automation->logs()->latest('started_at')->paginate(20);

        return view('admin.automations.logs', compact('automation', 'logs'));
    }

    public function showLog(AutomationLog $log): View
    {
        $log->load('automation');

        return view('admin.automations.log_show', compact('log'));
    }

    private function normalizeSchedulePayload(array $data): array
    {
        $data['schedule_mode'] = $data['schedule_mode'] ?? 'daily';
        $dayTimes = $data['day_times'] ?? [];
        $dailyTimes = $data['daily_times'] ?? [];

        // Normalize day_times and derive weekly_days
        $normalizedDayTimes = [];
        $weeklyDays = [];

        if ($data['schedule_mode'] === 'custom') {
            foreach ($dayTimes as $day => $times) {
                if (! is_array($times)) {
                    continue;
                }

                $cleanTimes = array_values(array_filter($times, static fn ($v) => $v !== null && $v !== ''));
                if (! empty($cleanTimes)) {
                    $normalizedDayTimes[$day] = $cleanTimes;
                    $weeklyDays[] = $day;
                }
            }

            $data['day_times'] = $normalizedDayTimes;
            $data['weekly_days'] = array_values(array_unique($weeklyDays));
            $data['daily_time'] = null;
            $data['run_times'] = [];
        } else {
            // daily mode
            $data['day_times'] = [];
            $data['weekly_days'] = [];
            $cleanDaily = array_values(array_filter($dailyTimes, static fn ($v) => $v !== null && $v !== ''));
            $data['run_times'] = $cleanDaily;
            $data['daily_time'] = $cleanDaily[0] ?? ($data['daily_time'] ?? '00:00');
        }

        // Legacy fields kept for compatibility but not used in scheduling
        $data['schedule_frequencies'] = [];
        $data['monthly_days'] = [];

        unset(
            $data['monthly_days_text'],
            $data['yearly_dates_text']
        );

        unset($data['daily_times']);

        return $data;
    }

    private function parseCommaSeparatedInts(string $value, int $min, int $max): array
    {
        if (trim($value) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));
        $ints = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $num = (int) $part;
            if ($num >= $min && $num <= $max) {
                $ints[] = $num;
            }
        }

        return array_values(array_unique($ints));
    }

    private function parseCommaSeparatedStrings(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));

        return array_values(array_unique(array_filter($parts, static fn ($v) => $v !== '')));
    }

    private function notifyAdminAutomationEvent(Automation $automation, string $type): void
    {
        $actorId = auth()->id();
        $actorName = auth()->user()->name ?? auth()->user()->email ?? null;

        $titleMap = [
            'automation_created' => 'Automation created',
            'automation_updated' => 'Automation updated',
            'automation_deleted' => 'Automation deleted',
            'automation_toggled' => 'Automation status changed',
        ];

        $title = $titleMap[$type] ?? 'Automation update';
        $message = $automation->name;

        $recipients = User::role(['admin', 'super-admin'])
            ->get()
            ->reject(fn (User $user) => $actorId && (int) $user->id === (int) $actorId);

        $recipients->each(function (User $user) use ($automation, $type, $title, $message, $actorName): void {
            $user->notify(new AutomationEventNotification(
                $automation,
                $type,
                $title,
                $message,
                $actorName
            ));
        });
    }
}
