<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    private function hasTaskPermission(User $user, string $permission): bool
    {
        return $user->can('manage-tasks') || $user->can($permission);
    }

    public function create(User $user): bool
    {
        return $this->hasTaskPermission($user, 'create-task');
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->can('manage-tasks') || $user->can('view-all-tasks')) {
            return true;
        }

        if (in_array($task->status, Task::deploymentStatuses(), true)) {
            return $user->hasAnyRole(['admin', 'super-admin']);
        }

        if ($task->isBacklogList()) {
            return $user->can('view-backlog');
        }

        return $user->can('view-tasks');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->hasTaskPermission($user, 'update-task');
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->hasTaskPermission($user, 'delete-task');
    }

    public function changeStatus(User $user, Task $task): bool
    {
        if (! $this->hasTaskPermission($user, 'change-task-status')) {
            return false;
        }

        return $this->view($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->hasTaskPermission($user, 'assign-task');
    }

    public function comment(User $user, Task $task): bool
    {
        if (! $this->hasTaskPermission($user, 'comment-task')) {
            return false;
        }

        return $this->view($user, $task);
    }

    public function uploadAttachments(User $user, Task $task): bool
    {
        if (! $this->hasTaskPermission($user, 'upload-task-attachments')) {
            return false;
        }

        return $this->view($user, $task);
    }

    public function downloadAttachments(User $user, Task $task): bool
    {
        if (! $this->hasTaskPermission($user, 'download-task-attachments')) {
            return false;
        }

        return $this->view($user, $task);
    }

    public function deleteAttachments(User $user, Task $task): bool
    {
        if (! $this->hasTaskPermission($user, 'delete-task-attachments')) {
            return false;
        }

        return $this->view($user, $task);
    }
}
