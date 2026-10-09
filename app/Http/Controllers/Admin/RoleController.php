<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ManageRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteResourceRequest;
use App\Http\Requests\Admin\SaveRoleRequest;
use App\Models\Admin;
use App\Support\AdminManagementData;
use App\Support\AdminMutations;
use App\Support\AdminPermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);
        $actor = $request->user('admin');
        $roles = Role::where('guard_name', 'admin')->withCount('permissions')->orderBy('name')->paginate(15)->through(function ($role) use ($actor) {
            $protected = $role->name === 'super-admin';
            $assigned = Admin::whereHas('roles', fn ($q) => $q->whereKey($role->id))->exists();

            return ['id' => $role->id, 'name' => $role->name, 'permissions_count' => $role->permissions_count,
                'can_edit' => (! $protected || AdminMutations::isSuper($actor)) && ($actor->can('update', $role) || $actor->can('assignPermissions', $role)),
                'can_delete' => ! $protected && ! $assigned && $actor->can('delete', $role)];
        });

        return Inertia::render('admin/roles/index', compact('roles'));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('admin/roles/create', $this->formData($request));
    }

    public function store(SaveRoleRequest $request, ManageRoles $action): RedirectResponse
    {
        $data = $request->validated();
        $action->save($request->user('admin'), null, $data['name'], $data['permission_ids'] ?? null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role created.']);

        return to_route('admin.roles.index');
    }

    public function edit(Request $request, Role $role): Response
    {
        abort_unless($role->guard_name === 'admin', 404);
        $actor = $request->user('admin');
        abort_unless($actor->can('update', $role) || $actor->can('assignPermissions', $role), 403);
        abort_if($role->name === 'super-admin' && ! AdminMutations::isSuper($actor), 403);

        return Inertia::render('admin/roles/edit', [...$this->formData($request), 'role' => ['id' => $role->id, 'name' => $role->name, 'permission_ids' => $role->permissions->modelKeys()]]);
    }

    public function update(SaveRoleRequest $request, Role $role, ManageRoles $action): RedirectResponse
    {
        $data = $request->validated();
        $action->save($request->user('admin'), $role, $data['name'] ?? null, $data['permission_ids'] ?? null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role updated.']);

        return back();
    }

    public function destroy(DeleteResourceRequest $request, Role $role, ManageRoles $action): RedirectResponse
    {
        $action->delete($request->user('admin'), $role);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role deleted.']);

        return to_route('admin.roles.index');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        return ['groups' => AdminManagementData::groups(), 'grantablePermissions' => array_values(array_filter(AdminPermissionCatalog::names(), fn ($name) => $request->user('admin')->can($name)))];
    }
}
