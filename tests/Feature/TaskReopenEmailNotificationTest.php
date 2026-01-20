<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Cmd\Reports\Services\EmailSenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaskReopenEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_user_gets_email_when_task_is_reopened(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $adminRole = Role::findOrCreate('admin');

        $actor = User::factory()->create();
        $actor->assignRole($adminRole);

        $assignee = User::factory()->create();

        $task = Task::create([
            'title' => 'Reopen notification',
            'status' => Task::STATUS_TODO,
            'assigned_to' => $assignee->id,
        ]);

        config()->set('services.graph.tenant_id', 'test-tenant');
        config()->set('services.graph.client_id', 'test-client');
        config()->set('services.graph.client_secret', 'test-secret');
        config()->set('services.graph.from_address', 'test@example.com');

        $emailSender = $this->mock(EmailSenderService::class);
        $emailSender
            ->shouldReceive('sendMailHtml')
            ->once()
            ->withArgs(function (string $subject, string $body, array $to, array $cc, array $bcc, array $attachments) use ($assignee): bool {
                return str_contains($subject, 'Task reopened:')
                    && $to === [$assignee->email]
                    && $cc === []
                    && $bcc === []
                    && $attachments === []
                    && $body !== '';
            })
            ->andReturn(true);

        $this
            ->actingAs($actor)
            ->from(route('admin.tasks.show', $task))
            ->patch(route('admin.tasks.status', $task), ['status' => Task::STATUS_REOPEN])
            ->assertRedirect(route('admin.tasks.show', $task));
    }
}
