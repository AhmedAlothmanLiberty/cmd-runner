<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    public const STATUS_TODO = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_DEPLOYED_S = 'deployed-s';
    public const STATUS_DEPLOYED_P = 'deployed-p';
    public const STATUS_REOPEN = 'reopen';

    public const STATUS_LABELS = [
        self::STATUS_TODO => 'To do',
        self::STATUS_IN_PROGRESS => 'In progress',
        self::STATUS_DONE => 'Done',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_BLOCKED => 'Blocked',
        self::STATUS_ON_HOLD => 'On hold',
        self::STATUS_DEPLOYED_S => 'Deployed S',
        self::STATUS_DEPLOYED_P => 'Deployed P',
        self::STATUS_REOPEN => 'Reopen',
    ];

    public const RESTRICTED_STATUSES = [
        self::STATUS_ON_HOLD,
        self::STATUS_DEPLOYED_S,
        self::STATUS_DEPLOYED_P,
    ];

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'completed_at',
        'assigned_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_task_label');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (self::canManageRestricted($user)) {
            return $query;
        }

        return $query->whereNotIn('status', self::RESTRICTED_STATUSES);
    }

    public function scopeBacklog(Builder $query): Builder
    {
        return $query->whereIn('status', self::RESTRICTED_STATUSES);
    }

    public function isRestrictedStatus(): bool
    {
        return in_array($this->status, self::RESTRICTED_STATUSES, true);
    }

    public static function canManageRestricted(?User $user): bool
    {
        return $user?->hasAnyRole(['admin', 'super-admin']) ?? false;
    }

    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    public static function visibleStatusLabels(?User $user): array
    {
        if (self::canManageRestricted($user)) {
            return self::STATUS_LABELS;
        }

        return array_diff_key(self::STATUS_LABELS, array_flip(self::RESTRICTED_STATUSES));
    }

    public static function backlogStatusLabels(): array
    {
        return array_intersect_key(self::STATUS_LABELS, array_flip(self::RESTRICTED_STATUSES));
    }

    public static function restrictedStatuses(): array
    {
        return self::RESTRICTED_STATUSES;
    }

    public static function allowedStatusesFor(?User $user): array
    {
        return array_keys(self::visibleStatusLabels($user));
    }

    public static function allStatuses(): array
    {
        return array_keys(self::STATUS_LABELS);
    }
}
