@csrf
<div class="mb-3">
    <label for="name" class="form-label">Name</label>
    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $automation->name ?? '') }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="slug" class="form-label">Slug (optional)</label>
    <input type="text" name="slug" id="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $automation->slug ?? '') }}">
    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="command" class="form-label">Command</label>
    <input type="text" name="command" id="command" class="form-control @error('command') is-invalid @enderror" placeholder="example: reports:generate" value="{{ old('command', $automation->command ?? '') }}" required>
    @error('command')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="cron_expression" class="form-label">Cron Expression</label>
    <input type="text" name="cron_expression" id="cron_expression" class="form-control @error('cron_expression') is-invalid @enderror" placeholder="*/5 * * * *" value="{{ old('cron_expression', $automation->cron_expression ?? '') }}" required>
    @error('cron_expression')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@php
    $timezoneDefault = 'America/Los_Angeles';
    $selectedTimezone = old('timezone', $automation->timezone ?? $timezoneDefault);
    $timezoneOptions = [
        'America/Los_Angeles',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'Asia/Amman',
        'UTC',
    ];
    $daysOfWeek = [
        ['key' => 'mon', 'label' => 'Monday'],
        ['key' => 'tue', 'label' => 'Tuesday'],
        ['key' => 'wed', 'label' => 'Wednesday'],
        ['key' => 'thu', 'label' => 'Thursday'],
        ['key' => 'fri', 'label' => 'Friday'],
        ['key' => 'sat', 'label' => 'Saturday'],
        ['key' => 'sun', 'label' => 'Sunday'],
    ];
    $dayTimes = old('day_times', []);
    $scheduleMode = old('schedule_mode', $automation->schedule_mode ?? 'daily'); // daily | custom
    $dailyTimes = old('daily_times', $automation->run_times ?? []);
    $dailyTimes = array_values($dailyTimes ?: []);
    if (count($dailyTimes) === 0) { $dailyTimes[] = null; }
@endphp

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <h6 class="text-uppercase text-muted small fw-semibold mb-3">Schedule</h6>

        <div class="d-flex flex-wrap gap-3 mb-3">
            <label class="form-check m-0">
                <input class="form-check-input schedule-mode-radio" type="radio" name="schedule_mode" value="daily" @checked($scheduleMode === 'daily')>
                <span class="form-check-label">Daily</span>
            </label>
            <label class="form-check m-0">
                <input class="form-check-input schedule-mode-radio" type="radio" name="schedule_mode" value="custom" @checked($scheduleMode === 'custom')>
                <span class="form-check-label">Custom days</span>
            </label>
        </div>

        <div id="daily-time-group" class="mb-3 @if($scheduleMode !== 'daily') d-none @endif">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label mb-0">Daily times</label>
                <button class="btn btn-sm btn-outline-secondary" type="button" id="add-daily-time">+ Add time</button>
            </div>
            <div id="daily-times-container" class="d-flex flex-column gap-2">
                @foreach ($dailyTimes as $idx => $time)
                    <div class="d-flex align-items-center gap-2 daily-time-row">
                        <input type="time" name="daily_times[]" class="form-control" value="{{ $time }}">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-daily-time">&times;</button>
                    </div>
                @endforeach
            </div>
        </div>

        <div id="custom-days-block" class="@if($scheduleMode !== 'custom') d-none @endif">
            @foreach ($daysOfWeek as $day)
                @php
                    $times = $dayTimes[$day['key']] ?? [];
                    $times = array_values($times ?: []);
                    if (count($times) === 0) { $times[] = null; }
                @endphp
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-semibold">{{ $day['label'] }}</span>
                        <button class="btn btn-sm btn-outline-secondary add-day-time" type="button" data-day="{{ $day['key'] }}">+ Add time</button>
                    </div>
                    <div class="d-flex flex-column gap-2 day-times-container" data-day-container="{{ $day['key'] }}">
                        @foreach ($times as $idx => $time)
                            <div class="d-flex align-items-center gap-2 day-time-row">
                                <input type="time" name="day_times[{{ $day['key'] }}][]" class="form-control form-control-sm" style="width: 140px;" value="{{ $time }}">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-day-time">&times;</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">
            <label class="form-label">Timezone</label>
            <select name="timezone" class="form-select @error('timezone') is-invalid @enderror">
                @foreach ($timezoneOptions as $timezone)
                    <option value="{{ $timezone }}" @selected($selectedTimezone === $timezone)>{{ $timezone }}</option>
                @endforeach
            </select>
            @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="row g-3 mt-3">
    <div class="col-md-4">
        <label for="timeout_seconds" class="form-label">Timeout (seconds)</label>
        <input type="number" name="timeout_seconds" id="timeout_seconds" class="form-control @error('timeout_seconds') is-invalid @enderror" value="{{ old('timeout_seconds', $automation->timeout_seconds ?? '') }}" min="1">
        @error('timeout_seconds')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="run_via" class="form-label">Run via</label>
        <select name="run_via" id="run_via" class="form-select @error('run_via') is-invalid @enderror">
            @foreach (['artisan' => 'Artisan', 'later' => 'Queue/Later'] as $value => $label)
                <option value="{{ $value }}" @selected(old('run_via', $automation->run_via ?? 'artisan') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('run_via')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 d-flex align-items-center gap-3">
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" @checked(old('is_active', $automation->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" value="1" id="notify_on_fail" name="notify_on_fail" @checked(old('notify_on_fail', $automation->notify_on_fail ?? false))>
            <label class="form-check-label" for="notify_on_fail">Notify on fail</label>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const addButtons = document.querySelectorAll('.add-day-time');
    const modeRadios = document.querySelectorAll('.schedule-mode-radio');
    const customBlock = document.getElementById('custom-days-block');
    const dailyGroup = document.getElementById('daily-time-group');
    const dailyTimesContainer = document.getElementById('daily-times-container');
    const addDailyBtn = document.getElementById('add-daily-time');

    const addDailyRow = (value = '') => {
        if (!dailyTimesContainer) return;
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-2 daily-time-row';
        row.innerHTML = `
            <input type="time" name="daily_times[]" class="form-control form-control-sm" style="width: 140px;" value="${value}">
            <button type="button" class="btn btn-sm btn-outline-danger remove-daily-time">&times;</button>
        `;
        dailyTimesContainer.appendChild(row);
        const btn = row.querySelector('.remove-daily-time');
        if (btn) {
            btn.addEventListener('click', () => {
                if (dailyTimesContainer.children.length > 1) {
                    row.remove();
                }
            });
        }
    };

    addDailyBtn?.addEventListener('click', () => addDailyRow());
    document.querySelectorAll('.daily-time-row .remove-daily-time').forEach((btn) => {
        btn.addEventListener('click', () => {
            const row = btn.closest('.daily-time-row');
            if (row && dailyTimesContainer && dailyTimesContainer.children.length > 1) {
                row.remove();
            }
        });
    });

    const attachRemove = (row) => {
        const removeBtn = row.querySelector('.remove-day-time');
        if (!removeBtn) return;
        removeBtn.addEventListener('click', () => {
            const container = row.closest('.day-times-container');
            if (container && container.children.length > 1) {
                row.remove();
            }
        });
    };

    addButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const day = btn.getAttribute('data-day');
            const container = document.querySelector(`[data-day-container="${day}"]`);
            if (!container) return;
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center gap-2 day-time-row';
            row.innerHTML = `
                <input type="time" name="day_times[${day}][]" class="form-control form-control-sm" style="width: 140px;">
                <button type="button" class="btn btn-sm btn-outline-danger remove-day-time">&times;</button>
            `;
            container.appendChild(row);
            attachRemove(row);
        });
    });

    document.querySelectorAll('.day-time-row').forEach(attachRemove);

    const toggleMode = () => {
        const selected = Array.from(modeRadios).find(r => r.checked)?.value;
        if (customBlock) customBlock.classList.toggle('d-none', selected !== 'custom');
        if (dailyGroup) dailyGroup.classList.toggle('d-none', selected !== 'daily');
    };
    modeRadios.forEach(r => r.addEventListener('change', toggleMode));
    toggleMode();
});
</script>
@endpush
