<?php

namespace App\Notifications\Channels;

use Cmd\Reports\Services\EmailSenderService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class GraphMailChannel
{
    public function __construct(private EmailSenderService $emailSender)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toGraphMail')) {
            return;
        }

        $recipientEmail = trim((string) ($notifiable->email ?? ''));
        if ($recipientEmail === '') {
            return;
        }

        $message = $notification->toGraphMail($notifiable);

        $subject = trim((string) ($message['subject'] ?? ''));
        $cc = (array) ($message['cc'] ?? []);
        $bcc = (array) ($message['bcc'] ?? []);
        $attachments = (array) ($message['attachments'] ?? []);

        $sent = false;
        if (array_key_exists('html', $message)) {
            $sent = $this->emailSender->sendMailHtml($subject, (string) $message['html'], [$recipientEmail], $cc, $bcc, $attachments);
        } elseif (array_key_exists('text', $message)) {
            $sent = $this->emailSender->sendMail($subject, (string) $message['text'], [$recipientEmail], $cc, $bcc, $attachments);
        }

        if (! $sent) {
            Log::warning('GraphMailChannel: failed to send notification email.', [
                'notification' => $notification::class,
                'to' => $recipientEmail,
            ]);
        }
    }
}

