@php
    $links = [];

    if (auth()->user()->hasAnyRole(['admin', 'super-admin'])) {
        $links[] = [
            'label' => 'User Management',
            'description' => 'Manage access and roles',
            'href' => route('admin.users.index'),
            'active' => request()->routeIs('admin.users.*'),
            'icon' => 'people-fill',
        ];
    }

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

        $links[] = [
            'label' => 'Package Updates',
            'description' => 'Run composer updates',
            'href' => route('admin.package-updates.index'),
            'active' => request()->routeIs('admin.package-updates.*'),
            'icon' => 'arrow-repeat',
        ];
    }

    if (auth()->user()->hasAnyRole(['admin', 'automation', 'super-admin'])) {
        $links[] = [
            'label' => 'Automations',
            'description' => 'Manage automation jobs',
            'href' => route('admin.automations.index'),
            'active' => request()->routeIs('admin.automations.*'),
            'icon' => 'gear-fill',
        ];
    }

    if (auth()->check()) {
        $links[] = [
            'label' => 'Tasks',
            'description' => 'Track work items',
            'href' => route('admin.tasks.index'),
            'active' => request()->routeIs('admin.tasks.*'),
            'icon' => 'check2-square',
        ];
    }
@endphp

@once
    <style>
        .fin-sidebar {
            background: linear-gradient(180deg, #ffffff 0%, #f5f9fd 100%);
            border-right: 1px solid #e2e8f0;
        }
        .fin-sidebar .nav-title {
            letter-spacing: 0.2em;
            color: #64748b;
        }
        .fin-sidebar .nav-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
            padding: 0.5rem;
        }
        .fin-sidebar .fin-link {
            border: 0;
            border-radius: 12px;
            padding: 0.75rem 0.85rem;
            margin-bottom: 0.35rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .fin-sidebar .fin-link:last-child {
            margin-bottom: 0;
        }
        .fin-sidebar .fin-link:hover {
            background: #f1f5f9;
            transform: translateX(2px);
        }
        .fin-sidebar .fin-link.active {
            background: rgba(50, 154, 214, 0.12);
            border: 1px solid rgba(50, 154, 214, 0.35);
            box-shadow: 0 8px 20px rgba(50, 154, 214, 0.18);
        }
        .fin-sidebar .fin-link .icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: #334155;
        }
        .fin-sidebar .fin-link.active .icon {
            background: #329ad6;
            color: #fff;
        }
        .fin-sidebar .fin-link .label {
            font-weight: 700;
            color: #0f172a;
        }
        .fin-sidebar .fin-link .desc {
            color: #64748b;
        }
    </style>
@endonce

<div class="position-sticky pt-3 sidebar-sticky fin-sidebar">
    <div class="px-3 pb-3">
        <p class="text-uppercase small fw-semibold mb-3 nav-title">Admin</p>
        <div class="nav-card">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 fin-link @if($link['active']) active @endif">
                    <span class="icon"><i class="bi bi-{{ $link['icon'] }}"></i></span>
                    <div class="flex-grow-1">
                        <div class="label">{{ $link['label'] }}</div>
                        <small class="desc">{{ $link['description'] }}</small>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>
