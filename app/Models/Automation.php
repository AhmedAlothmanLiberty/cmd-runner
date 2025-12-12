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

    public function shouldRunNow(): bool
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

        // 3) Handle DAILY fixed-time logic (e.g., 01:00 LA time)
        $cronExpression = $this->cronExpressionWithDailyTime();

        if (! $cronExpression) {
            return false;
        }

        // 4) Evaluate CRON expression (daily_time baked into the cron if present)
        try {
            $cron = new CronExpression($cronExpression);
        } catch (Throwable $exception) {
            Log::warning('Invalid cron expression for automation', [
                'automation_id'  => $this->id,
                'cron_expression' => $cronExpression,
                'error'          => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $cron->isDue($now)) {
            return false;
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

        $cronExpression = $this->cronExpressionWithDailyTime();

        if (! $cronExpression) {
            return null;
        }

        try {
            $cron = new CronExpression($cronExpression);
            $next = $cron->getNextRunDate($now, 0, true, $timezone);

            return Carbon::instance($next)->setTimezone($timezone);
        } catch (Throwable $exception) {
            Log::warning('Unable to calculate next run for automation', [
                'automation_id' => $this->id,
                'cron_expression' => $cronExpression,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function cronExpressionWithDailyTime(): ?string
    {
        if (! $this->daily_time) {
            return $this->cron_expression;
        }

        $parts = preg_split('/\s+/', trim($this->cron_expression));

        if (! $parts || count($parts) < 5) {
            Log::warning('Cron expression missing required parts for daily time merge', [
                'automation_id' => $this->id,
                'cron_expression' => $this->cron_expression,
            ]);

            return null;
        }

        [$hour, $minute] = explode(':', $this->daily_time);

        $parts[0] = (string) (int) $minute; // minute
        $parts[1] = (string) (int) $hour;   // hour

        // Preserve only the first 5 parts (minute hour dom month dow)
        $parts = array_slice($parts, 0, 5);

        return implode(' ', $parts);
    }
}
