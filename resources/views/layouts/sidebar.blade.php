@php
    $links = [];

    if (auth()->check()) {
        $links[] = [
            'label' => 'Dashboard',
            'description' => 'Overview',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'icon' => 'speedometer2',
        ];
    }

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

        $links[] = [
            'label' => 'S3 Upload Jobs',
            'description' => 'Review S3 upload queue',
            'href' => route('admin.s3-upload-jobs.index'),
            'active' => request()->routeIs('admin.s3-upload-jobs.*'),
            'icon' => 'cloud-upload',
        ];

        $links[] = [
            'label' => 'EasyEngine Jobs',
            'description' => 'Track EasyEngine processing',
            'href' => route('admin.easyengine-jobs.index'),
            'active' => request()->routeIs('admin.easyengine-jobs.*'),
            'icon' => 'database',
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
            'active' => request()->routeIs('admin.tasks.*') && ! request()->routeIs('admin.tasks.backlog'),
            'icon' => 'check2-square',
        ];
    }

    if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'super-admin'])) {
        $links[] = [
            'label' => 'Backlog',
            'description' => 'On hold and deployed tasks',
            'href' => route('admin.tasks.backlog'),
            'active' => request()->routeIs('admin.tasks.backlog'),
            'icon' => 'inbox-fill',
        ];
    }

    $roleLabel = null;
    if (auth()->check() && method_exists(auth()->user(), 'getRoleNames')) {
        $roleLabel = auth()->user()->getRoleNames()->first();
        $roleLabel = str()->title(str()->replace('-', ' ', $roleLabel));
    }
@endphp

@once
    <style>
        .fin-sidebar {
            background: linear-gradient(180deg, #ffffff 0%, #f5f9fd 100%);
            /* border-right: 1px solid #e2e8f0; */
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
            padding: 1px;
            margin-bottom: 0.35rem;
            position: relative;
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
        body.sidebar-collapsed .fin-sidebar .nav-title,
        body.sidebar-collapsed .fin-sidebar .text-muted {
            display: none;
        }
        body.sidebar-collapsed .fin-sidebar .fin-link {
            justify-content: center;
        }
        body.sidebar-collapsed .fin-sidebar .fin-link .label,
        body.sidebar-collapsed .fin-sidebar .fin-link .desc,
        body.sidebar-collapsed .fin-sidebar .fin-link .flex-grow-1 {
            display: none;
        }
        body.sidebar-collapsed .fin-sidebar .fin-link::after {
            content: attr(data-label);
            position: absolute;
            left: 64px;
            top: 50%;
            transform: translateY(-50%);
            background: #0f172a;
            color: #fff;
            padding: 0.35rem 0.6rem;
            border-radius: 8px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.2);
            transition: opacity 0.12s ease;
        }
        body.sidebar-collapsed .fin-sidebar .fin-link:hover::after {
            opacity: 1;
        }
    </style>
@endonce

<div class="position-sticky pt-3 sidebar-sticky fin-sidebar">
    <div class="px-3 pb-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <p class="text-uppercase small fw-semibold mb-0 nav-title">Admin</p>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-sidebar-toggle aria-pressed="false" aria-label="Toggle sidebar">
                <i class="bi bi-layout-sidebar"></i>
            </button>
        </div>
        {{-- @if (!empty($roleLabel))
            <div class="text-muted small mb-3">Role: {{ $roleLabel }}</div>
        @endif --}}
        <div class="">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" data-label="{{ $link['label'] }}" title="{{ $link['label'] }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 fin-link @if($link['active']) active @endif">
                    <span class="icon"><i class="bi bi-{{ $link['icon'] }}"></i></span>
                    <div class="flex-grow-1">
                        <div class="label">{{ $link['label'] }}</div>
                        {{-- <small class="desc">{{ $link['description'] }}</small> --}}
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
            if (!sidebarToggle) return;

            const storageKey = 'sidebar-collapsed';
            const stored = localStorage.getItem(storageKey);
            const initialCollapsed = stored === null ? true : stored === 'true';
            document.body.classList.toggle('sidebar-collapsed', initialCollapsed);
            sidebarToggle.setAttribute('aria-pressed', initialCollapsed ? 'true' : 'false');

            sidebarToggle.addEventListener('click', () => {
                const isCollapsed = !document.body.classList.contains('sidebar-collapsed');
                document.body.classList.toggle('sidebar-collapsed', isCollapsed);
                sidebarToggle.setAttribute('aria-pressed', isCollapsed ? 'true' : 'false');
                localStorage.setItem(storageKey, isCollapsed ? 'true' : 'false');
            });
        });
    </script>
@endpush
