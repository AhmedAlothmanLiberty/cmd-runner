<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <h2 class="h4 mb-0">EasyEngine Job #{{ $job->id }}</h2>
                <small class="text-muted">Created {{ $job->created_at?->format('Y-m-d H:i') ?? '-' }}</small>
            </div>
            <a href="{{ route('admin.easyengine-jobs.index') }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to jobs
            </a>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Job Details</h5>
                </div>
                <div class="card-body">
                    @php
                        $statusClass = match ($job->status) {
                            'uploaded' => 'text-bg-secondary',
                            'converted' => 'text-bg-info',
                            'uploaded_s3' => 'text-bg-success',
                            'failed' => 'text-bg-danger',
                            default => 'text-bg-light',
                        };
                    @endphp
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge {{ $statusClass }}">{{ $job->status }}</span></dd>

                        <dt class="col-sm-4">User</dt>
                        <dd class="col-sm-8">
                            <div>{{ $job->user?->name ?? '-' }}</div>
                            @if ($job->user?->email)
                                <div class="small text-muted">{{ $job->user->email }}</div>
                            @endif
                        </dd>

                        <dt class="col-sm-4">State</dt>
                        <dd class="col-sm-8">{{ $job->state ?? '-' }}</dd>

                        <dt class="col-sm-4">Drop Date</dt>
                        <dd class="col-sm-8">{{ $job->drop_date?->format('Y-m-d') ?? '-' }}</dd>

                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8">{{ $job->created_at?->format('Y-m-d H:i') ?? '-' }}</dd>

                        <dt class="col-sm-4">Updated</dt>
                        <dd class="col-sm-8">{{ $job->updated_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Files</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Original</dt>
                        <dd class="col-sm-8">{{ $job->original_filename ?? '-' }}</dd>

                        <dt class="col-sm-4">CSV Path</dt>
                        <dd class="col-sm-8"><code>{{ $job->csv_path ?? '-' }}</code></dd>

                        <dt class="col-sm-4">CSV SHA256</dt>
                        <dd class="col-sm-8"><code>{{ $job->csv_sha256 ?? '-' }}</code></dd>

                        <dt class="col-sm-4">Parquet Path</dt>
                        <dd class="col-sm-8"><code>{{ $job->parquet_path ?? '-' }}</code></dd>

                        <dt class="col-sm-4">Parquet SHA256</dt>
                        <dd class="col-sm-8"><code>{{ $job->parquet_sha256 ?? '-' }}</code></dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0">S3 Destination</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">S3 Bucket</dt>
                        <dd class="col-sm-8">{{ $job->s3_bucket ?? '-' }}</dd>

                        <dt class="col-sm-4">S3 Key</dt>
                        <dd class="col-sm-8"><code>{{ $job->s3_key ?? '-' }}</code></dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Meta</h5>
                </div>
                <div class="card-body">
                    @if ($job->meta)
                        <pre class="bg-light p-3 rounded small mb-0">{{ json_encode($job->meta, JSON_PRETTY_PRINT) }}</pre>
                    @else
                        <div class="text-muted">-</div>
                    @endif
                </div>
            </div>
        </div>

        @if ($job->error)
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-danger">Error</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded small text-danger mb-0">{{ $job->error }}</pre>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
