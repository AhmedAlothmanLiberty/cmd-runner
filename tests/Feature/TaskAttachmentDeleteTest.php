<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaskAttachmentDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_delete_attachment_and_file(): void
    {
        Storage::fake();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view-tasks');
        Permission::findOrCreate('delete-task-attachments');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-tasks', 'delete-task-attachments']);
        $task = Task::create([
            'title' => 'Delete task',
            'assigned_to' => $user->id,
            'status' => Task::STATUS_TODO,
        ]);

        $file = UploadedFile::fake()->create('doc.txt', 10, 'text/plain');
        $path = Storage::putFile('task-attachments', $file);

        $attachment = $task->attachments()->create([
            'user_id' => $user->id,
            'file_name' => 'doc.txt',
            'file_path' => $path,
            'file_size' => Storage::size($path),
            'mime_type' => 'text/plain',
        ]);

        $this
            ->actingAs($user)
            ->from(route('admin.tasks.show', $task))
            ->delete(route('admin.tasks.attachments.destroy', ['task' => $task, 'attachment' => $attachment]))
            ->assertRedirect(route('admin.tasks.show', $task));

        $this->assertDatabaseMissing('task_attachments', ['id' => $attachment->id]);
        Storage::assertMissing($path);
    }

    public function test_unassigned_user_cannot_delete_attachment(): void
    {
        Storage::fake();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view-tasks');
        Permission::findOrCreate('delete-task-attachments');

        $assignedUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->givePermissionTo(['view-tasks']);
        $task = Task::create([
            'title' => 'Forbidden task',
            'assigned_to' => $assignedUser->id,
            'status' => Task::STATUS_TODO,
        ]);

        $path = Storage::put('task-attachments/readme.txt', 'hello');

        $attachment = $task->attachments()->create([
            'user_id' => $assignedUser->id,
            'file_name' => 'readme.txt',
            'file_path' => 'task-attachments/readme.txt',
            'file_size' => Storage::size('task-attachments/readme.txt'),
            'mime_type' => 'text/plain',
        ]);

        $this
            ->actingAs($otherUser)
            ->delete(route('admin.tasks.attachments.destroy', ['task' => $task, 'attachment' => $attachment]))
            ->assertForbidden();
    }

    public function test_delete_is_forbidden_for_backlog_task_without_view_backlog_permission(): void
    {
        Storage::fake();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view-tasks');
        Permission::findOrCreate('delete-task-attachments');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-tasks', 'delete-task-attachments']);
        $task = Task::create([
            'title' => 'Backlog task',
            'status' => Task::STATUS_BACKLOG,
        ]);

        $path = Storage::put('task-attachments/readme.txt', 'hello');

        $attachment = $task->attachments()->create([
            'user_id' => $user->id,
            'file_name' => 'readme.txt',
            'file_path' => 'task-attachments/readme.txt',
            'file_size' => Storage::size('task-attachments/readme.txt'),
            'mime_type' => 'text/plain',
        ]);

        $this
            ->actingAs($user)
            ->delete(route('admin.tasks.attachments.destroy', ['task' => $task, 'attachment' => $attachment]))
            ->assertForbidden();
    }
}
