<x-app-layout>
    @once
        <style>
            .automation-table td, .automation-table th {
                padding: 0.75rem 1rem;
                vertical-align: middle;
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
                padding: 0.2rem 0.5rem;
                border-radius: 999px;
                border: 1px solid #e5e7eb;
                background: #f8fafc;
                font-size: 0.8rem;
                color: #4b5563;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            .automation-table .badge-soft {
                border-radius: 999px;
                padding: 0.25rem 0.6rem;
                font-size: 0.8rem;
                font-weight: 700;
            }
            .badge-soft-success { background: #e8f5e9; color: #166534; }
            .badge-soft-muted { background: #f1f5f9; color: #475569; }
            .badge-soft-danger { background: #fef2f2; color: #b91c1c; }
            .badge-soft-primary { background: #e0ebff; color: #1d4ed8; }
            .action-stack {
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-end;
                gap: 0.35rem;
            }
            .btn-ghost {
                border: 1px solid #e2e8f0;
                background: #fff;
                color: #334155;
            }
            .btn-ghost:hover {
                border-color: #cbd5e1;
                background: #f8fafc;
            }
            .btn-run {
                background: #2563eb;
                border-color: #1d4ed8;
                color: #fff;
            }
            .btn-run:hover { background: #1d4ed8; color: #fff; }
            .btn-toggle-active {
                border: 1px solid #f59e0b;
                color: #b45309;
                background: #fffbeb;
            }
            .btn-toggle-inactive {
                border: 1px solid #22c55e;
                color: #166534;
                background: #ecfdf3;
            }
        </style>
    @endonce

    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Admin</p>
                <h2 class="h4 mb-0">{{ __('Automations') }}</h2>
                <small class="text-muted">View, toggle, run, and monitor automations.</small>
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
            @if (session('status'))
                <div class="alert alert-info m-3 mb-0">
                    {{ session('status') }}
                </div>
        @endif
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle automation-table">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Command</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Last run</th>
                            <th class="text-nowrap">Next run</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($automations as $automation)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="title">{{ $automation->name }}</span>
                                        <span class="subtext">{{ $automation->slug }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <code class="text-danger fw-semibold">{{ $automation->command }}</code>
                                        <span class="pill">{{ strtoupper($automation->run_via) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column small">
                                        <code>{{ $automation->cron_expression }}</code>
                                        @if ($automation->daily_time)
                                            <span class="text-muted">Daily at {{ $automation->daily_time }}</span>
                                        @endif
                                        <span class="text-muted">{{ $automation->timezone ?? ('App TZ: ' . config('app.timezone')) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge-soft {{ $automation->is_active ? 'badge-soft-success' : 'badge-soft-muted' }}">
                                            {{ $automation->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        @if ($automation->last_run_status)
                                            <span class="badge-soft {{ $automation->last_run_status === 'success' ? 'badge-soft-success' : 'badge-soft-danger' }}">
                                                Last: {{ $automation->last_run_status }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-muted">
                                        {{ $automation->last_run_at ? $automation->last_run_at->diffForHumans() : '—' }}
                                        @if ($automation->last_runtime_ms)
                                            <div>Runtime: {{ $automation->last_runtime_ms }} ms</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    @php $nextRun = $automation->nextRunAt(); @endphp
                                    <div class="d-flex flex-column align-items-start gap-1">
                                        @if (! $automation->is_active)
                                            <span class="badge-soft badge-soft-muted">Inactive</span>
                                        @elseif ($nextRun)
                                            <span class="badge-soft badge-soft-primary">{{ $nextRun->diffForHumans() }}</span>
                                            <small class="text-muted">{{ $nextRun->format('Y-m-d H:i') }} ({{ $nextRun->getTimezone()->getName() }})</small>
                                        @else
                                            <span class="badge-soft badge-soft-danger">Needs schedule</span>
                                            <small class="text-muted">Check cron / daily time</small>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="action-stack">
                                        <a href="{{ route('admin.automations.edit', $automation) }}" class="btn btn-sm btn-ghost">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                        <a href="{{ route('admin.automations.logs', $automation) }}" class="btn btn-sm btn-ghost">
                                            <i class="bi bi-list-ul me-1"></i>Logs
                                        </a>
                                        @can('run-automation')
                                            <form action="{{ route('admin.automations.run', $automation) }}" method="POST" class="automation-run-form d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-run">
                                                    <i class="bi bi-play-fill me-1"></i>Run now
                                                </button>
                                            </form>
                                        @endcan
                                        @can('run-automation')
                                            <form action="{{ route('admin.automations.toggle', $automation) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $automation->is_active ? 'btn-toggle-active' : 'btn-toggle-inactive' }}">
                                                    <i class="bi {{ $automation->is_active ? 'bi-pause-fill' : 'bi-play-circle' }} me-1"></i>
                                                    {{ $automation->is_active ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No automations yet.</td>
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
