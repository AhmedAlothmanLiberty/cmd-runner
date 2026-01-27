<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskRequest;
use App\Http\Requests\Admin\UpdateTaskRequest;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskLabel;
use App\Models\User;
use App\Notifications\TaskEventNotification;
use App\Notifications\TaskReopenedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Task::query()
            ->with(['assignedTo', 'createdBy', 'updatedBy', 'labels'])
            ->standardListFor($user);

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

        if (in_array($status, Task::indexStatusesFor($user), true)) {
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
        $statusOptions = Task::indexStatusLabelsFor($user);

        return view('admin.tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'users' => $users,
            'categories' => $categories,
            'statusOptions' => $statusOptions,
            'perPageOptions' => $perPageOptions,
            'filterAction' => route('admin.tasks.index'),
            'resetUrl' => route('admin.tasks.index'),
        ]);
    }

    public function backlog(Request $request): View
    {
        $query = Task::query()
            ->with(['assignedTo', 'createdBy', 'updatedBy', 'labels'])
            ->backlogList();

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

        if ($status !== null && $status !== '') {
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
        $statusOptions = array_diff_key(Task::statusLabels(), array_flip(Task::deploymentStatuses()));

        return view('admin.tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'users' => $users,
            'categories' => $categories,
            'statusOptions' => $statusOptions,
            'perPageOptions' => $perPageOptions,
            'pageTitle' => 'Backlog',
            'pageSubtitle' => 'Unassigned and backlog tasks.',
            'isBacklog' => true,
            'filterAction' => route('admin.tasks.backlog'),
            'resetUrl' => route('admin.tasks.backlog'),
        ]);
    }

    public function all(Request $request): View
    {
        $query = Task::query()
            ->with(['assignedTo', 'createdBy', 'updatedBy', 'labels']);

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $priority = $request->input('priority');
        $assignedTo = $request->input('assigned_to');
        $categoryId = $request->input('category_id');
        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');
        $dateFrom = $this->parseFilterDate($dateFromInput);
        $dateTo = $this->parseFilterDate($dateToInput);
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

        if ($status !== null && $status !== '') {
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

        if ($dateFrom && $dateTo) {
            $query->whereBetween('updated_at', [
                $dateFrom->copy()->startOfDay(),
                $dateTo->copy()->endOfDay(),
            ]);
        } elseif ($dateFrom) {
            $query->where('updated_at', '>=', $dateFrom->copy()->startOfDay());
        } elseif ($dateTo) {
            $query->where('updated_at', '<=', $dateTo->copy()->endOfDay());
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
            'date_from' => (string) $dateFromInput,
            'date_to' => (string) $dateToInput,
            'per_page' => (string) $perPage,
        ];

        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);
        $categories = TaskLabel::query()->orderBy('name')->get(['id', 'name', 'color']);

        $distinctStatuses = Task::query()->select('status')->distinct()->pluck('status')->filter()->values();
        $statusOptions = Task::statusLabels();
        foreach ($distinctStatuses as $distinctStatus) {
            if (! array_key_exists($distinctStatus, $statusOptions)) {
                $statusOptions[$distinctStatus] = str_replace(['_', '-'], ' ', $distinctStatus);
            }
        }

        return view('admin.tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'users' => $users,
            'categories' => $categories,
            'statusOptions' => $statusOptions,
            'perPageOptions' => $perPageOptions,
            'pageTitle' => 'All Tasks',
            'pageSubtitle' => 'Everything, including backlog and unassigned tasks.',
            'isAllTasks' => true,
            'filterAction' => route('admin.tasks.all'),
            'resetUrl' => route('admin.tasks.all'),
            'exportUrl' => route('admin.tasks.all.export', $request->query()),
        ]);
    }

    public function exportAllCsv(Request $request): StreamedResponse
    {
        $query = Task::query()
            ->with(['assignedTo', 'createdBy', 'updatedBy', 'labels']);

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $priority = $request->input('priority');
        $assignedTo = $request->input('assigned_to');
        $categoryId = $request->input('category_id');
        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');
        $dateFrom = $this->parseFilterDate($dateFromInput);
        $dateTo = $this->parseFilterDate($dateToInput);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
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

        if ($dateFrom && $dateTo) {
            $query->whereBetween('updated_at', [
                $dateFrom->copy()->startOfDay(),
                $dateTo->copy()->endOfDay(),
            ]);
        } elseif ($dateFrom) {
            $query->where('updated_at', '>=', $dateFrom->copy()->startOfDay());
        } elseif ($dateTo) {
            $query->where('updated_at', '<=', $dateTo->copy()->endOfDay());
        }

        $fileName = 'all-tasks-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id',
                'title',
                'status',
                'priority',
                'assigned_to',
                'reporter',
                'due_at',
                'created_at',
                'updated_at',
                'category',
            ]);

            $query
                ->orderBy('id')
                ->chunk(500, function ($tasks) use ($handle): void {
                    foreach ($tasks as $task) {
                        $category = $task->labels->first();

                        fputcsv($handle, [
                            $task->id,
                            $task->title,
                            $task->status,
                            $task->priority,
                            $task->assignedTo?->name,
                            $task->createdBy?->name,
                            $task->due_at?->toDateTimeString(),
                            $task->created_at?->toDateTimeString(),
                            $task->updated_at?->toDateTimeString(),
                            $category?->name,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Task::class);
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        $categories = TaskLabel::query()->orderBy('name')->get();

        $statusOptions = Task::formStatusLabels();

        return view('admin.tasks.create', compact('users', 'categories', 'statusOptions'));
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);
        $task->load(['labels', 'attachments', 'comments.user', 'assignedTo', 'createdBy', 'updatedBy']);

        return view('admin.tasks.show', compact('task'));
    }

    public function previewAttachment(Request $request, Task $task, TaskAttachment $attachment)
    {
        $this->authorize('downloadAttachments', $task);
        $this->assertAttachmentExists($task, $attachment);

        $mimeType = Storage::mimeType($attachment->file_path) ?? $attachment->mime_type;
        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
        ];

        if (! $mimeType || ! in_array(strtolower($mimeType), $allowedMimeTypes, true)) {
            abort(404);
        }

        return Storage::response($attachment->file_path, $attachment->file_name, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function downloadAttachment(Request $request, Task $task, TaskAttachment $attachment)
    {
        $this->authorize('downloadAttachments', $task);
        $this->assertAttachmentExists($task, $attachment);

        return Storage::download($attachment->file_path, $attachment->file_name);
    }

    public function destroyAttachment(Request $request, Task $task, TaskAttachment $attachment): RedirectResponse
    {
        $this->authorize('deleteAttachments', $task);

        if ((int) $attachment->task_id !== (int) $task->id) {
            abort(404);
        }

        if ($attachment->file_path && Storage::exists($attachment->file_path)) {
            Storage::delete($attachment->file_path);
        }

        $attachment->delete();

        return back()->with('status', 'Attachment deleted.');
    }

    public function storeTaskAttachments(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('uploadAttachments', $task);

        $request->validate([
            'attachments' => ['required', 'array'],
            'attachments.*' => ['file', 'max:5120'],
        ]);

        $this->storeAttachments($task, $request);

        return back()->with('status', 'Attachments uploaded.');
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $this->authorize('create', Task::class);
        $data = $request->validated();
        $userId = auth()->id();

        if (! empty($data['assigned_to'])) {
            $this->authorize('assign', Task::make());
        }

        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $data['completed_at'] = $data['status'] === 'completed' ? now() : null;

        $task = Task::create(Arr::except($data, ['category_id', 'comment', 'attachments']));

        $categoryId = $data['category_id'] ?? null;
        $this->syncLabels($task, $categoryId ? [$categoryId] : []);

        if ($request->hasFile('attachments')) {
            $this->authorize('uploadAttachments', $task);
        }
        $this->storeAttachments($task, $request);

        if (trim((string) $request->input('comment')) !== '') {
            $this->authorize('comment', $task);
        }
        $this->storeComment($task, $request->input('comment'), $userId);
        $this->notifyTaskEvent($task, 'new_task');

        $redirectFilters = $this->extractReturnFilters($request);
        return redirect()
            ->route($this->returnRouteName($request), $redirectFilters)
            ->with('status', 'Task created.');
    }

    private function parseFilterDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);
        $task->load(['labels', 'attachments', 'comments.user']);
        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        $categories = TaskLabel::query()->orderBy('name')->get();

        $statusOptions = Task::editStatusLabels($task);
        if (! Task::canUseDeploymentStatuses(request()->user())) {
            $statusOptions = array_intersect_key($statusOptions, array_flip(Task::userChangeableStatuses()));
        }

        return view('admin.tasks.edit', compact('task', 'users', 'categories', 'statusOptions'));
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);
        $data = $request->validated();
        $userId = auth()->id();
        $previousStatus = $task->status;
        $previousAssignedTo = $task->assigned_to;

        $data['updated_by'] = $userId;
        if ($data['status'] === 'completed') {
            $data['completed_at'] = $task->completed_at ?? now();
        } else {
            $data['completed_at'] = null;
        }

        if (($data['status'] ?? null) !== $previousStatus) {
            $this->authorize('changeStatus', $task);

            if (! Task::canUseDeploymentStatuses($request->user())) {
                if (! in_array($data['status'], Task::userChangeableStatuses(), true)) {
                    abort(403);
                }
            }

            if (
                in_array($data['status'], Task::deploymentStatuses(), true) &&
                ! Task::canUseDeploymentStatuses($request->user())
            ) {
                abort(403);
            }
        }

        if (array_key_exists('assigned_to', $data) && ($data['assigned_to'] ?? null) !== $previousAssignedTo) {
            $this->authorize('assign', $task);
        }

        $task->update(Arr::except($data, ['category_id', 'comment', 'attachments']));

        if ($previousStatus !== Task::STATUS_REOPEN && $task->status === Task::STATUS_REOPEN) {
            $this->notifyAssignedUserTaskReopened($task);
        }

        $categoryId = $data['category_id'] ?? null;
        $this->syncLabels($task, $categoryId ? [$categoryId] : []);

        if ($request->hasFile('attachments')) {
            $this->authorize('uploadAttachments', $task);
        }
        $this->storeAttachments($task, $request);

        if (trim((string) $request->input('comment')) !== '') {
            $this->authorize('comment', $task);
        }
        $this->storeComment($task, $request->input('comment'), $userId);
        $this->notifyTaskEvent($task, 'task_updated');

        $redirectFilters = $this->extractReturnFilters($request);
        return redirect()
            ->route($this->returnRouteName($request), $redirectFilters)
            ->with('status', 'Task updated.');
    }

    public function addComment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('comment', $task);

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
        $this->authorize('changeStatus', $task);

        $allowedStatuses = Task::canUseDeploymentStatuses($request->user())
            ? Task::editStatuses($task)
            : Task::userChangeableStatuses();

        $validated = $request->validate([
            'status' => ['required', Rule::in($allowedStatuses)],
        ]);

        if (! Task::canUseDeploymentStatuses($request->user())) {
            if (! in_array($validated['status'], Task::userChangeableStatuses(), true)) {
                abort(403);
            }
        }

        if (
            in_array($validated['status'], Task::deploymentStatuses(), true) &&
            ! Task::canUseDeploymentStatuses($request->user())
        ) {
            abort(403);
        }

        $previousStatus = $task->status;
        $task->status = $validated['status'];
        $task->updated_by = auth()->id();
        $task->completed_at = $task->status === 'completed'
            ? ($task->completed_at ?? now())
            : null;
        $task->save();

        if ($previousStatus !== Task::STATUS_REOPEN && $task->status === Task::STATUS_REOPEN) {
            $this->notifyAssignedUserTaskReopened($task);
        }

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

    private function extractReturnFilters(Request $request): array
    {
        $filters = $request->input('return_filters', []);
        $allowed = ['search', 'status', 'priority', 'assigned_to', 'category_id', 'per_page'];
        $filtered = array_intersect_key((array) $filters, array_flip($allowed));

        return array_filter($filtered, static fn ($value) => $value !== null && $value !== '');
    }

    private function returnRouteName(Request $request): string
    {
        $returnTo = (string) $request->input('return_to', '');

        return match ($returnTo) {
            'backlog' => 'admin.tasks.backlog',
            'all' => 'admin.tasks.all',
            default => 'admin.tasks.index',
        };
    }

    private function notifyAssignedUserTaskReopened(Task $task): void
    {
        if (! $task->assigned_to) {
            return;
        }

        $assignedUser = $task->assignedTo;
        if (! $assignedUser) {
            return;
        }

        $actorId = auth()->id();
        if ($actorId && (int) $assignedUser->id === (int) $actorId) {
            return;
        }

        $assignedUser->notify(new TaskReopenedNotification($task, auth()->user()->name ?? null));
    }

    private function assertAttachmentExists(Task $task, TaskAttachment $attachment): void
    {
        if ((int) $attachment->task_id !== (int) $task->id) {
            abort(404);
        }

        if (! $attachment->file_path || ! Storage::exists($attachment->file_path)) {
            abort(404);
        }
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
