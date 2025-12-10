<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row w-100 align-items-start align-items-md-center justify-content-between">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Overview</p>
                <h1 class="h4 mb-0">Welcome back, {{ auth()->user()->name }}</h1>
                <small class="text-muted">Simple, Bootstrap-flavored admin dashboard.</small>
            </div>
            <div class="mt-3 mt-md-0 d-flex align-items-center gap-2">
                <span class="badge bg-success rounded-pill">Online</span>
                <span class="text-muted small">All services healthy</span>
            </div>
        </div>
    </x-slot>

    @php
        $highlights = [
            ['label' => 'Active Projects', 'value' => '8', 'icon' => 'folder2-open'],
            ['label' => 'Open Tasks', 'value' => '34', 'icon' => 'check2-square'],
            ['label' => 'Pending Approvals', 'value' => '3', 'icon' => 'inboxes'],
            ['label' => 'Automation Runs', 'value' => '12', 'icon' => 'cpu'],
        ];

        $activity = [
            ['title' => 'New user request', 'detail' => 'Developer access for Maya', 'time' => '5m ago'],
            ['title' => 'Deployment queued', 'detail' => 'API service rolling out', 'time' => '18m ago'],
            ['title' => 'Test automation', 'detail' => 'Regression suite passed', 'time' => '1h ago'],
            ['title' => 'Report shared', 'detail' => 'Weekly delivery metrics', 'time' => '3h ago'],
        ];
    @endphp

    <div class="row g-3 mb-4">
        @foreach ($highlights as $item)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted text-uppercase small mb-1">{{ $item['label'] }}</p>
                                <h2 class="h4 mb-0">{{ $item['value'] }}</h2>
                            </div>
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                <i class="bi bi-{{ $item['icon'] }}"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Quick Actions</h5>
                        <small class="text-muted">Jump into common tasks.</small>
                    </div>
                    <span class="badge text-bg-primary">Shortcuts</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <a class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between" href="{{ route('dashboard') }}">
                                <span><i class="bi bi-speedometer2 me-2"></i>Dashboard</span>
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>

                        @if (auth()->user()->hasAnyRole(['admin', 'super-admin']))
                            <div class="col-12 col-md-4">
                                <a class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between" href="{{ route('admin.users.index') }}">
                                    <span><i class="bi bi-people-fill me-2"></i>User Management</span>
                                    <i class="bi bi-shield-lock"></i>
                                </a>
                            </div>
                        @endif

                        @if (auth()->user()->hasRole('super-admin'))
                            <div class="col-12 col-md-4">
                                <a class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between" href="{{ route('admin.roles.index') }}">
                                    <span><i class="bi bi-shield-lock-fill me-2"></i>Roles & Perms</span>
                                    <i class="bi bi-arrow-up-right"></i>
                                </a>
                            </div>
                        @endif

                        <div class="col-12 col-md-4">
                            <a class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between" href="mailto:team@example.com">
                                <span><i class="bi bi-envelope-fill me-2"></i>Report an issue</span>
                                <i class="bi bi-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Recent Activity</h5>
                        <small class="text-muted">Latest updates across your workspace.</small>
                    </div>
                    <span class="badge text-bg-light"><i class="bi bi-wifi"></i> Live</span>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($activity as $item)
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="d-flex gap-3">
                                <span class="badge rounded-circle bg-primary bg-opacity-10 text-primary p-3 d-inline-flex align-items-center justify-content-center">
                                    <i class="bi bi-clock-history"></i>
                                </span>
                                <div>
                                    <p class="mb-0 fw-semibold">{{ $item['title'] }}</p>
                                    <small class="text-muted">{{ $item['detail'] }}</small>
                                </div>
                            </div>
                            <small class="text-muted">{{ $item['time'] }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm border-0 mb-3 text-white" style="background: linear-gradient(135deg, #0d6efd, #6610f2);">
                <div class="card-body">
                    <p class="text-uppercase small fw-semibold text-white-50 mb-1">Status</p>
                    <h4 class="mb-2">Delivery Health</h4>
                    <p class="text-white-50 mb-4">Keep approvals flowing and stay ahead of blockers.</p>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Reliability</span>
                        <span class="fw-semibold">99.9%</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>QA coverage</span>
                        <span class="fw-semibold">86%</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Cycle time</span>
                        <span class="fw-semibold">2.3d</span>
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
