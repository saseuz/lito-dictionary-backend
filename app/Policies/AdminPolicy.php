<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;

class AdminPolicy
{
    public function viewAny(Admin|User $actor): bool
    {
        return $actor instanceof Admin && $actor->can('admins.view');
    }

    public function view(Admin|User $actor, Admin $admin): bool
    {
        return $actor instanceof Admin && $actor->can('admins.view');
    }

    public function create(Admin|User $actor): bool
    {
        return $actor instanceof Admin && $actor->can('admins.create');
    }

    public function update(Admin|User $actor, Admin $admin): bool
    {
        return $actor instanceof Admin && $actor->can('admins.update');
    }

    public function assignRole(Admin|User $actor, Admin $admin): bool
    {
        return $actor instanceof Admin && $actor->can('admins.assign-role');
    }

    public function delete(Admin|User $actor, Admin $admin): bool
    {
        return $actor instanceof Admin && $actor->can('admins.delete');
    }
}
