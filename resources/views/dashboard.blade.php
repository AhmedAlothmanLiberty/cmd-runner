<x-app-layout>
    @once
        <style>
            .dash-hero {
                background: radial-gradient(circle at 20% 20%, #0099ff, #0d6efd 45%), linear-gradient(135deg, #0d6efd 0%, #6f42c1 70%);
                color: #fff;
                border-radius: 16px;
            }

            .dash-chip {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.35rem 0.65rem;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
                font-size: 0.85rem;
            }

            .metric-card {
                border: 1px solid #e5e7eb;
                border-radius: 14px;
                transition: transform 120ms ease, box-shadow 120ms ease;
            }

            .metric-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            }

            .metric-icon {
                width: 46px;
                height: 46px;
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #e0ebff;
                color: #1d4ed8;
            }

            .timeline-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #0d6efd;
                margin-top: 6px;
            }
        </style>
    @endonce

    <div class="dash-hero p-4 p-lg-5 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <div class="dash-chip mb-2">
                    <i class="bi bi-activity"></i> Operations Pulse
                </div>
                <h1 class="h3 mb-1">Welcome back, {{ auth()->user()->name }}</h1>
                <p class="mb-0 text-white-50">Live view of automations and package updates.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="dash-chip"><i class="bi bi-shield-check"></i> All systems</span>
                <span class="dash-chip"><i class="bi bi-clock"></i> {{ now()->format('M d, H:i') }}</span>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($highlights as $item)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="metric-card p-3 h-100 bg-white">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted text-uppercase small fw-semibold">{{ $item['label'] }}</span>
                        <span class="metric-icon">
                            <i class="bi bi-{{ $item['icon'] }}"></i>
                        </span>
                    </div>
                    <div class="h4 mb-1">{{ $item['value'] }}</div>
                    <small class="text-muted">Updated just now</small>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Recent Activity</h5>
                        <small class="text-muted">Latest automation runs and package updates.</small>
                    </div>
                    <span class="badge text-bg-light"><i class="bi bi-wifi"></i> Live</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($activity as $item)
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="d-flex gap-3">
                                <div class="timeline-dot"></div>
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $item['title'] }}</p>
                                    <small class="text-muted">{{ $item['detail'] }}</small>
                                </div>
                            </div>
                            <small class="text-muted">{{ $item['time'] }}</small>
                        </div>
                    @empty
                        <div class="list-group-item text-center text-muted py-4">
                            No recent activity yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Operations Snapshot</h5>
                        <small class="text-muted">Quick health summary.</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <p class="mb-0 fw-semibold">Automations</p>
                            <small class="text-muted">Active vs total</small>
                        </div>
                        <span class="badge text-bg-success">{{ $highlights[1]['value'] ?? '0' }} /
                            {{ $highlights[0]['value'] ?? '0' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <p class="mb-0 fw-semibold">Last automation run</p>
                            <small class="text-muted">{{ $highlights[3]['value'] ?? '—' }}</small>
                        </div>
                        <i class="bi bi-cpu text-primary"></i>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-0 fw-semibold">Package updates</p>
                            <small class="text-muted">{{ $highlights[2]['value'] ?? '—' }}</small>
                        </div>
                        <i class="bi bi-arrow-repeat text-primary"></i>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Your Profile</h5>
                        <small class="text-muted">Manage your account.</small>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-primary">Edit</a>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <p class="text-muted small mb-1">Name</p>
                        <p class="mb-0 fw-semibold">{{ auth()->user()->name }}</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-muted small mb-1">Email</p>
                        <p class="mb-0 fw-semibold">{{ auth()->user()->email }}</p>
                    </div>
                    <div>
                        <p class="text-muted small mb-1">Role</p>
                        <p class="mb-0 fw-semibold">
                            @php $roleName = auth()->user()->roles->first()?->name; @endphp
                            {{ $roleName ? \Illuminate\Support\Str::headline($roleName) : '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
