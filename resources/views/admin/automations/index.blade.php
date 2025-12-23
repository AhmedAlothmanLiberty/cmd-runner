<x-app-layout>
    @once
        <style>
            .automation-table td, .automation-table th {
                padding: 0.85rem 1rem;
                vertical-align: middle;
            }
            .automation-table tbody tr {
                border-left: 4px solid transparent;
                transition: background 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            }
            .automation-table tbody tr:hover {
                background: #f8fafc;
                border-color: #0ea5e9;
                box-shadow: inset 0 1px 0 rgba(0,0,0,0.03), inset 0 -1px 0 rgba(0,0,0,0.03);
            }
            .automation-table .title {
                font-weight: 700;
                color: #0f172a;
            }
            .automation-table .subtext {
                color: #6b7280;
                font-size: 0.85rem;
            }
            .automation-table .pill {
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
            .dropdown .dropdown-item i { color: #94a3b8; }
        </style>
    @endonce

    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Admin</p>
                <h2 class="h4 mb-0">{{ __('Automations') }}</h2>
                <small class="text-muted">View, toggle, run, and monitor automations — newest updates show first.</small>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="{{ route('admin.automations.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New Automation
                </a>
            </div>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="border-bottom bg-light px-3 py-3">
                <form method="GET" action="{{ route('admin.automations.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-6 col-lg-5">
                        <label class="form-label mb-1">Search</label>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Name or command"
                        />
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <a href="{{ route('admin.automations.index') }}" class="btn btn-outline-secondary">
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
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle automation-table">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Command</th>
                            <th class="text-nowrap">Active</th>
                            <th class="text-nowrap">Last run at</th>
                            <th class="text-nowrap">Last status</th>
                            <th class="text-nowrap">Next run</th>
                            <th>Created by</th>
                            <th>Updated by</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($automations as $automation)
                            <tr>
                                <td class="text-muted fw-semibold">
                                    {{ $loop->iteration + ($automations->firstItem() ?? 0) - 1 }}
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="title">{{ $automation->name }}</span>
                                        <span class="subtext">{{ $automation->slug }}</span>
                                    </div>
                                </td>
                                <td>
                                    <code class="text-danger fw-semibold">{{ $automation->command }}</code>
                                </td>
                                <td class="small">
                                    <span class="badge {{ $automation->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $automation->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    {{ $automation->last_run_at ? $automation->last_run_at->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="small">
                                    @if ($automation->last_run_status)
                                        <span class="badge {{ $automation->last_run_status === 'success' ? 'text-bg-success' : 'text-bg-danger' }}">
                                            {{ $automation->last_run_status }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    @php $nextRun = $automation->nextRunAt(); @endphp
                                    @if ($nextRun)
                                        @php
                                            $diffMinutes = now($nextRun->getTimezone())->diffInMinutes($nextRun, false);
                                            $hours = intdiv(max($diffMinutes, 0), 60);
                                            $minutes = max($diffMinutes, 0) % 60;
                                        @endphp
                                        <div>{{ $nextRun->format('Y-m-d H:i') }}</div>
                                        <div class="text-success fw-semibold">in {{ $hours }}h {{ $minutes }}m</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    {{ $automation->created_by ?? '—' }}
                                </td>
                                <td class="small text-muted">
                                    {{ $automation->updated_by ?? '—' }}
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="{{ route('admin.automations.edit', $automation) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                            <li><a class="dropdown-item" href="{{ route('admin.automations.logs', $automation) }}"><i class="bi bi-list-ul me-2"></i>Logs</a></li>
                                            @can('run-automation')
                                                <li>
                                                    <form action="{{ route('admin.automations.run', $automation) }}" method="POST" class="automation-run-form">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="bi bi-play-fill me-2"></i>Run now
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form action="{{ route('admin.automations.toggle', $automation) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="bi {{ $automation->is_active ? 'bi-pause-fill' : 'bi-play-circle' }} me-2"></i>
                                                            {{ $automation->is_active ? 'Disable' : 'Enable' }}
                                                        </button>
                                                    </form>
                                                </li>
                                            @endcan
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No automations yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $automations->links() }}
            </div>
        </div>
    </div>
</x-app-layout>

<x-loader
    data-loader="automation-run-loader"
    trigger-selector=".automation-run-form"
    :duration="3000"
    style="display: none;"
    :text="'RUNNING...'"
/>\n
