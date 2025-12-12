<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        if ($this->daily_time) {
            if ($now->format('H:i') !== $this->daily_time) {
                return false;
            }
        }

        // 4) Evaluate CRON expression
        try {
            $cron = new CronExpression($this->cron_expression);
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
}
