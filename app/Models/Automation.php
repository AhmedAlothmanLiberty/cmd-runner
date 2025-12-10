<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        return $this->hasMany(AutomationLog::class);
    }
}
