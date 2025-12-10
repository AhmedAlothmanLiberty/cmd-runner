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
        $roles = Role::orderBy('name')->pluck('name', 'id');

        return view('admin.users.create', [
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => $request->string('password'),
        ]);

        $roleNames = Role::query()
            ->whereIn('id', $request->input('roles', []))
            ->pluck('name')
            ->all();

        $user->syncRoles($roleNames);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->pluck('name', 'id');
        $user->load('roles');

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
        ]);

        if ($request->filled('password')) {
            $user->password = $request->string('password');
        }

        $user->save();

        $roleNames = Role::query()
            ->whereIn('id', $request->input('roles', []))
            ->pluck('name')
            ->all();

        $user->syncRoles($roleNames);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        // Prevent locking yourself out
        if (auth()->id() === $user->id) {
            return back()->with('status', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }
}
