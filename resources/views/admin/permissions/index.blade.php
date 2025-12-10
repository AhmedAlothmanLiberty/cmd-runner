<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Super Admin</p>
                <h2 class="h4 mb-0">{{ __('Permissions') }}</h2>
                <small class="text-muted">Manage the permission catalog.</small>
            </div>
        </div>
    </x-slot>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">All Permissions</h5>
                        <small class="text-muted">List of permissions and their roles.</small>
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
                                    <th scope="col">Roles</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($permissions as $permission)
                                    <tr>
                                        <td class="text-capitalize">{{ $permission->name }}</td>
                                        <td>
                                            @forelse ($permission->roles as $role)
                                                <span class="badge text-bg-secondary me-1 mb-1 text-capitalize">{{ $role->name }}</span>
                                            @empty
                                                <span class="text-muted small">No roles</span>
                                            @endforelse
                                        </td>
                                        <td class="text-end">
                                            <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" onsubmit="return confirm('Delete this permission?');" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No permissions yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Create Permission</h5>
                    <small class="text-muted">Add a new permission to the catalog.</small>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.permissions.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">Permission name</label>
                            <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            <small class="text-muted">Use lowercase; will be stored as lower-case.</small>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Create permission</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
