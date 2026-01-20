<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskAttachmentPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_inline_response_for_image_attachment(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Preview task',
        ]);

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $path = Storage::putFile('task-attachments', $file);

        $attachment = $task->attachments()->create([
            'user_id' => $user->id,
            'file_name' => 'photo.jpg',
            'file_path' => $path,
            'file_size' => Storage::size($path),
            'mime_type' => 'image/jpeg',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.tasks.attachments.preview', ['task' => $task, 'attachment' => $attachment]));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_preview_returns_404_for_non_image_attachment(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Non-image task',
        ]);

        Storage::put('task-attachments/readme.txt', 'hello');

        $attachment = $task->attachments()->create([
            'user_id' => $user->id,
            'file_name' => 'readme.txt',
            'file_path' => 'task-attachments/readme.txt',
            'file_size' => Storage::size('task-attachments/readme.txt'),
            'mime_type' => 'text/plain',
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.tasks.attachments.preview', ['task' => $task, 'attachment' => $attachment]))
            ->assertNotFound();
    }

    public function test_preview_is_forbidden_for_restricted_task_when_user_not_admin(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Restricted task',
            'status' => Task::STATUS_BACKLOG,
        ]);

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $path = Storage::putFile('task-attachments', $file);

        $attachment = $task->attachments()->create([
            'user_id' => $user->id,
            'file_name' => 'photo.jpg',
            'file_path' => $path,
            'file_size' => Storage::size($path),
            'mime_type' => 'image/jpeg',
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.tasks.attachments.preview', ['task' => $task, 'attachment' => $attachment]))
            ->assertForbidden();
    }
}

