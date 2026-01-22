<?php

namespace App\Notifications;

use App\Models\Automation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AutomationEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Automation $automation,
        private string $type,
        private string $title,
        private string $message,
        private ?string $actorName = null,
        private ?string $url = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'actor_name' => $this->actorName,
            'url' => $this->url ?? route('admin.automations.edit', $this->automation),
            'automation_id' => $this->automation->id,
            'automation_name' => $this->automation->name,
            'automation_slug' => $this->automation->slug,
            'automation_command' => $this->automation->command,
            'assigned_name' => 'Admins',
            'task_title' => $this->automation->name,
        ];
    }
}

