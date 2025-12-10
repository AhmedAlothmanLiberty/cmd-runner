<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionManagementController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::with('roles')->orderBy('name')->get();

        return view('admin.permissions.index', [
            'permissions' => $permissions,
        ]);
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        Permission::firstOrCreate(
            ['name' => $request->string('name')->lower(), 'guard_name' => 'web'],
        );

        return redirect()->route('admin.permissions.index')->with('status', 'Permission created.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return redirect()->route('admin.permissions.index')->with('status', 'Permission deleted.');
    }
}
