<?php

namespace App\Console\Concerns;

use Spatie\Permission\Models\Role;

trait SelectsAdminRole
{
    protected function selectAdminRole(): ?Role
    {
        $roles = Role::query()->where('guard_name', 'admin')->orderBy('name')->get();

        if ($roles->isEmpty()) {
            $this->error('No admin roles exist. Run php artisan db:seed --class=AdminRolesSeeder first.');

            return null;
        }

        if (! $this->input->isInteractive()) {
            $this->error('An explicit interactive role selection is required.');

            return null;
        }

        // No default: the operator must choose even when only one role exists.
        $name = $this->choice('Role', $roles->pluck('name')->all(), null);
        $role = $roles->firstWhere('name', $name);

        if ($role === null) {
            $this->error('Select an available admin role.');
        }

        return $role;
    }
}
