@props([
    'permissions' => [],   // collection or array of permission models (id, name)
    'selected' => [],      // array of selected permission IDs
])

@php
    $selectedPermissions = collect($selected)->map(fn ($id) => (int) $id)->all();
    $groupOrder = [
        'Users',
        'Roles',
        'Permissions',
        'Tasks',
        'Automations',
        'Projects',
        'Reports',
        'Dashboard',
        'Other',
    ];
    $groupFor = function (string $name): string {
        $lower = strtolower($name);
        $explicit = [
            'assign-roles' => 'Users',
            'assign-admin-roles' => 'Users',
            'view-backlog' => 'Tasks',
        ];
        if (array_key_exists($lower, $explicit)) {
            return $explicit[$lower];
        }
        if (str_contains($lower, 'user')) {
            return 'Users';
        }
        if (str_contains($lower, 'role')) {
            return 'Roles';
        }
        if (str_contains($lower, 'permission')) {
            return 'Permissions';
        }
        if (str_contains($lower, 'task')) {
            return 'Tasks';
        }
        if (str_contains($lower, 'automation')) {
            return 'Automations';
        }
        if (str_contains($lower, 'project')) {
            return 'Projects';
        }
        if (str_contains($lower, 'report')) {
            return 'Reports';
        }
        if (str_contains($lower, 'dashboard')) {
            return 'Dashboard';
        }
        return 'Other';
    };
    $permissionsByGroup = [];
    foreach ($permissions as $permission) {
        $group = $groupFor($permission->name);
        $permissionsByGroup[$group][] = $permission;
    }
    $sortedGroups = array_values(array_unique(array_merge($groupOrder, array_keys($permissionsByGroup))));
@endphp

@once
    <style>
        .permission-group {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem;
            background: #fff;
        }
        .permission-group + .permission-group {
            margin-top: 0.75rem;
        }
        .permission-group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .permission-group-title {
            font-weight: 700;
            color: #0f172a;
        }
        .permission-group-meta {
            font-size: 0.75rem;
            color: #64748b;
        }
        .permission-pill-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .permission-pill {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.75rem;
            border: 1px solid #d0d7de;
            border-radius: 999px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        .permission-pill:hover {
            border-color: #94a3b8;
            background: #eef2ff;
        }
        .permission-pill input[type="checkbox"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .permission-pill .badge-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .permission-pill .label-text {
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
            text-transform: capitalize;
        }
        .permission-pill input[type="checkbox"]:checked ~ .badge-dot {
            background: #16a34a;
            transform: scale(1.1);
        }
        .permission-pill input[type="checkbox"]:checked ~ .label-text {
            color: #0b4abf;
        }
        .permission-pill.is-master {
            border-color: #2563eb;
            background: #eff6ff;
        }
    </style>
@endonce

@foreach ($sortedGroups as $group)
    @php
        $groupPermissions = $permissionsByGroup[$group] ?? [];
        if (empty($groupPermissions)) {
            continue;
        }
        $groupKey = strtolower(str_replace(' ', '-', $group));
        $groupPermissions = collect($groupPermissions)
            ->sortBy(fn ($perm) => $perm->name)
            ->values()
            ->all();
        $masterPermission = collect($groupPermissions)->first(fn ($perm) => str_starts_with($perm->name, 'manage-'));
        if ($masterPermission) {
            $groupPermissions = collect($groupPermissions)
                ->reject(fn ($perm) => $perm->id === $masterPermission->id)
                ->prepend($masterPermission)
                ->all();
        }
        $masterId = $masterPermission?->id;
    @endphp
    <div class="permission-group" data-permission-group="{{ $groupKey }}">
        <div class="permission-group-header">
            <div class="permission-group-title">{{ $group }}</div>
            <div class="permission-group-meta">{{ count($groupPermissions) }} permissions</div>
        </div>
        <div class="permission-pill-group">
            @foreach ($groupPermissions as $permission)
                @php
                    $fieldId = 'perm-' . $permission->id . '-' . uniqid();
                    $isChecked = in_array((int) $permission->id, $selectedPermissions, true);
                    $isMaster = $masterId && (int) $permission->id === (int) $masterId;
                @endphp
                <label class="permission-pill {{ $isMaster ? 'is-master' : '' }}" for="{{ $fieldId }}">
                    <input
                        type="checkbox"
                        id="{{ $fieldId }}"
                        name="permissions[]"
                        value="{{ $permission->id }}"
                        data-permission
                        data-permission-name="{{ $permission->name }}"
                        data-group="{{ $groupKey }}"
                        @if ($isMaster) data-master="1" @endif
                        @checked($isChecked)
                    >
                    <span class="badge-dot"></span>
                    <span class="label-text">{{ $permission->name }}</span>
                </label>
            @endforeach
        </div>
    </div>
@endforeach

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-permission-group]').forEach((group) => {
                    const master = group.querySelector('input[data-master="1"]');
                    const boxes = Array.from(group.querySelectorAll('input[data-permission]'));
                    const children = boxes.filter((box) => box !== master);

                    const syncMaster = () => {
                        if (!master) return;
                        const total = children.length;
                        const checkedCount = children.filter((box) => box.checked).length;
                        master.checked = total > 0 && checkedCount === total;
                        master.indeterminate = checkedCount > 0 && checkedCount < total;
                    };

                    if (master) {
                        master.addEventListener('change', () => {
                            children.forEach((box) => {
                                if (!box.disabled) {
                                    box.checked = master.checked;
                                }
                            });
                            master.indeterminate = false;
                        });
                    }

                    children.forEach((box) => {
                        box.addEventListener('change', syncMaster);
                    });

                    syncMaster();
                });
            });
        </script>
    @endpush
@endonce
