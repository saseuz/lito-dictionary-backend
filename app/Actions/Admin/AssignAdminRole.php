<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Support\AdminMutations;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Models\Role;

class AssignAdminRole
{
    public function handle(Admin $admin, Role $role, ?Admin $actor = null): Admin
    {
        if (! $admin->exists || ! $role->exists) {
            throw new InvalidArgumentException('Save the Admin and role before assigning a role.');
        }

        $this->ensureAdminGuard($role);

        // A null actor is reserved for trusted local provisioning commands.
        if ($actor === null && ! app()->runningInConsole()) {
            throw new AuthorizationException;
        }

        AdminMutations::transaction(function () use ($admin, $role, $actor): void {
            // Serialize replacements for the same Admin on databases supporting row locks.
            $account = $admin->newQuery()->whereKey($admin->getKey())->lockForUpdate()->firstOrFail();
            $assignedRole = $role->newQuery()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureAdminGuard($assignedRole);

            if ($actor !== null) {
                $actor = $actor->fresh();
                Gate::forUser($actor)->authorize('assignRole', $account);
                AdminMutations::protectTarget($actor, $account);
                AdminMutations::grantRole($actor, $assignedRole);
            }
            if ($assignedRole->name !== 'super-admin') {
                AdminMutations::protectLastSuper($account);
            }
            $account->syncRoles([$assignedRole]);
        });

        // Spatie clears relations on the instance it mutates, not other loaded instances.
        $admin->unsetRelation('roles')->unsetRelation('permissions');

        return $admin->load('roles.permissions', 'permissions');
    }

    private function ensureAdminGuard(Role $role): void
    {
        if ($role->guard_name !== 'admin') {
            throw GuardDoesNotMatch::create($role->guard_name, collect(['admin']));
        }
    }
}
