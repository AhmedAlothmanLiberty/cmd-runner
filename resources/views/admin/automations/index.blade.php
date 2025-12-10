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
                            <th>Cron</th>
                            <th>Status</th>
                            <th>Last run</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($automations as $automation)
                            <tr>
                                <td class="fw-semibold">{{ $automation->name }}</td>
                                <td><code>{{ $automation->command }}</code></td>
                                <td><code>{{ $automation->cron_expression }}</code></td>
                                <td>
                                    <span class="badge {{ $automation->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $automation->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    @if ($automation->last_run_status)
                                        <span class="badge {{ $automation->last_run_status === 'success' ? 'text-bg-success' : 'text-bg-danger' }} ms-1">
                                            Last: {{ $automation->last_run_status }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ $automation->last_run_at ? $automation->last_run_at->diffForHumans() : '—' }}
                                    </small>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('admin.automations.edit', $automation) }}" class="btn btn-outline-secondary">Edit</a>
                                        <a href="{{ route('admin.automations.logs', $automation) }}" class="btn btn-outline-secondary">Logs</a>
                                        <form action="{{ route('admin.automations.run', $automation) }}" method="POST">
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
                                <td colspan="6" class="text-center text-muted py-4">No automations yet.</td>
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
