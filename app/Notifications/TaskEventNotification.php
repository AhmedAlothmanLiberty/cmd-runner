<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Task $task,
        private string $type,
        private string $title,
        private string $message,
        private ?string $status = null,
        private ?string $actorName = null,
        private ?string $comment = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'task_title' => $this->task->title,
            'assigned_name' => $this->task->assignedTo?->name,
            'status' => $this->status,
            'actor_name' => $this->actorName,
            'comment' => $this->comment,
            'url' => route('admin.tasks.show', $this->task),
        ];
    }
}
