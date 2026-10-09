<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(Admin|User $actor): bool
    {
        return $actor instanceof Admin && $actor->can('roles.view');
    }

    public function view(Admin|User $actor, Role $role): bool
    {
        return $actor instanceof Admin && $role->guard_name === 'admin' && $actor->can('roles.view');
    }

    public function create(Admin|User $actor): bool
    {
        return $actor instanceof Admin && $actor->can('roles.create');
    }

    public function update(Admin|User $actor, Role $role): bool
    {
        return $actor instanceof Admin && $role->guard_name === 'admin' && $actor->can('roles.update');
    }

    public function assignPermissions(Admin|User $actor, Role $role): bool
    {
        return $actor instanceof Admin && $role->guard_name === 'admin' && $actor->can('roles.assign-permissions');
    }

    public function delete(Admin|User $actor, Role $role): bool
    {
        return $actor instanceof Admin && $role->guard_name === 'admin' && $actor->can('roles.delete');
    }
}
