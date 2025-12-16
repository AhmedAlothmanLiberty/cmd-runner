@props([
    'roles' => [],          // [id => name]
    'selected' => [],       // array of selected role IDs
])

@php
    $selectedRoles = collect($selected)->map(fn ($id) => (int) $id)->all();
@endphp

@once
    <style>
        .role-pill-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .role-pill {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.75rem;
            border: 1px solid #d0d7de;
            border-radius: 999px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        .role-pill:hover {
            border-color: #94a3b8;
            background: #eef2ff;
        }
        .role-pill input[type="checkbox"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .role-pill .badge-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .role-pill .label-text {
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
        }
        .role-pill input[type="checkbox"]:checked ~ .badge-dot {
            background: #2563eb;
            transform: scale(1.1);
        }
        .role-pill input[type="checkbox"]:checked ~ .label-text {
            color: #0b4abf;
        }
    </style>
@endonce

<div class="role-pill-group">
    @foreach ($roles as $id => $roleName)
        @php
            $fieldId = 'role-' . $id . '-' . uniqid();
            $isChecked = in_array((int) $id, $selectedRoles, true);
        @endphp
        <label class="role-pill" for="{{ $fieldId }}">
            <input
                type="checkbox"
                id="{{ $fieldId }}"
                name="roles[]"
                value="{{ $id }}"
                @checked($isChecked)
            >
            <span class="badge-dot"></span>
            <span class="label-text">{{ \Illuminate\Support\Str::headline($roleName) }}</span>
        </label>
    @endforeach
</div>
