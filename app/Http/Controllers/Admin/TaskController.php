<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskRequest;
use App\Http\Requests\Admin\UpdateTaskRequest;
use App\Models\Task;
use App\Models\TaskLabel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $query = Task::query()->with(['assignedTo', 'createdBy', 'updatedBy', 'labels']);

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $priority = $request->input('priority');
        $assignedTo = $request->input('assigned_to');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['todo', 'in_progress', 'done', 'blocked'], true)) {
            $query->where('status', $status);
        }

        if (in_array($priority, ['low', 'medium', 'high'], true)) {
            $query->where('priority', $priority);
        }

        if (! empty($assignedTo)) {
            $query->where('assigned_to', $assignedTo);
        }

        $tasks = $query
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->appends($request->query());

        $filters = [
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
            'assigned_to' => $assignedTo,
        ];

        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.tasks.index', compact('tasks', 'filters', 'users'));
    }

    public function create(): View
    {
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        $labels = TaskLabel::query()->orderBy('name')->get();

        return view('admin.tasks.create', compact('users', 'labels'));
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);
        $task->load(['labels', 'attachments', 'comments.user', 'assignedTo', 'createdBy', 'updatedBy']);

        return view('admin.tasks.show', compact('task'));
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = auth()->id();

        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $data['completed_at'] = $data['status'] === 'done' ? now() : null;

        $task = Task::create(Arr::except($data, ['labels', 'comment', 'attachments']));

        $this->syncLabels($task, $data['labels'] ?? []);
        $this->storeAttachments($task, $request);
        $this->storeComment($task, $request->input('comment'), $userId);

        return redirect()->route('admin.tasks.index')->with('status', 'Task created.');
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);
        $task->load(['labels', 'attachments', 'comments.user']);
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        $labels = TaskLabel::query()->orderBy('name')->get();

        return view('admin.tasks.edit', compact('task', 'users', 'labels'));
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $data = $request->validated();
        $userId = auth()->id();

        $data['updated_by'] = $userId;
        if ($data['status'] === 'done') {
            $data['completed_at'] = $task->completed_at ?? now();
        } else {
            $data['completed_at'] = null;
        }

        $task->update(Arr::except($data, ['labels', 'comment', 'attachments']));

        $this->syncLabels($task, $data['labels'] ?? []);
        $this->storeAttachments($task, $request);
        $this->storeComment($task, $request->input('comment'), $userId);

        return redirect()->route('admin.tasks.index')->with('status', 'Task updated.');
    }

    public function addComment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'comment' => ['required', 'string'],
        ]);

        $task->comments()->create([
            'user_id' => auth()->id(),
            'body' => $data['comment'],
        ]);

        return back()->with('status', 'Comment added.');
    }

    public function updateStatus(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', 'in:todo,in_progress,done,blocked'],
        ]);

        $task->status = $validated['status'];
        $task->updated_by = auth()->id();
        $task->completed_at = $task->status === 'done' ? ($task->completed_at ?? now()) : null;
        $task->save();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return back()->with('status', 'Task status updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $task->delete();

        return redirect()->route('admin.tasks.index')->with('status', 'Task deleted.');
    }

    private function syncLabels(Task $task, array $labelIds): void
    {
        $task->labels()->sync($labelIds);
    }

    private function storeAttachments(Task $task, Request $request): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $userId = auth()->id();

        foreach ($request->file('attachments', []) as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $path = $file->store('task-attachments');

            $task->attachments()->create([
                'user_id' => $userId,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
            ]);
        }
    }

    private function storeComment(Task $task, ?string $comment, ?int $userId): void
    {
        $comment = trim((string) $comment);

        if ($comment === '') {
            return;
        }

        $task->comments()->create([
            'user_id' => $userId,
            'body' => $comment,
        ]);
    }
}
