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
        if (! $this->is_active) {
            return false;
        }

        $now = now()->startOfMinute();

        try {
            $cron = new CronExpression($this->cron_expression);
        } catch (Throwable $exception) {
            Log::warning('Invalid cron expression for automation', [
                'automation_id' => $this->id,
                'cron_expression' => $this->cron_expression,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $cron->isDue($now)) {
            return false;
        }

        if (is_null($this->last_run_at)) {
            return true;
        }

        return $this->last_run_at->lt($now);
    }
}
