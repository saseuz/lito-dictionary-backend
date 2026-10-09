<?php

namespace App\Support;

class AdminPermissionCatalog
{
    public const GROUPS = [
        'dictionary' => ['name' => 'Dictionary', 'permissions' => ['dictionary.view']],
        'admins' => ['name' => 'Admin Management', 'permissions' => ['admins.view', 'admins.create', 'admins.update', 'admins.delete', 'admins.assign-role']],
        'roles' => ['name' => 'Role Management', 'permissions' => ['roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign-permissions']],
        'permissions' => ['name' => 'Permission Catalog', 'permissions' => ['permissions.view']],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_merge(...array_column(self::GROUPS, 'permissions'));
    }
}
