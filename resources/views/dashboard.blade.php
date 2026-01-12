<x-app-layout>
    @once
        <style>
            .dash-hero {
                background: #f8f9fa;
                color: #212529;
                border: 1px solid #e9ecef;
                border-radius: 12px;
            }

            .dash-chip {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.35rem 0.65rem;
                border-radius: 999px;
                background: #ffffff;
                border: 1px solid #e9ecef;
                font-size: 0.85rem;
                color: #495057;
            }

            .metric-card {
                border: 1px solid #e9ecef;
                border-radius: 12px;
                transition: box-shadow 120ms ease;
            }

            .metric-card:hover {
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
            }

            .metric-icon {
                width: 46px;
                height: 46px;
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #eef2f7;
                color: #0d6efd;
            }

            .timeline-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #0d6efd;
                margin-top: 6px;
            }
            .task-section {
                border-radius: 12px;
            }
            .task-table td, .task-table th {
                padding: 0.85rem 1rem;
                vertical-align: middle;
            }
            .task-table tbody tr {
                border-left: 3px solid transparent;
                transition: background 0.15s ease, border-color 0.15s ease;
            }
            .task-table tbody tr:hover {
                background: #f8f9fa;
                border-color: #0d6efd;
            }
            .task-table .title {
                font-weight: 700;
                color: #212529;
            }
            .task-table .subtext {
                color: #6c757d;
                font-size: 0.85rem;
            }
            .task-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.25rem 0.55rem;
                border-radius: 999px;
                border: 1px solid #e9ecef;
                background: #f8f9fa;
                font-size: 0.78rem;
                color: #495057;
                letter-spacing: 0.02em;
            }
            .task-pill.label-pill {
                border: 0;
                color: #fff;
            }
            .task-badge {
                border-radius: 999px;
                padding: 0.25rem 0.6rem;
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            .task-badge-todo { background: #f1f3f5; color: #495057; }
            .task-badge-progress { background: #e7f1ff; color: #0d6efd; }
            .task-badge-done { background: #e6f4ea; color: #198754; }
            .task-badge-completed { background: #e6f4ea; color: #198754; }
            .task-badge-blocked { background: #f8d7da; color: #b02a37; }
            .task-badge-on-hold { background: #fff3cd; color: #997404; }
            .task-badge-deployed-s { background: #e7f1ff; color: #0b5ed7; }
            .task-badge-deployed-p { background: #e9ecef; color: #343a40; }
            .task-badge-reopen { background: #fff3cd; color: #997404; }
            .task-priority-low { background: #eef2ff; color: #3d5afe; }
            .task-priority-medium { background: #fff3cd; color: #997404; }
            .task-priority-high { background: #f8d7da; color: #b02a37; }
        </style>
    @endonce

    @if (! $isSuperAdmin)
        <div class="dash-hero p-2 mb-4 shadow-sm">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="dash-chip mb-2">
                        <i class="bi bi-clipboard-check"></i> My Tasks
                    </div>
                    <h1 class="h3 mb-1">Welcome back, {{ auth()->user()->name }}</h1>
                    <p class="mb-0 text-muted">Your task status overview.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="dash-chip"><i class="bi bi-clock"></i> {{ now()->format('M d, H:i') }}</span>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ($taskWidgets as $widget)
                <div class="col-12 col-sm-6 col-xl-3">
                    <a class="text-decoration-none" href="{{ route('dashboard', array_filter(['status' => $widget['status']])) }}">
                        <div class="metric-card p-3 h-100 bg-white">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted text-uppercase small fw-semibold">{{ $widget['label'] }}</span>
                                <span class="metric-icon">
                                    <i class="bi bi-clipboard"></i>
                                </span>
                            </div>
                            <div class="h4 mb-1">{{ $widget['value'] }}</div>
                            <small class="text-muted">Assigned to you</small>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card shadow-sm border-0 task-section mb-4">
            <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h5 class="mb-0">Tasks</h5>
                    <small class="text-muted">All tasks with filters.</small>
                </div>
            </div>
            <div class="border-bottom bg-light px-3 py-3">
                <form method="GET" action="{{ route('dashboard') }}" class="row g-2 align-items-end">
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label mb-1">Assignee</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">All assigned</option>
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
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            @php $statusOptions = $statusOptions ?? \App\Models\Task::statusLabels(); @endphp
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle task-table">
                    <thead class="table-light">
                        <tr>
                            <th>Task</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Assigned</th>
                            <th class="text-nowrap">Due</th>
                            <th class="text-nowrap">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tasks as $task)
                            @php
                                $statusClass = match ($task->status) {
                                    'in_progress' => 'task-badge-progress',
                                    'done' => 'task-badge-done',
                                    'completed' => 'task-badge-completed',
                                    'blocked' => 'task-badge-blocked',
                                    'on_hold' => 'task-badge-on-hold',
                                    'deployed-s' => 'task-badge-deployed-s',
                                    'deployed-p' => 'task-badge-deployed-p',
                                    'reopen' => 'task-badge-reopen',
                                    default => 'task-badge-todo',
                                };
                                $priorityClass = match ($task->priority) {
                                    'high' => 'task-priority-high',
                                    'medium' => 'task-priority-medium',
                                    default => 'task-priority-low',
                                };
                            @endphp
                            <tr>
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
                                                    <span class="task-pill label-pill" style="background-color: {{ $labelColor }}; color: {{ $labelText }};">
                                                        {{ $label->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="task-badge {{ $statusClass }}">{{ str_replace('_', ' ', $task->status) }}</span>
                                </td>
                                <td>
                                    <span class="task-badge {{ $priorityClass }}">{{ $task->priority }}</span>
                                </td>
                                <td class="small text-muted">
                                    {{ $task->assignedTo?->name ?? 'Unassigned' }}
                                </td>
                                <td class="small text-muted text-nowrap">
                                    {{ $task->due_at ? $task->due_at->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="small text-muted text-nowrap">
                                    {{ $task->updated_at ? $task->updated_at->diffForHumans() : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No tasks yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $tasks->links() }}
            </div>
        </div>
    @else
        <div class="row g-3 mb-4">
            @foreach ($highlights as $item)
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="metric-card p-3 h-100 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted text-uppercase small fw-semibold">{{ $item['label'] }}</span>
                            <span class="metric-icon">
                                <i class="bi bi-{{ $item['icon'] }}"></i>
                            </span>
                        </div>
                        <div class="h4 mb-1">{{ $item['value'] }}</div>
                        <small class="text-muted">Updated just now</small>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card shadow-sm border-0 task-section mb-4">
            <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h5 class="mb-0">Tasks</h5>
                    <small class="text-muted">Latest 3 updated tasks across the team.</small>
                </div>
                <div class="d-flex gap-2">
                    @can('manage-tasks')
                        <a href="{{ route('admin.tasks.create') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> New Task
                        </a>
                    @endcan
                    <a href="{{ route('admin.tasks.index') }}" class="btn btn-outline-secondary btn-sm">
                        See more
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle task-table">
                    <thead class="table-light">
                        <tr>
                            <th>Task</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Assigned</th>
                            <th class="text-nowrap">Due</th>
                            <th class="text-nowrap">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($latestTasks as $task)
                            @php
                                $statusClass = match ($task->status) {
                                    'in_progress' => 'task-badge-progress',
                                    'done' => 'task-badge-done',
                                    'completed' => 'task-badge-completed',
                                    'blocked' => 'task-badge-blocked',
                                    'on_hold' => 'task-badge-on-hold',
                                    'deployed-s' => 'task-badge-deployed-s',
                                    'deployed-p' => 'task-badge-deployed-p',
                                    'reopen' => 'task-badge-reopen',
                                    default => 'task-badge-todo',
                                };
                                $priorityClass = match ($task->priority) {
                                    'high' => 'task-priority-high',
                                    'medium' => 'task-priority-medium',
                                    default => 'task-priority-low',
                                };
                            @endphp
                            <tr>
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
                                                    <span class="task-pill label-pill" style="background-color: {{ $labelColor }}; color: {{ $labelText }};">
                                                        {{ $label->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="task-badge {{ $statusClass }}">{{ str_replace('_', ' ', $task->status) }}</span>
                                </td>
                                <td>
                                    <span class="task-badge {{ $priorityClass }}">{{ $task->priority }}</span>
                                </td>
                                <td class="small text-muted">
                                    {{ $task->assignedTo?->name ?? 'Unassigned' }}
                                </td>
                                <td class="small text-muted text-nowrap">
                                    {{ $task->due_at ? $task->due_at->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="small text-muted text-nowrap">
                                    {{ $task->updated_at ? $task->updated_at->diffForHumans() : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No tasks yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Recent Activity</h5>
                            <small class="text-muted">Latest automation runs and package updates.</small>
                        </div>
                        <span class="badge text-bg-light"><i class="bi bi-wifi"></i> Live</span>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($activity as $item)
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="d-flex gap-3">
                                    <div class="timeline-dot"></div>
                                    <div>
                                        <p class="mb-0 fw-semibold">{{ $item['title'] }}</p>
                                        <small class="text-muted">{{ $item['detail'] }}</small>
                                    </div>
                                </div>
                                <small class="text-muted">{{ $item['time'] }}</small>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-muted py-4">
                                No recent activity yet.
                            </div>
                        @endforelse
                    </div>
                </div>
                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Latest Automations</h5>
                            <small class="text-muted">Most recent (excluding test command).</small>
                        </div>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($latestAutomations as $automation)
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $automation->name }}</p>
                                    <small class="text-muted">{{ $automation->command }}</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge {{ $automation->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $automation->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <div class="small text-muted">{{ $automation->created_at?->diffForHumans() ?? '—' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-muted py-4">
                                No automations yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Online Users</h5>
                            <small class="text-muted">Active in the last 5 minutes.</small>
                        </div>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($onlineUsers as $user)
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $user->name }}</p>
                                    <small class="text-muted">{{ $user->email }}</small>
                                </div>
                                <small class="text-muted">{{ $user->last_seen_at?->diffForHumans() ?? '—' }}</small>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-muted py-4">
                                No users online.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Last Online</h5>
                        <small class="text-muted">Most recent activity.</small>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($lastOnlineUsers as $user)
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $user->name }}</p>
                                    <small class="text-muted">{{ $user->email }}</small>
                                </div>
                                <small class="text-muted">{{ $user->last_seen_at?->diffForHumans() ?? '—' }}</small>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-muted py-4">
                                No recent activity yet.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Latest Updates</h5>
                        <small class="text-muted">Recent user profile changes.</small>
                    </div>
                    {{-- <div class="list-group list-group-flush">
                        @forelse ($latestUserUpdates as $user)
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $user->name }}</p>
                                    <small class="text-muted">{{ $user->email }}</small>
                                </div>
                                <small class="text-muted">{{ $user->updated_at?->diffForHumans() ?? '—' }}</small>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-muted py-4">
                                No updates yet.
                            </div>
                        @endforelse
                    </div> --}}
                </div>

                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Last Package Update</h5>
                            <small class="text-muted">Latest composer run.</small>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($lastPackageUpdate)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $lastPackageUpdate->package }}</p>
                                    <small class="text-muted">Status: {{ $lastPackageUpdate->status }}</small>
                                </div>
                                <span class="badge {{ $lastPackageUpdate->status === 'success' ? 'text-bg-success' : 'text-bg-danger' }}">
                                    {{ $lastPackageUpdate->status }}
                                </span>
                            </div>
                            <div class="small text-muted">
                                Constraint: {{ $lastPackageUpdate->branch }}<br>
                                When: {{ $lastPackageUpdate->created_at?->diffForHumans() ?? '—' }}<br>
                                By: {{ $lastPackageUpdate->triggered_by ?? '—' }}
                            </div>
                        @else
                            <p class="text-muted mb-0">No package updates logged yet.</p>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    {{-- <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Your Profile</h5>
                            <small class="text-muted">Manage your account.</small>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-primary">Edit</a>
                    </div> --}}
                    {{-- <div class="card-body">
                        <div class="mb-3">
                            <p class="text-muted small mb-1">Name</p>
                            <p class="mb-0 fw-semibold">{{ auth()->user()->name }}</p>
                        </div>
                        <div class="mb-3">
                            <p class="text-muted small mb-1">Email</p>
                            <p class="mb-0 fw-semibold">{{ auth()->user()->email }}</p>
                        </div>
                        <div>
                            <p class="text-muted small mb-1">Role</p>
                            <p class="mb-0 fw-semibold">
                                @php $roleName = auth()->user()->roles->first()?->name; @endphp
                                {{ $roleName ? \Illuminate\Support\Str::headline($roleName) : '—' }}
                            </p>
                        </div>
                    </div> --}}
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
