<x-app-layout>
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
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Command</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Last run</th>
                            <th>Next run</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($automations as $automation)
                            <tr>
                                <td class="fw-semibold">
                                    <div class="d-flex flex-column">
                                        <span>{{ $automation->name }}</span>
                                        <small class="text-muted">{{ $automation->slug }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <code>{{ $automation->command }}</code>
                                        <span class="badge text-bg-light text-muted align-self-start">{{ strtoupper($automation->run_via) }}</span>
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
                                        <span class="badge {{ $automation->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $automation->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        @if ($automation->last_run_status)
                                            <span class="badge {{ $automation->last_run_status === 'success' ? 'text-bg-success' : 'text-bg-danger' }}">
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
                                <td>
                                    @php $nextRun = $automation->nextRunAt(); @endphp
                                    <div class="small text-muted">
                                        @if (! $automation->is_active)
                                            <span class="text-secondary">Inactive</span>
                                        @elseif ($nextRun)
                                            <div>{{ $nextRun->diffForHumans() }}</div>
                                            <div class="text-nowrap">{{ $nextRun->format('Y-m-d H:i') }} ({{ $nextRun->getTimezone()->getName() }})</div>
                                        @else
                                            <span class="text-danger">Unable to calculate</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('admin.automations.edit', $automation) }}" class="btn btn-outline-secondary">Edit</a>
                                        <a href="{{ route('admin.automations.logs', $automation) }}" class="btn btn-outline-secondary">Logs</a>
                                        <form action="{{ route('admin.automations.run', $automation) }}" method="POST" class="automation-run-form">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary">Run now</button>
                                        </form>
                                        <form action="{{ route('admin.automations.toggle', $automation) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-warning">{{ $automation->is_active ? 'Disable' : 'Enable' }}</button>
                                        </form>
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
