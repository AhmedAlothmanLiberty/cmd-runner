<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Admin</p>
                <h2 class="h4 mb-0">{{ $automation->name }} — Logs</h2>
                <small class="text-muted">Run history and output.</small>
            </div>
            <a href="{{ route('admin.automations.index') }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to automations
            </a>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Started</th>
                            <th>Finished</th>
                            <th>Status</th>
                            <th>Runtime</th>
                            <th>Triggered By</th>
                            <th class="text-end">Output</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ $log->started_at?->toDateTimeString() }}</td>
                                <td>{{ $log->finished_at?->toDateTimeString() ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $log->status === 'success' ? 'text-bg-success' : ($log->status === 'running' ? 'text-bg-warning' : 'text-bg-danger') }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td>{{ $log->runtime_ms }} ms</td>
                                <td><small class="text-muted">{{ $log->triggered_by ?? '—' }}</small></td>
                                <td class="text-end">
                                    <a href="{{ route('admin.automations.log.show', $log) }}" class="btn btn-sm btn-outline-secondary">View Output</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No logs yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
