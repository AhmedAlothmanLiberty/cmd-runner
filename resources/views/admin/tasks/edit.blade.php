<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Admin</p>
                <h2 class="h4 mb-0">Edit Task</h2>
                <small class="text-muted">Update task details and status.</small>
            </div>
            <a href="{{ route('admin.tasks.index') }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to tasks
            </a>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0 mx-auto" style="max-width: 900px;">
        <div class="card-body">
            <form action="{{ route('admin.tasks.update', $task) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('admin.tasks._form')
                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.tasks.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
            <hr class="my-4">
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <h6 class="text-uppercase text-muted small fw-semibold mb-3">Comments</h6>
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
                <div class="col-12 col-lg-6">
                    <h6 class="text-uppercase text-muted small fw-semibold mb-3">Attachments</h6>
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
