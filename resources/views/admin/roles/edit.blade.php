<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Super Admin</p>
                <h2 class="h4 mb-0">{{ __('Edit Role') }}</h2>
                <small class="text-muted">Update role and permissions for {{ $role->name }}.</small>
            </div>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to roles
            </a>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">Role name</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name', $role->name) }}"
                        required
                        @if($role->name === 'super-admin') readonly @endif
                    >
                    @if($role->name === 'super-admin')
                        <small class="text-muted">Super admin role name cannot be changed.</small>
                    @else
                        <small class="text-muted">Use lowercase; will be stored as lower-case.</small>
                    @endif
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="permissions" class="form-label">Permissions</label>
                    @php
                        $selectedPermissions = old('permissions', $role->permissions->pluck('id')->toArray());
                    @endphp
                    <x-permission-selector :permissions="$permissions" :selected="$selectedPermissions" />
                    <small class="text-muted">Select one or more permissions.</small>
                    @error('permissions')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>

            <div class="mt-3 d-flex justify-content-between align-items-center">
                @if ($role->name === 'super-admin')
                    <small class="text-muted">Super admin role cannot be deleted.</small>
                @else
                    <form class="d-inline" method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete role</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
