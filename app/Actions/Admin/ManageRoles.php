<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\Permission;
use App\Support\AdminMutations;
use App\Support\AdminPermissionCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class ManageRoles
{
    /** @param array<int, int|string>|null $permissionIds */
    public function save(Admin $actor, ?Role $role, ?string $name, ?array $permissionIds): Role
    {
        return AdminMutations::transaction(function () use ($actor, $role, $name, $permissionIds) {
            $actor = $actor->fresh();
            if ($role === null) {
                Gate::forUser($actor)->authorize('create', Role::class);
                if ($name === 'super-admin') {
                    throw ValidationException::withMessages(['name' => 'The super-admin role is code-managed.']);
                }
                $role = Role::create(['name' => $name, 'guard_name' => 'admin']);
            } else {
                $role = $role->fresh();
                $this->adminRole($role);
                if ($name === 'super-admin' && $role->name !== 'super-admin') {
                    throw ValidationException::withMessages(['name' => 'The super-admin role is code-managed.']);
                }
                if ($role->name === 'super-admin') {
                    if (! AdminMutations::isSuper($actor)) {
                        throw new AuthorizationException;
                    }
                    if ($name !== null && $name !== 'super-admin') {
                        throw ValidationException::withMessages(['name' => 'The super-admin role cannot be renamed.']);
                    }
                }
                if ($name !== null) {
                    Gate::forUser($actor)->authorize('update', $role);
                    $role->update(['name' => $name]);
                }
            }
            if ($permissionIds !== null) {
                Gate::forUser($actor)->authorize('assignPermissions', $role);
                $permissions = Permission::whereIn('id', $permissionIds)->where('guard_name', 'admin')->whereIn('name', AdminPermissionCatalog::names())->get();
                if ($permissions->count() !== count(array_unique($permissionIds))) {
                    throw ValidationException::withMessages(['permission_ids' => 'Select valid admin catalog permissions.']);
                }
                AdminMutations::grantPermissions($actor, $permissions);
                $role->syncPermissions($permissions);
            }

            return $role;
        });
    }

    public function delete(Admin $actor, Role $role): void
    {
        AdminMutations::transaction(function () use ($actor, $role): void {
            $actor = $actor->fresh();
            $role = $role->fresh();
            $this->adminRole($role);
            Gate::forUser($actor)->authorize('delete', $role);
            if ($role->name === 'super-admin') {
                throw ValidationException::withMessages(['delete' => 'The super-admin role cannot be deleted.']);
            }
            if (Admin::whereHas('roles', fn ($q) => $q->whereKey($role->id))->exists()) {
                throw ValidationException::withMessages(['delete' => 'This role is assigned to an Admin. Reassign its accounts first.']);
            }
            $role->delete();
        });
    }

    private function adminRole(Role $role): void
    {
        if ($role->guard_name !== 'admin') {
            throw new AuthorizationException;
        }
    }
}
