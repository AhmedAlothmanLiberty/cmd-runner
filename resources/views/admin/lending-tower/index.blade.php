<x-app-layout>
    @once
        <style>
            .lt-stat-card {
                border: 1px solid #e9ecef;
                border-radius: 14px;
            }

            .lt-preview-table th,
            .lt-preview-table td {
                white-space: nowrap;
                vertical-align: middle;
            }
        </style>
    @endonce

    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100 gap-2">
            <div>
                <h2 class="h4 mb-0">Lending Tower Reports</h2>
                <small class="text-muted">Preview recent SMS report data and download the latest CSV without using the server file system directly.</small>
            </div>
            @if ($latestFile)
                <a href="{{ route('admin.lending-tower.reports.download', ['file' => $latestFile['name']]) }}" class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Download latest CSV
                </a>
            @endif
        </div>
    </x-slot>

    @if (! $latestFile)
        <div class="alert alert-info mb-0">
            No Lending Tower report CSV files were found in <code>EE/</code>.
        </div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 lt-stat-card h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small fw-semibold mb-2">Latest file</div>
                        <div class="fw-semibold">{{ $latestFile['name'] }}</div>
                        <div class="small text-muted mt-1">Updated {{ $latestFile['modified_at']->diffForHumans() }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 lt-stat-card h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small fw-semibold mb-2">Rows in file</div>
                        <div class="h4 mb-0">{{ number_format($rowCount) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm border-0 lt-stat-card h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small fw-semibold mb-2">File size</div>
                        <div class="h4 mb-0">{{ $latestFile['size_human'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Preview</h5>
                            <small class="text-muted">Showing the first {{ count($previewRows) }} rows from {{ $latestFile['name'] }}.</small>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle lt-preview-table">
                                <thead class="table-light">
                                    <tr>
                                        @foreach ($previewHeader as $column)
                                            <th>{{ $column }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($previewRows as $row)
                                        <tr>
                                            @foreach ($previewHeader as $column)
                                                <td>{{ $row[$column] ?? '' }}</td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ max(count($previewHeader), 1) }}" class="text-center text-muted py-4">No preview rows available.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Available files</h5>
                        <small class="text-muted">Download any generated Lending Tower report.</small>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($files as $file)
                            <div class="list-group-item d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $file['name'] }}</div>
                                    <small class="text-muted">
                                        {{ $file['size_human'] }} · {{ $file['modified_at']->format('Y-m-d H:i') }}
                                    </small>
                                </div>
                                <a href="{{ route('admin.lending-tower.reports.download', ['file' => $file['name']]) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download me-1"></i> Download
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
