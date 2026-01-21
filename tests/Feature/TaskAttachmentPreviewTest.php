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

class TaskAttachmentPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_inline_response_for_image_attachment(): void
    {
        Storage::fake();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view-tasks');
        Permission::findOrCreate('download-task-attachments');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-tasks', 'download-task-attachments']);
        $task = Task::create([
            'title' => 'Preview task',
            'status' => Task::STATUS_TODO,
            'assigned_to' => $user->id,
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view-tasks');
        Permission::findOrCreate('download-task-attachments');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-tasks', 'download-task-attachments']);
        $task = Task::create([
            'title' => 'Non-image task',
            'status' => Task::STATUS_TODO,
            'assigned_to' => $user->id,
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

    public function test_preview_is_forbidden_for_backlog_task_without_view_backlog_permission(): void
    {
        Storage::fake();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('download-task-attachments');
        Permission::findOrCreate('view-tasks');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-tasks', 'download-task-attachments']);
        $task = Task::create([
            'title' => 'Backlog task',
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
