<?php

namespace App\Notifications;

use App\Notifications\Channels\GraphMailChannel;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReopenedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Task $task,
        private ?string $actorName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        $graph = (array) config('services.graph', []);
        $hasGraphConfig = ($graph['tenant_id'] ?? '') !== ''
            && ($graph['client_id'] ?? '') !== ''
            && ($graph['client_secret'] ?? '') !== ''
            && ($graph['from_address'] ?? '') !== '';

        return $hasGraphConfig ? [GraphMailChannel::class] : ['mail'];
    }

    public function toGraphMail(object $notifiable): array
    {
        $title = (string) $this->task->title;
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $url = route('admin.tasks.show', $this->task);
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $actorLine = $this->actorName ? ('Reopened by ' . htmlspecialchars($this->actorName, ENT_QUOTES, 'UTF-8') . '.') : null;

        $lines = [
            "<p>The task &quot;{$escapedTitle}&quot; has been reopened.</p>",
        ];
        if ($actorLine) {
            $lines[] = "<p>{$actorLine}</p>";
        }
        $lines[] = "<p><a href=\"{$escapedUrl}\">View task</a></p>";

        return [
            'subject' => "Task reopened: {$title}",
            'html' => implode("\n", $lines),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = (string) $this->task->title;
        $url = route('admin.tasks.show', $this->task);

        $mail = (new MailMessage())
            ->subject("Task reopened: {$title}")
            ->line("The task \"{$title}\" has been reopened.");

        if ($this->actorName) {
            $mail->line("Reopened by {$this->actorName}.");
        }

        return $mail->action('View task', $url);
    }
}
