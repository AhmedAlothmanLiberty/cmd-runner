@php
    $links = [
        [
            'label' => 'User Management',
            'description' => 'Manage access and roles',
            'href' => route('admin.users.index'),
            'active' => request()->routeIs('admin.users.*'),
            'icon' => 'people-fill',
        ],
    ];

    if (auth()->user()->hasRole('super-admin')) {
        $links[] = [
            'label' => 'Roles',
            'description' => 'Define role capabilities',
            'href' => route('admin.roles.index'),
            'active' => request()->routeIs('admin.roles.*'),
            'icon' => 'shield-lock',
        ];

        $links[] = [
            'label' => 'Permissions',
            'description' => 'Manage permissions catalog',
            'href' => route('admin.permissions.index'),
            'active' => request()->routeIs('admin.permissions.*'),
            'icon' => 'key-fill',
        ];
    }
@endphp

<div class="position-sticky pt-3 sidebar-sticky">
    <div class="px-3">
        <p class="text-uppercase text-muted small fw-semibold mb-3">Admin</p>
        <div class="list-group">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start @if($link['active']) active @endif">
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold d-flex align-items-center gap-2">
                            <i class="bi bi-{{ $link['icon'] }}"></i>
                            {{ $link['label'] }}
                        </div>
                        <small class="text-muted">{{ $link['description'] }}</small>
                    </div>
                    @if($link['active'])
                        <span class="badge bg-light text-dark rounded-pill">Now</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
