<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskRequest;
use App\Http\Requests\Admin\UpdateTaskRequest;
use App\Models\Task;
use App\Models\TaskLabel;
use App\Models\User;
use App\Notifications\TaskEventNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Task::query()
            ->with(['assignedTo', 'createdBy', 'updatedBy', 'labels'])
            ->visibleTo($user);

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $priority = $request->input('priority');
        $assignedTo = $request->input('assigned_to');
        $categoryId = $request->input('category_id');
        $perPage = $request->input('per_page', '15');
        $perPageOptions = ['15', '25', '50', 'all'];
        if (! in_array((string) $perPage, $perPageOptions, true)) {
            $perPage = '15';
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (in_array($status, Task::allowedStatusesFor($user), true)) {
            $query->where('status', $status);
        }

        if (in_array($priority, ['low', 'medium', 'high'], true)) {
            $query->where('priority', $priority);
        }

        if (! empty($assignedTo)) {
            $query->where('assigned_to', $assignedTo);
        }

        if (! empty($categoryId)) {
            $query->whereHas('labels', function ($q) use ($categoryId): void {
                $q->where('task_labels.id', $categoryId);
            });
        }

        $perPageValue = $perPage === 'all' ? max(1, (int) $query->count()) : (int) $perPage;
        $tasks = $query
            ->orderByDesc('updated_at')
            ->paginate($perPageValue)
            ->appends($request->query());

        $filters = [
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
            'assigned_to' => $assignedTo,
            'category_id' => $categoryId,
            'per_page' => (string) $perPage,
        ];

        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);
        $categories = TaskLabel::query()->orderBy('name')->get(['id', 'name', 'color']);
        $statusOptions = Task::visibleStatusLabels($user);

        return view('admin.tasks.index', compact('tasks', 'filters', 'users', 'categories', 'statusOptions', 'perPageOptions'));
    }

    public function backlog(Request $request): View
    {
        $user = $request->user();
        if (! Task::canManageRestricted($user)) {
            abort(403);
        }

        $query = Task::query()
            ->with(['assignedTo', 'createdBy', 'updatedBy', 'labels'])
            ->backlog();

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $priority = $request->input('priority');
        $assignedTo = $request->input('assigned_to');
        $categoryId = $request->input('category_id');
        $perPage = $request->input('per_page', '15');
        $perPageOptions = ['15', '25', '50', 'all'];
        if (! in_array((string) $perPage, $perPageOptions, true)) {
            $perPage = '15';
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (in_array($status, Task::restrictedStatuses(), true)) {
            $query->where('status', $status);
        }

        if (in_array($priority, ['low', 'medium', 'high'], true)) {
            $query->where('priority', $priority);
        }

        if (! empty($assignedTo)) {
            $query->where('assigned_to', $assignedTo);
        }

        if (! empty($categoryId)) {
            $query->whereHas('labels', function ($q) use ($categoryId): void {
                $q->where('task_labels.id', $categoryId);
            });
        }

        $perPageValue = $perPage === 'all' ? max(1, (int) $query->count()) : (int) $perPage;
        $tasks = $query
            ->orderByDesc('updated_at')
            ->paginate($perPageValue)
            ->appends($request->query());

        $filters = [
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
            'assigned_to' => $assignedTo,
            'category_id' => $categoryId,
            'per_page' => (string) $perPage,
        ];

        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);
        $categories = TaskLabel::query()->orderBy('name')->get(['id', 'name', 'color']);
        $statusOptions = Task::backlogStatusLabels();

        return view('admin.tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'users' => $users,
            'categories' => $categories,
            'statusOptions' => $statusOptions,
            'perPageOptions' => $perPageOptions,
            'pageTitle' => 'Backlog',
            'pageSubtitle' => 'On hold or deployed tasks.',
            'backLink' => route('admin.tasks.index'),
            'backLinkLabel' => 'All tasks',
            'isBacklog' => true,
        ]);
    }

    public function create(): View
    {
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        $categories = TaskLabel::query()->orderBy('name')->get();

        $statusOptions = Task::visibleStatusLabels(request()->user());

        return view('admin.tasks.create', compact('users', 'categories', 'statusOptions'));
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
        $data['completed_at'] = in_array($data['status'], ['done', 'completed'], true) ? now() : null;

        $task = Task::create(Arr::except($data, ['category_id', 'comment', 'attachments']));

        $categoryId = $data['category_id'] ?? null;
        $this->syncLabels($task, $categoryId ? [$categoryId] : []);
        $this->storeAttachments($task, $request);
        $this->storeComment($task, $request->input('comment'), $userId);
        $this->notifyTaskEvent($task, 'new_task');

        return redirect()->route('admin.tasks.index')->with('status', 'Task created.');
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);
        $task->load(['labels', 'attachments', 'comments.user']);
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        $categories = TaskLabel::query()->orderBy('name')->get();

        $statusOptions = Task::visibleStatusLabels(request()->user());

        return view('admin.tasks.edit', compact('task', 'users', 'categories', 'statusOptions'));
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $data = $request->validated();
        $userId = auth()->id();

        $data['updated_by'] = $userId;
        if (in_array($data['status'], ['done', 'completed'], true)) {
            $data['completed_at'] = $task->completed_at ?? now();
        } else {
            $data['completed_at'] = null;
        }

        $task->update(Arr::except($data, ['category_id', 'comment', 'attachments']));

        $categoryId = $data['category_id'] ?? null;
        $this->syncLabels($task, $categoryId ? [$categoryId] : []);
        $this->storeAttachments($task, $request);
        $this->storeComment($task, $request->input('comment'), $userId);
        $this->notifyTaskEvent($task, 'task_updated');

        return redirect()->route('admin.tasks.index')->with('status', 'Task updated.');
    }

    public function addComment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task);
        if ($task->isRestrictedStatus() && ! Task::canManageRestricted($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'comment' => ['required', 'string'],
        ]);

        $task->comments()->create([
            'user_id' => auth()->id(),
            'body' => $data['comment'],
        ]);

        $this->notifyTaskEvent($task, 'new_comment', [
            'comment' => 'New comment',
        ]);

        return back()->with('status', 'Comment added.');
    }

    public function updateStatus(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Task::allowedStatusesFor($request->user()))],
        ]);

        $task->status = $validated['status'];
        $task->updated_by = auth()->id();
        $task->completed_at = in_array($task->status, ['done', 'completed'], true)
            ? ($task->completed_at ?? now())
            : null;
        $task->save();

        $this->notifyTaskEvent($task, 'status_changed', [
            'status' => $task->status,
        ]);

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

    // private function notifyTaskEvent(Task $task, string $title, string $message): void
    // {
    //     $actorId = auth()->id();
    //     $recipients = collect();

    //     if ($task->assigned_to) {
    //         $recipients->push($task->assignedTo);
    //     }

    //     if ($task->created_by) {
    //         $recipients->push($task->createdBy);
    //     }

    //     $adminUsers = User::query()
    //         ->whereHas('roles', function ($query): void {
    //             $query->whereIn('name', ['admin', 'super-admin']);
    //         })
    //         ->get();

    //     $recipients = $recipients->merge($adminUsers)
    //         ->filter()
    //         ->unique('id')
    //         ->reject(fn (User $user) => $actorId && (int) $user->id === (int) $actorId);

    //     $recipients->each(function (User $user) use ($task, $title, $message): void {
    //         $user->notify(new TaskEventNotification($task, $title, $message));
    //     });
    // }
    private function notifyTaskEvent(Task $task, string $type, array $context = []): void
    {
        $actorId = auth()->id();
        $actorName = auth()->user()->name ?? null;
        $titleMap = [
            'new_task' => 'New task',
            'task_updated' => 'Task updated',
            'new_comment' => 'New comment',
            'status_changed' => 'Status changed',
        ];

        $title = $titleMap[$type] ?? 'Task update';
        $message = $title;
        $status = $context['status'] ?? null;
        $comment = $context['comment'] ?? null;

        $recipients = User::query()
            ->get()
            ->reject(fn (User $user) => $actorId && (int) $user->id === (int) $actorId);

        $recipients->each(function (User $user) use ($task, $type, $title, $message, $status, $actorName, $comment): void {
            $user->notify(new TaskEventNotification(
                $task,
                $type,
                $title,
                $message,
                $status,
                $actorName,
                $comment
            ));
        });
    }
}
