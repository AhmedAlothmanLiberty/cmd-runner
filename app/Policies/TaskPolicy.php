<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return (int) $task->assigned_to === (int) $user->id;
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return (int) $task->assigned_to === (int) $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return (int) $task->assigned_to === (int) $user->id;
    }
}
