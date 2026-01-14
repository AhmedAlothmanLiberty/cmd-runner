<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <h2 class="h4 mb-0">S3 Upload Jobs</h2>
                <small class="text-muted">Track EasyEngine S3 upload pipeline activity.</small>
            </div>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="border-bottom bg-light px-3 py-3">
                <form method="GET" action="{{ route('admin.s3-upload-jobs.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1">Search</label>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Name, path, uploader, IP"
                        />
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            @foreach ($statusOptions as $option)
                                <option value="{{ $option }}" @selected(($filters['status'] ?? '') === $option)>
                                    {{ ucfirst(str_replace('_', ' ', $option)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">State</label>
                        <input
                            type="text"
                            name="state"
                            class="form-control"
                            value="{{ $filters['state'] ?? '' }}"
                            placeholder="CA"
                        />
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        <a href="{{ route('admin.s3-upload-jobs.index') }}" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Original Name</th>
                            <th>State</th>
                            <th>Drop Date</th>
                            <th>Status</th>
                            <th>Uploader</th>
                            <th>Size</th>
                            <th class="text-nowrap">Created</th>
                            <th class="text-end">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jobs as $job)
                            @php
                                $statusClass = match ($job->status) {
                                    'queued' => 'text-bg-secondary',
                                    'pending' => 'text-bg-warning',
                                    'processing' => 'text-bg-info',
                                    'uploaded' => 'text-bg-success',
                                    'failed' => 'text-bg-danger',
                                    default => 'text-bg-light',
                                };
                            @endphp
                            <tr>
                                <td class="text-muted">{{ $job->id }}</td>
                                <td class="fw-semibold">{{ $job->original_name }}</td>
                                <td class="text-muted">{{ $job->state ?? '-' }}</td>
                                <td class="text-muted">{{ $job->drop_date?->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $statusClass }}">{{ $job->status }}</span>
                                </td>
                                <td class="text-muted">{{ $job->uploader ?? '-' }}</td>
                                <td class="text-muted">
                                    {{ $job->size ? number_format($job->size) . ' bytes' : '-' }}
                                </td>
                                <td class="text-muted small">{{ $job->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.s3-upload-jobs.show', $job) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No upload jobs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white">
                {{ $jobs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
