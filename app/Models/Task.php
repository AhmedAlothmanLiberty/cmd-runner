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
    public const STATUS_BACKLOG = 'backlog';
    public const STATUS_DEPLOYED_S = 'deployed-s';
    public const STATUS_DEPLOYED_P = 'deployed-p';
    public const STATUS_REOPEN = 'reopen';

    public const STATUS_LABELS = [
        self::STATUS_TODO => 'To Do',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_DONE => 'Testing',
        self::STATUS_COMPLETED => 'Complete',
        self::STATUS_BACKLOG => 'Backlog',
        self::STATUS_DEPLOYED_S => 'Staging',
        self::STATUS_DEPLOYED_P => 'Production',
        self::STATUS_REOPEN => 'Reopen',
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

    public function scopeStandardList(Builder $query): Builder
    {
        return $query
            ->whereNotNull('assigned_to')
            ->whereIn('status', self::standardStatuses());
    }

    public function scopeStandardListFor(Builder $query, ?User $user): Builder
    {
        return $query
            ->whereNotNull('assigned_to')
            ->whereIn('status', self::indexStatusesFor($user));
    }

    public function scopeBacklogList(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', self::STATUS_BACKLOG)
                ->orWhere(function (Builder $unassigned): void {
                    $unassigned
                        ->whereNull('assigned_to')
                        ->whereNotIn('status', self::deploymentStatuses());
                });
        });
    }

    public function isBacklogList(): bool
    {
        return $this->status === self::STATUS_BACKLOG || $this->assigned_to === null;
    }

    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    public static function standardStatusLabels(): array
    {
        return array_intersect_key(self::STATUS_LABELS, array_flip(self::standardStatuses()));
    }

    public static function backlogStatusLabels(): array
    {
        return array_intersect_key(self::STATUS_LABELS, array_flip([self::STATUS_BACKLOG]));
    }

    public static function deploymentStatuses(): array
    {
        return [
            self::STATUS_DEPLOYED_S,
            self::STATUS_DEPLOYED_P,
        ];
    }

    public static function canUseDeploymentStatuses(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return ($user->hasAnyRole(['admin', 'super-admin']) ?? false) || $user->can('manage-tasks');
    }

    public static function indexStatusesFor(?User $user): array
    {
        $statuses = self::standardStatuses();

        if (self::canUseDeploymentStatuses($user)) {
            $statuses = array_values(array_unique(array_merge($statuses, self::deploymentStatuses())));
        }

        return $statuses;
    }

    public static function indexStatusLabelsFor(?User $user): array
    {
        return array_intersect_key(self::STATUS_LABELS, array_flip(self::indexStatusesFor($user)));
    }

    public static function formStatusLabels(): array
    {
        return array_diff_key(self::STATUS_LABELS, array_flip(self::deploymentStatuses()));
    }

    public static function editStatusLabels(?self $task = null): array
    {
        $labels = self::STATUS_LABELS;

        if ($task && $task->status !== '' && ! array_key_exists($task->status, $labels)) {
            $labels[$task->status] = str_replace(['_', '-'], ' ', $task->status);
        }

        return $labels;
    }

    public static function formStatuses(): array
    {
        return array_keys(self::formStatusLabels());
    }

    public static function editStatuses(?self $task = null): array
    {
        return array_keys(self::editStatusLabels($task));
    }

    public static function standardStatuses(): array
    {
        return [
            self::STATUS_TODO,
            self::STATUS_IN_PROGRESS,
            self::STATUS_DONE,
            self::STATUS_COMPLETED,
            self::STATUS_REOPEN,
        ];
    }

    public static function allStatuses(): array
    {
        return array_keys(self::STATUS_LABELS);
    }
}
