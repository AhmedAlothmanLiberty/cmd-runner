<x-app-layout>
    @once
        <style>
            .task-table td, .task-table th {
                padding: 0.85rem 1rem;
                vertical-align: middle;
            }
            .task-table tbody tr {
                border-left: 4px solid transparent;
                transition: background 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            }
            .task-table tbody tr:hover {
                background: #f8fafc;
                border-color: #0ea5e9;
                box-shadow: inset 0 1px 0 rgba(0,0,0,0.03), inset 0 -1px 0 rgba(0,0,0,0.03);
            }
            .task-table .title {
                font-weight: 700;
                color: #0f172a;
            }
            .task-table .subtext {
                color: #6b7280;
                font-size: 0.85rem;
            }
            .task-table .pill {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.25rem 0.55rem;
                border-radius: 999px;
                border: 1px solid #e5e7eb;
                background: #f8fafc;
                font-size: 0.78rem;
                color: #334155;
                letter-spacing: 0.02em;
            }
            .label-pill {
                border: 0;
                color: #fff;
            }
            .task-table-wrapper {
                min-height: 380px;
            }
            .status-text {
                display: inline-flex;
                align-items: center;
                padding: 0.25rem 0.6rem;
                border-radius: 6px;
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            .status-cell--todo .status-text {
                background: #bae6fd;
                color: #075985;
            }
            .status-cell--in_progress .status-text {
                background: #fef9c3;
                color: #854d0e;
            }
            .status-cell--done .status-text {
                background: #bbf7d0;
                color: #166534;
            }
            .status-cell--blocked .status-text {
                background: #fecaca;
                color: #b91c1c;
            }
            .status-cell--deployed-s .status-text {
                background: #e0f2fe;
                color: #0c4a6e;
            }
            .status-cell--deployed-p .status-text {
                background: #e2e8f0;
                color: #1e293b;
            }
            .status-cell--reopen .status-text {
                background: #fef3c7;
                color: #92400e;
            }
            .badge-soft {
                border-radius: 999px;
                padding: 0.25rem 0.6rem;
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            .badge-soft-todo { background: #f1f5f9; color: #475569; }
            .badge-soft-progress { background: #e0f2fe; color: #0369a1; }
            .badge-soft-done { background: #dcfce7; color: #166534; }
            .badge-soft-blocked { background: #fee2e2; color: #b91c1c; }
            .badge-soft-low { background: #eef2ff; color: #3730a3; }
            .badge-soft-medium { background: #fef9c3; color: #a16207; }
            .badge-soft-high { background: #ffe4e6; color: #be123c; }
            .dropdown .dropdown-item i { color: #94a3b8; }
        </style>
    @endonce

    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <h2 class="h4 mb-0">Tasks</h2>
                <small class="text-muted">Track work items and keep them moving.</small>
            </div>
            @can('manage-tasks')
                <div class="mt-3 mt-md-0">
                    <a href="{{ route('admin.tasks.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> New Task
                    </a>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="border-bottom bg-light px-3 py-3">
                <form method="GET" action="{{ route('admin.tasks.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-5 col-lg-4">
                        <label class="form-label mb-1">Search</label>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Title or description"
                        />
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="todo" @selected(($filters['status'] ?? '') === 'todo')>To do</option>
                            <option value="in_progress" @selected(($filters['status'] ?? '') === 'in_progress')>In progress</option>
                            <option value="done" @selected(($filters['status'] ?? '') === 'done')>Done</option>
                            <option value="blocked" @selected(($filters['status'] ?? '') === 'blocked')>Blocked</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label mb-1">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="">All</option>
                            <option value="low" @selected(($filters['priority'] ?? '') === 'low')>Low</option>
                            <option value="medium" @selected(($filters['priority'] ?? '') === 'medium')>Medium</option>
                            <option value="high" @selected(($filters['priority'] ?? '') === 'high')>High</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label mb-1">Assigned to</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Anyone</option>
                            @foreach ($users as $user)
                                <option
                                    value="{{ $user->id }}"
                                    @selected((string) ($filters['assigned_to'] ?? '') === (string) $user->id)
                                >
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <a href="{{ route('admin.tasks.index') }}" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            @if (session('status'))
                <div class="alert alert-info m-3 mb-0">
                    {{ session('status') }}
                </div>
            @endif

            <div class="table-responsive task-table-wrapper">
                <table class="table table-hover mb-0 align-middle task-table">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Task</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Assigned</th>
                            <th>Reporter</th>
                            <th class="text-nowrap">Due</th>
                            <th class="text-nowrap">Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tasks as $task)
                            <tr>
                                <td class="text-muted fw-semibold">
                                    {{ $loop->iteration + ($tasks->firstItem() ?? 0) - 1 }}
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <a class="title text-decoration-none" href="{{ route('admin.tasks.show', $task) }}">
                                            {{ $task->title }}
                                        </a>
                                        @if ($task->description)
                                            <span class="subtext">{{ \Illuminate\Support\Str::limit($task->description, 80) }}</span>
                                        @endif
                                        @if ($task->labels->isNotEmpty())
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                @foreach ($task->labels as $label)
                                                    @php
                                                        $labelColor = $label->color ?? '#e2e8f0';
                                                        $labelText = strtoupper($labelColor) === '#F59E0B' ? '#0f172a' : '#fff';
                                                    @endphp
                                                    <span class="pill label-pill" style="background-color: {{ $labelColor }}; color: {{ $labelText }};">
                                                        {{ $label->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="status-cell status-cell--{{ $task->status }}">
                                    <span class="status-text">
                                        {{ str_replace(['_', '-'], ' ', $task->status) }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $priorityClass = match ($task->priority) {
                                            'high' => 'badge-soft-high',
                                            'medium' => 'badge-soft-medium',
                                            default => 'badge-soft-low',
                                        };
                                    @endphp
                                    <span class="badge-soft {{ $priorityClass }}">{{ $task->priority }}</span>
                                </td>
                                <td class="small text-muted">
                                    {{ $task->assignedTo?->name ?? 'Unassigned' }}
                                </td>
                                <td class="small text-muted">
                                    {{ $task->createdBy?->name ?? '—' }}
                                </td>
                                <td class="small text-muted text-nowrap">
                                    {{ $task->due_at ? $task->due_at->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="small text-muted text-nowrap">
                                    {{ $task->updated_at ? $task->updated_at->diffForHumans() : '—' }}
                                </td>
                                <td class="text-end text-nowrap">
                                    @can('update', $task)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="{{ route('admin.tasks.edit', $task) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                                @can('delete', $task)
                                                    <li>
                                                        <form action="{{ route('admin.tasks.destroy', $task) }}" method="POST" onsubmit="return confirm('Delete this task?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash me-2"></i>Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endcan
                                            </ul>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No tasks yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-3">
                {{ $tasks->links() }}
            </div>
        </div>
    </div>

</x-app-layout>
