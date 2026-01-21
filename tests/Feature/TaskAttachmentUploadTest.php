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

class TaskAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_upload_attachments_from_show_page(): void
    {
        Storage::fake();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view-tasks');
        Permission::findOrCreate('upload-task-attachments');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-tasks', 'upload-task-attachments']);

        $task = Task::create([
            'title' => 'Upload task',
            'status' => Task::STATUS_TODO,
            'assigned_to' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('admin.tasks.attachments.store', $task), [
                'attachments' => [
                    UploadedFile::fake()->create('doc.txt', 10, 'text/plain'),
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('task_attachments', 1);
        $path = Task::first()->attachments()->first()->file_path;
        Storage::assertExists($path);
    }

    public function test_upload_is_forbidden_for_backlog_task_without_view_backlog_permission(): void
    {
        Storage::fake();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view-tasks');
        Permission::findOrCreate('upload-task-attachments');

        $user = User::factory()->create();
        $user->givePermissionTo(['view-tasks', 'upload-task-attachments']);

        $task = Task::create([
            'title' => 'Backlog task',
            'status' => Task::STATUS_BACKLOG,
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.tasks.attachments.store', $task), [
                'attachments' => [
                    UploadedFile::fake()->image('photo.jpg'),
                ],
            ])
            ->assertForbidden();
    }
}
