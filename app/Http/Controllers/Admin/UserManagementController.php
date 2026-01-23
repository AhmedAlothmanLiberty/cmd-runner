<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Display the user management dashboard.
     */
    public function index(): View
    {
        $users = User::with('roles')->orderBy('name')->get();

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        $roleSelection = $this->roleSelectionData(auth()->user());

        return view('admin.users.create', [
            'roles' => $roleSelection['roles'],
            'disabledRoleIds' => $roleSelection['disabledRoleIds'],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => $request->string('password'),
        ]);

        $roleNames = [];
        if ($this->canAssignRoles($request->user())) {
            $roleNames = $this->filterRoleNames($request->input('roles', []), $request->user());
        }

        $user->syncRoles($roleNames);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        $this->abortIfProtectedUser($user);
        $roleSelection = $this->roleSelectionData(auth()->user());
        $user->load('roles');

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $roleSelection['roles'],
            'disabledRoleIds' => $roleSelection['disabledRoleIds'],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->abortIfProtectedUser($user);
        $actor = $request->user();
        $canUpdateProfile = $actor?->can('manage-users') || $actor?->can('update-user');
        $canChangePassword = $actor?->can('manage-users') || $actor?->can('change-user-password');

        if (! $canUpdateProfile && ! $canChangePassword) {
            abort(403);
        }

        if (! $canUpdateProfile) {
            if (
                $request->string('name')->toString() !== $user->name ||
                $request->string('email')->toString() !== $user->email
            ) {
                abort(403);
            }
        }

        $user->fill([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
        ]);

        if ($request->filled('password')) {
            if (! $canChangePassword) {
                abort(403);
            }
            $user->password = $request->string('password');
        }

        $user->save();

        if ($this->canAssignRoles($request->user())) {
            $roleNames = $this->filterRoleNames($request->input('roles', []), $request->user(), $user);
            $user->syncRoles($roleNames);
        }

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->abortIfProtectedUser($user);
        // Prevent locking yourself out
        if (auth()->id() === $user->id) {
            return back()->with('status', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    private function canAssignRoles(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        return $actor->can('manage-users')
            || $actor->can('assign-roles')
            || $actor->can('assign-admin-roles');
    }

    private function canAssignAdminRoles(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        return $actor->hasRole('super-admin') || $actor->can('assign-admin-roles');
    }

    private function roleSelectionData(?User $actor): array
    {
        $roles = Role::orderBy('name')->get(['id', 'name']);
        $disabledRoleIds = [];

        if (! $this->canAssignRoles($actor)) {
            $disabledRoleIds = $roles->pluck('id')->all();
        } elseif (! $this->canAssignAdminRoles($actor)) {
            $disabledRoleIds = $roles
                ->whereIn('name', ['admin', 'super-admin'])
                ->pluck('id')
                ->all();
        }

        return [
            'roles' => $roles->pluck('name', 'id'),
            'disabledRoleIds' => $disabledRoleIds,
        ];
    }

    private function filterRoleNames(array $roleIds, ?User $actor, ?User $target = null): array
    {
        $roleIds = array_map('intval', $roleIds);
        $query = Role::query()->whereIn('id', $roleIds);

        if (! $this->canAssignAdminRoles($actor)) {
            $query->whereNotIn('name', ['admin', 'super-admin']);
        }

        $roleNames = $query->pluck('name')->all();

        if ($target && ! $this->canAssignAdminRoles($actor)) {
            $protectedRoles = $target->roles()
                ->whereIn('name', ['admin', 'super-admin'])
                ->pluck('name')
                ->all();

            $roleNames = array_values(array_unique(array_merge($roleNames, $protectedRoles)));
        }

        return $roleNames;
    }

    private function abortIfProtectedUser(User $user): void
    {
        $actor = auth()->user();
        if ($user->hasRole('super-admin') && ! $actor?->hasRole('super-admin')) {
            abort(403);
        }
    }
}
