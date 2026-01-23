<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <h2 class="h4 mb-0">{{ __('User Management') }}</h2>
                <small class="text-muted">Manage team access, roles, and account status.</small>
            </div>
            <div class="mt-3 mt-md-0">
                @if (auth()->user()?->can('manage-users') || auth()->user()?->can('create-user'))
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> New User
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0">Team Members</h5>
                <small class="text-muted">Overview of all users and their roles.</small>
            </div>
            <span class="badge text-bg-success"><i class="bi bi-shield-lock me-1"></i> Admin only</span>
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
                            <th scope="col">Email</th>
                            <th scope="col">Roles</th>
                            <th scope="col">Joined</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                            @forelse ($users as $user)
                                @php
                                    $isProtected = $user->hasRole('super-admin') && ! auth()->user()?->hasRole('super-admin');
                                @endphp
                                <tr>
                                <td class="fw-semibold">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @forelse ($user->roles as $role)
                                        <span class="badge text-bg-secondary me-1 mb-1">{{ \Illuminate\Support\Str::headline($role->name) }}</span>
                                    @empty
                                        <span class="text-muted small">No role</span>
                                    @endforelse
                                </td>
                                <td class="text-muted small">
                                    {{ $user->created_at?->format('M j, Y') ?? '—' }}
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @if (! $isProtected && (auth()->user()?->can('manage-users') || auth()->user()?->can('update-user')))
                                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary">Edit</a>
                                        @endif
                                        @if (! $isProtected && (auth()->user()?->can('manage-users') || auth()->user()?->can('delete-user')) && auth()->id() !== $user->id)
                                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Delete this user?');">
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
                                <td colspan="5" class="text-center text-muted py-4">No users found yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
