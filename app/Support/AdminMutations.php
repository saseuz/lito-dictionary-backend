<?php

namespace App\Support;

use App\Models\Admin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminMutations
{
    public static function transaction(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            // Every management mutation takes this lock before reading authorization state.
            // SQLite has no row locks: a no-op write reserves the database writer first.
            $query = DB::table('roles')->where('guard_name', 'admin')->where('name', 'super-admin');
            if (DB::connection()->getDriverName() === 'sqlite') {
                $query->update(['name' => DB::raw('name')]);
            }
            $query->lockForUpdate()->first();

            return $callback();
        });
    }

    public static function isSuper(Admin $admin): bool
    {
        return $admin->roles()->where('name', 'super-admin')->where('guard_name', 'admin')->exists();
    }

    public static function protectTarget(Admin $actor, Admin $target): void
    {
        if (self::isSuper($target) && ! self::isSuper($actor)) {
            throw new AuthorizationException('Only super administrators may modify super-admin accounts.');
        }
    }

    public static function protectLastSuper(Admin $target): void
    {
        if (self::isSuper($target) && Admin::whereHas('roles', fn ($q) => $q->where('name', 'super-admin')->where('guard_name', 'admin'))->count() <= 1) {
            throw ValidationException::withMessages(['role_id' => 'The final super administrator cannot be deleted or demoted.']);
        }
    }

    public static function grantRole(Admin $actor, Role $role): void
    {
        if ($role->name === 'super-admin' && ! self::isSuper($actor)) {
            throw new AuthorizationException('Only super administrators may assign the super-admin role.');
        }
        self::grantPermissions($actor, $role->permissions);
    }

    /** @param iterable<Permission> $permissions */
    public static function grantPermissions(Admin $actor, iterable $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($permission->guard_name !== 'admin' || ! $actor->can($permission->name)) {
                throw new AuthorizationException('You cannot grant permissions you do not possess.');
            }
        }
    }
}
