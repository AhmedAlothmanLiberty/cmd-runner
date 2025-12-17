<x-app-layout>
    @once
        <style>
            .package-update-output {
                white-space: pre-wrap;
                word-break: break-word;
            }
        </style>
    @endonce

    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Admin</p>
                <h2 class="h4 mb-0">{{ __('Package Updates') }}</h2>
                <small class="text-muted">Run controlled Composer updates and review audit logs.</small>
            </div>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Run Update</h5>
                    <small class="text-muted">Updates a package using `composer require`.</small>
                </div>
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-info">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.package-updates.run') }}" class="d-grid gap-2">
                        @csrf

                        <div>
                            <label for="package" class="form-label">Package</label>
                            <input
                                id="package"
                                name="package"
                                class="form-control @error('package') is-invalid @enderror"
                                value="{{ old('package', 'liberty-cmd/reports') }}"
                                placeholder="vendor/package"
                                required
                            />
                            @error('package')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button class="btn btn-primary" onclick="return confirm('Run composer update for this package?');">
                            <i class="bi bi-arrow-repeat me-1"></i> Run update
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-0">Latest Logs</h5>
                        <small class="text-muted">Most recent update runs.</small>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Package</th>
                                    <th>Constraint</th>
                                    <th>Env</th>
                                    <th>Status</th>
                                    <th>By</th>
                                    <th class="text-nowrap">At</th>
                                    <th class="text-end">Output</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($logs as $log)
                                    <tr>
                                        <td class="text-muted">{{ $log->id }}</td>
                                        <td class="fw-semibold">{{ $log->package }}</td>
                                        <td><code class="text-danger fw-semibold">{{ $log->branch }}</code></td>
                                        <td><span class="badge text-bg-secondary">{{ strtoupper($log->env) }}</span></td>
                                        <td>
                                            <span class="badge {{ $log->status === 'success' ? 'text-bg-success' : 'text-bg-danger' }}">
                                                {{ $log->status }}
                                            </span>
                                        </td>
                                        <td class="text-muted">{{ $log->triggered_by ?: '—' }}</td>
                                        <td class="text-muted small">{{ $log->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="text-end">
                                            @if ($log->output)
                                                <button
                                                    class="btn btn-sm btn-outline-secondary"
                                                    type="button"
                                                    data-output-toggle="{{ $log->id }}"
                                                >
                                                    <i class="bi bi-terminal me-1"></i> View
                                                </button>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($log->output)
                                        <tr id="output-row-{{ $log->id }}" class="bg-light d-none">
                                            <td colspan="8" class="p-0 border-top-0">
                                                <pre class="m-0 p-3 small package-update-output" style="max-height: 260px; overflow: auto;">{{ $log->output }}</pre>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No logs yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-white">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-output-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const logId = button.getAttribute('data-output-toggle');
                    const row = document.getElementById(`output-row-${logId}`);
                    if (!row) return;

                    const willShow = row.classList.contains('d-none');
                    row.classList.toggle('d-none');
                    button.innerHTML = `<i class="bi bi-terminal me-1"></i> ${willShow ? 'Hide' : 'View'}`;
                });
            });
        });
    </script>
@endpush
