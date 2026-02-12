<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Automation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'command',
        'cron_expression',
        'timezone',
        'daily_time',
        'is_active',
        'timeout_seconds',
        'run_via',
        'last_run_at',
        'last_run_status',
        'last_runtime_ms',
        'notify_on_fail',
        'created_by',
        'updated_by',
        'schedule_frequencies',
        'schedule_mode',
        'day_times',
        'run_times',
        'weekly_days',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notify_on_fail' => 'boolean',
        'last_run_at' => 'datetime',
        'schedule_frequencies' => 'array',
        'schedule_mode' => 'string',
        'day_times' => 'array',
        'run_times' => 'array',
        'weekly_days' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Automation $automation): void {
            if (empty($automation->slug)) {
                $automation->slug = Str::slug($automation->name);
            }
        });
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class)->latest();
    }

    public function scopeApplyIndexFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $scheduleMode = $filters['schedule_mode'] ?? null;
        $lastRunStatus = $filters['last_run_status'] ?? null;
        $createdBy = trim((string) ($filters['created_by'] ?? ''));
        $updatedBy = trim((string) ($filters['updated_by'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('command', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('is_active', $status === 'active');
        }

        if (in_array($scheduleMode, ['daily', 'custom'], true)) {
            $query->where('schedule_mode', $scheduleMode);
        }

        if (in_array($lastRunStatus, ['success', 'failed'], true)) {
            $query->where('last_run_status', $lastRunStatus);
        } elseif ($lastRunStatus === 'never') {
            $query->whereNull('last_run_at');
        }

        if ($createdBy !== '') {
            $query->where('created_by', $createdBy);
        }

        if ($updatedBy !== '') {
            $query->where('updated_by', $updatedBy);
        }

        return $query;
    }

    public function shouldRunNow(bool $runNow = false): bool
    {
        // 1) If automation is disabled → don't run
        if (! $this->is_active) {
            return false;
        }

        // 2) Determine timezone (automation timezone > app timezone)
        $timezone = $this->timezone ?: config('app.timezone');

        try {
            $now = now($timezone)->startOfMinute();
        } catch (Throwable $exception) {
            Log::warning('Invalid timezone for automation', [
                'automation_id' => $this->id,
                'timezone'      => $this->timezone,
                'error'         => $exception->getMessage(),
            ]);

            $timezone = config('app.timezone');
            $now = now($timezone)->startOfMinute();
        }

        // If run_now was explicitly requested, skip schedule gates (run immediately)
        if ($runNow === true) {
            return true;
        }

        $mode = $this->schedule_mode ?? 'daily';

        if ($mode === 'custom') {
            $dayKey = strtolower($now->format('D')); // mon, tue, ...
            $timesForDay = $this->day_times[$dayKey] ?? [];
            $timesForDay = $this->normalizeTimesArray($timesForDay);

            if (empty($timesForDay)) {
                return false;
            }

            if (! in_array($now->format('H:i'), $timesForDay, true)) {
                return false;
            }
        } else {
            // daily mode
            $dailyTimes = $this->normalizeRunTimes();
            if (! empty($dailyTimes)) {
                if (! in_array($now->format('H:i'), $dailyTimes, true)) {
                    return false;
                }
            } elseif ($this->daily_time) {
                if ($now->format('H:i') !== $this->daily_time) {
                    return false;
                }
            } else {
                // Cron fallback
                $cronExpression = $this->cron_expression ?: '* * * * *';

                try {
                    $cron = new CronExpression($cronExpression);
                } catch (Throwable $exception) {
                    Log::warning('Invalid cron expression for automation', [
                        'automation_id'  => $this->id,
                        'cron_expression' => $this->cron_expression,
                        'error'          => $exception->getMessage(),
                    ]);

                    return false;
                }

                if (! $cron->isDue($now)) {
                    return false;
                }
            }
        }

        // 5) Prevent double-run within the same minute
        if (! is_null($this->last_run_at)) {
            $lastRun = $this->last_run_at
                ->copy()
                ->timezone($timezone)
                ->startOfMinute();

            // If last run is the same minute → skip
            if (! $lastRun->lt($now)) {
                return false;
            }
        }

        // 6) All conditions passed → should run
        return true;
    }

    public function nextRunAt(): ?Carbon
    {
        if (! $this->is_active) {
            return null;
        }

        $timezone = $this->timezone ?: config('app.timezone');

        try {
            $now = now($timezone)->startOfMinute();
        } catch (Throwable $exception) {
            Log::warning('Invalid timezone for automation', [
                'automation_id' => $this->id,
                'timezone' => $this->timezone,
                'error' => $exception->getMessage(),
            ]);

            $timezone = config('app.timezone');
            $now = now($timezone)->startOfMinute();
        }

        $mode = $this->schedule_mode ?? 'daily';

        if ($mode === 'custom') {
            $dayTimes = $this->normalizedDayTimes();

            for ($i = 0; $i <= 14; $i++) {
                $candidateDay = $now->copy()->addDays($i);
                $dayKey = strtolower($candidateDay->format('D'));
                $times = $dayTimes[$dayKey] ?? [];

                foreach ($times as $time) {
                    $candidate = $candidateDay->copy()->setTimeFromTimeString($time);
                    if ($candidate->greaterThanOrEqualTo($now)) {
                        return $candidate;
                    }
                }
            }

            return null;
        }

        // daily mode
        $dailyTimes = $this->normalizeRunTimes();
        if (! empty($dailyTimes)) {
            for ($i = 0; $i <= 1; $i++) {
                $candidateDay = $now->copy()->addDays($i);
                foreach ($dailyTimes as $time) {
                    $candidate = $candidateDay->copy()->setTimeFromTimeString($time);
                    if ($candidate->greaterThanOrEqualTo($now)) {
                        return $candidate;
                    }
                }
            }
        }

        if ($this->daily_time) {
            $candidate = $now->copy()->setTimeFromTimeString($this->daily_time);

            if ($candidate->lt($now)) {
                $candidate->addDay();
            }

            return $candidate;
        }

        // Cron fallback
        $cronExpression = $this->cron_expression ?: '* * * * *';

        try {
            $cron = new CronExpression($cronExpression);
        } catch (Throwable $exception) {
            Log::warning('Invalid cron expression for automation (next run calc)', [
                'automation_id' => $this->id,
                'cron_expression' => $this->cron_expression,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $next = $cron->getNextRunDate($now, 0, true, $timezone);

        return Carbon::instance($next)->setTimezone($timezone);
    }

    private function normalizeRunTimes(): array
    {
        $times = $this->run_times ?? [];
        $times = array_filter($times ?? [], static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        });

        $times = array_values($times);
        sort($times);

        return $times;
    }

    private function normalizeTimesArray(array $times): array
    {
        $times = array_filter($times ?? [], static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        });

        $times = array_values($times);
        sort($times);

        return $times;
    }

    private function normalizedDayTimes(): array
    {
        $times = $this->day_times ?? [];
        $normalized = [];

        foreach ($times as $day => $list) {
            if (! is_array($list)) {
                continue;
            }

            $clean = array_values(array_filter($list, static fn ($v) => is_string($v) && trim($v) !== ''));
            sort($clean);

            if (! empty($clean)) {
                $normalized[$day] = $clean;
            }
        }

        return $normalized;
    }
}
