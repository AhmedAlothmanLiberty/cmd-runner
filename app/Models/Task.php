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
    public const STATUS_DEPLOYED_S = 'deployed-s';
    public const STATUS_DEPLOYED_P = 'deployed-p';
    public const STATUS_BACKLOG = 'backlog';
    public const STATUS_REOPEN = 'reopen';

    public const STATUS_LABELS = [
        self::STATUS_TODO => 'To Do',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_DONE => 'Testing',
        self::STATUS_DEPLOYED_S => 'Staging',
        self::STATUS_DEPLOYED_P => 'Production',
        self::STATUS_COMPLETED => 'Complete',
        self::STATUS_BACKLOG => 'Backlog',
        self::STATUS_REOPEN => 'Reopen',
    ];

    public const RESTRICTED_STATUSES = [
        self::STATUS_BACKLOG,
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
        $excluded = [self::STATUS_BACKLOG];

        if (! self::canManageRestricted($user)) {
            $excluded[] = self::STATUS_DEPLOYED_S;
            $excluded[] = self::STATUS_DEPLOYED_P;
        }

        return $query->whereNotIn('status', $excluded);
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
        $labels = self::STATUS_LABELS;
        unset($labels[self::STATUS_BACKLOG]);

        if (! self::canManageRestricted($user)) {
            unset($labels[self::STATUS_DEPLOYED_S], $labels[self::STATUS_DEPLOYED_P]);
        }

        return $labels;
    }

    public static function formStatusLabels(): array
    {
        $formStatuses = [
            self::STATUS_TODO,
            self::STATUS_IN_PROGRESS,
            self::STATUS_DONE,
            self::STATUS_DEPLOYED_S,
            self::STATUS_DEPLOYED_P,
            self::STATUS_COMPLETED,
        ];

        return array_intersect_key(self::STATUS_LABELS, array_flip($formStatuses));
    }

    public static function editStatusLabels(?self $task = null): array
    {
        $labels = self::formStatusLabels();

        if ($task && $task->status === self::STATUS_BACKLOG) {
            $labels[self::STATUS_BACKLOG] = self::STATUS_LABELS[self::STATUS_BACKLOG];
        }

        $labels[self::STATUS_REOPEN] = self::STATUS_LABELS[self::STATUS_REOPEN];

        return $labels;
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

    public static function formStatuses(): array
    {
        return array_keys(self::formStatusLabels());
    }

    public static function editStatuses(?self $task = null): array
    {
        return array_keys(self::editStatusLabels($task));
    }

    public static function allStatuses(): array
    {
        return array_keys(self::STATUS_LABELS);
    }
}
