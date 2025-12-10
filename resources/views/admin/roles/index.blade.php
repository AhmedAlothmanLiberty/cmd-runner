<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Super Admin</p>
                <h2 class="h4 mb-0">{{ __('Roles') }}</h2>
                <small class="text-muted">Manage roles and their permissions.</small>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New Role
                </a>
            </div>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0">All Roles</h5>
                <small class="text-muted">Assign permissions to each role.</small>
            </div>
            <span class="badge text-bg-info"><i class="bi bi-shield-lock me-1"></i> Super admin only</span>
        </div>
        <div class="card-body p-0">
            @if (session('status'))
                <div class="alert alert-info m-3 mb-0">
                    {{ session('status') }}
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Permissions</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr>
                                <td class="fw-semibold text-capitalize">{{ $role->name }}</td>
                                <td>
                                    @forelse ($role->permissions as $permission)
                                        <span class="badge text-bg-secondary me-1 mb-1 text-capitalize">{{ $permission->name }}</span>
                                    @empty
                                        <span class="text-muted small">No permissions</span>
                                    @endforelse
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-outline-secondary">Edit</a>
                                        @if ($role->name !== 'super-admin')
                                            <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" onsubmit="return confirm('Delete this role?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">No roles found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
