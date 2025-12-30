<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <h2 class="h4 mb-0">{{ __('Edit User') }}</h2>
                <small class="text-muted">Update details and roles for {{ $user->name }}.</small>
            </div>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to users
            </a>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required autofocus>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">New Password (optional)</label>
                        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Confirm Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Roles</label>
                    @php
                        $selectedRoles = old('roles', $user->roles->pluck('id')->toArray());
                    @endphp
                    <x-role-selector :roles="$roles" :selected="$selectedRoles" />
                    <small class="text-muted">Select one or more roles.</small>
                    @error('roles')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>

            <div class="mt-4 d-flex justify-content-between align-items-center">
                @if (auth()->id() === $user->id)
                    <small class="text-muted">You cannot delete your own account.</small>
                @else
                    <form class="d-inline" method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete user</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
