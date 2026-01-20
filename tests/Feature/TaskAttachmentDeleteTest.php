<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskAttachmentDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_user_can_delete_attachment_and_file(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Delete task',
            'assigned_to' => $user->id,
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

        $assignedUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $task = Task::create([
            'title' => 'Forbidden task',
            'assigned_to' => $assignedUser->id,
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

    public function test_cannot_delete_attachment_when_task_is_restricted(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Restricted task',
            'status' => Task::STATUS_BACKLOG,
            'assigned_to' => $user->id,
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

