<?php

namespace App\Models;

use Cron\CronExpression;
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notify_on_fail' => 'boolean',
        'last_run_at' => 'datetime',
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

        // 3) Handle DAILY fixed-time logic (takes precedence; cron ignored when set)
        if ($this->daily_time) {
            if ($now->format('H:i') !== $this->daily_time) {
                return false;
            }
        } else {
            // 4) Evaluate CRON expression only when daily_time is not set
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

        // If daily_time is set, it is the schedule (ignore cron)
        if ($this->daily_time) {
            $candidate = $now->copy()->setTimeFromTimeString($this->daily_time);

            if ($candidate->lt($now)) {
                $candidate->addDay();
            }

            return $candidate;
        }

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
}
