@props([
    'permissions' => [],   // collection or array of permission models (id, name)
    'selected' => [],      // array of selected permission IDs
])

@php
    $selectedPermissions = collect($selected)->map(fn ($id) => (int) $id)->all();
@endphp

@once
    <style>
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
    </style>
@endonce

<div class="permission-pill-group">
    @foreach ($permissions as $permission)
        @php
            $fieldId = 'perm-' . $permission->id . '-' . uniqid();
            $isChecked = in_array((int) $permission->id, $selectedPermissions, true);
        @endphp
        <label class="permission-pill" for="{{ $fieldId }}">
            <input
                type="checkbox"
                id="{{ $fieldId }}"
                name="permissions[]"
                value="{{ $permission->id }}"
                @checked($isChecked)
            >
            <span class="badge-dot"></span>
            <span class="label-text">{{ $permission->name }}</span>
        </label>
    @endforeach
</div>
