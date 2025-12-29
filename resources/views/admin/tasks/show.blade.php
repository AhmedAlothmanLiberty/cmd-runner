<x-app-layout>
    @once
        <style>
            .ops-shell {
                background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 60%, #fdf2f8 100%);
                border-radius: 18px;
                padding: 1.5rem;
            }
            .ops-panel {
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                background: #fff;
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            }
            .ops-kpi {
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                background: #f8fafc;
                padding: 0.75rem 1rem;
            }
            .ops-kpi strong {
                color: #0f172a;
            }
            .ops-title {
                font-weight: 700;
                color: #0f172a;
            }
            .ops-subtext {
                color: #64748b;
                font-size: 0.9rem;
            }
            .ops-section-title {
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #64748b;
                font-size: 0.72rem;
                font-weight: 700;
            }
            .ops-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.35rem 0.7rem;
                border-radius: 999px;
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            .ops-pill.todo { background: #bae6fd; color: #075985; }
            .ops-pill.in_progress { background: #fef9c3; color: #854d0e; }
            .ops-pill.done { background: #bbf7d0; color: #166534; }
            .ops-pill.blocked { background: #fecaca; color: #b91c1c; }
            .ops-pill.deployed-s { background: #e0f2fe; color: #0c4a6e; }
            .ops-pill.deployed-p { background: #e2e8f0; color: #1e293b; }
            .ops-pill.reopen { background: #fef3c7; color: #92400e; }
            .ops-rail {
                border-left: 2px dashed #e2e8f0;
                padding-left: 1.25rem;
            }
        </style>
    @endonce

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

    <div class="ops-shell">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="ops-section-title mb-1">Task Overview</div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="ops-title">{{ $task->title }}</div>
                    <span class="ops-pill {{ $task->status }}">{{ str_replace('_', ' ', $task->status) }}</span>
                </div>
                <div class="ops-subtext">Assigned to {{ $task->assignedTo?->name ?? 'Unassigned' }} · Reporter {{ $task->createdBy?->name ?? '—' }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="ops-pill" style="background:#e0e7ff;color:#3730a3;">{{ $task->priority }}</span>
                @if ($task->labels->isNotEmpty())
                    @foreach ($task->labels as $label)
                        @php
                            $labelColor = $label->color ?? '#e2e8f0';
                            $labelText = strtoupper($labelColor) === '#F59E0B' ? '#0f172a' : '#fff';
                        @endphp
                        <span class="ops-pill" style="background: {{ $labelColor }}; color: {{ $labelText }};">
                            {{ $label->name }}
                        </span>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="ops-panel p-4 mb-3">
                    <div class="ops-section-title mb-3">Mission Brief</div>
                    <p class="mb-0">{{ $task->description ?: 'No description.' }}</p>
                </div>

                <div class="ops-panel p-4">
                    <div class="ops-section-title mb-3">Comments</div>
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

            <div class="col-12 col-lg-4">
                <div class="ops-panel p-4 h-100 ops-rail">
                    <div class="ops-section-title mb-3">Mission Status</div>
                    <div class="d-flex flex-column gap-3">
                        <div class="ops-kpi">
                            <div class="text-muted small">Last update</div>
                            <strong>{{ $task->updated_at?->diffForHumans() ?? '—' }}</strong>
                        </div>
                        <div class="ops-kpi">
                            <div class="text-muted small">Due</div>
                            <strong>{{ $task->due_at ? $task->due_at->format('Y-m-d H:i') : '—' }}</strong>
                        </div>
                        <div class="ops-kpi">
                            <div class="text-muted small">Created</div>
                            <strong>{{ $task->created_at?->format('Y-m-d H:i') ?? '—' }}</strong>
                        </div>
                        <div class="ops-kpi">
                            <div class="text-muted small">Updated by</div>
                            <strong>{{ $task->updatedBy?->name ?? '—' }}</strong>
                        </div>
                        <div class="ops-kpi">
                            <div class="text-muted small">Attachments</div>
                            <strong>{{ $task->attachments->count() }} files</strong>
                        </div>
                        <div class="ops-kpi">
                            <div class="text-muted small">Comments</div>
                            <strong>{{ $task->comments->count() }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-12">
                <div class="ops-panel p-4">
                    <div class="ops-section-title mb-3">Attachments</div>
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
