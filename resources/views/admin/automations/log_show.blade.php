<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <h2 class="h4 mb-0">{{ $log->automation->name }} — Log Detail</h2>
                <small class="text-muted">Started {{ $log->started_at?->toDateTimeString() }}</small>
            </div>
            <a href="{{ route('admin.automations.logs', $log->automation) }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to logs
            </a>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Metadata</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $log->status === 'success' ? 'text-bg-success' : ($log->status === 'running' ? 'text-bg-warning' : 'text-bg-danger') }}">
                                {{ ucfirst($log->status) }}
                            </span>
                        </dd>

                        <dt class="col-sm-4">Started</dt>
                        <dd class="col-sm-8">{{ $log->started_at?->toDateTimeString() }}</dd>

                        <dt class="col-sm-4">Finished</dt>
                        <dd class="col-sm-8">{{ $log->finished_at?->toDateTimeString() ?? '—' }}</dd>

                        <dt class="col-sm-4">Runtime</dt>
                        <dd class="col-sm-8">{{ $log->runtime_ms }} ms</dd>

                        <dt class="col-sm-4">Triggered By</dt>
                        <dd class="col-sm-8">{{ $log->triggered_by ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Output</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded small" style="max-height: 320px; overflow:auto;">{{ $log->output ?? 'No output' }}</pre>
                </div>
            </div>

            @if ($log->error)
                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-danger">Error</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded small text-danger" style="max-height: 320px; overflow:auto;">{{ $log->error }}</pre>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
