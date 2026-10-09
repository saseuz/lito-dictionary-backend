<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(Admin|User $actor): bool
    {
        return $actor instanceof Admin && $actor->can('permissions.view');
    }
}
