<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Admin</p>
                <h2 class="h4 mb-0">{{ $task->title }}</h2>
                <small class="text-muted">Task details and activity.</small>
            </div>
            <div class="d-flex gap-2 mt-3 mt-md-0">
                <a href="{{ route('admin.tasks.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to tasks
                </a>
                @can('update', $task)
                    <a href="{{ route('admin.tasks.edit', $task) }}" class="btn btn-primary">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge {{ $task->status === 'done' ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ str_replace('_', ' ', $task->status) }}
                        </span>
                        <span class="badge text-bg-light text-uppercase">{{ $task->priority }}</span>
                        @if ($task->labels->isNotEmpty())
                            @foreach ($task->labels as $label)
                                <span class="badge text-bg-light">{{ $label->name }}</span>
                            @endforeach
                        @endif
                    </div>

                    <h6 class="text-uppercase text-muted small fw-semibold">Description</h6>
                    <p class="mb-0">{{ $task->description ?: 'No description.' }}</p>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Comments</h6>
                </div>
                <div class="card-body">
                    @auth
                        <form action="{{ route('admin.tasks.comments.store', $task) }}" method="POST" class="mb-3">
                            @csrf
                            <div class="mb-2">
                                <textarea name="comment" rows="3" class="form-control" placeholder="Add a comment..."></textarea>
                                @error('comment')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Add comment</button>
                        </form>
                    @endauth

                    @if ($task->comments->isEmpty())
                        <div class="text-muted">No comments yet.</div>
                    @else
                        <div class="d-flex flex-column gap-3">
                            @foreach ($task->comments as $comment)
                                <div class="border rounded p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-semibold">{{ $comment->user?->name ?? 'System' }}</span>
                                        <small class="text-muted">{{ $comment->created_at->diffForHumans() }}</small>
                                    </div>
                                    <div class="text-muted">{{ $comment->body }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Assigned to</div>
                        <div class="fw-semibold">{{ $task->assignedTo?->name ?? 'Unassigned' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Reporter</div>
                        <div class="fw-semibold">{{ $task->createdBy?->name ?? '—' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Due</div>
                        <div class="fw-semibold">{{ $task->due_at ? $task->due_at->format('Y-m-d H:i') : '—' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Created</div>
                        <div class="fw-semibold">{{ $task->created_at?->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Updated</div>
                        <div class="fw-semibold">{{ $task->updated_at?->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-muted small">Updated by</div>
                        <div class="fw-semibold">{{ $task->updatedBy?->name ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Attachments</h6>
                </div>
                <div class="card-body">
                    @if ($task->attachments->isEmpty())
                        <div class="text-muted">No attachments yet.</div>
                    @else
                        <div class="list-group">
                            @foreach ($task->attachments as $attachment)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">{{ $attachment->file_name }}</div>
                                        <small class="text-muted">
                                            {{ $attachment->created_at->format('Y-m-d H:i') }}
                                            @if ($attachment->file_size)
                                                · {{ number_format($attachment->file_size / 1024, 1) }} KB
                                            @endif
                                        </small>
                                    </div>
                                    <small class="text-muted">{{ $attachment->mime_type ?? 'file' }}</small>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
