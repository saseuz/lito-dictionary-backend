<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\PermissionGroup;
use Spatie\Permission\Models\Role;

class AdminManagementData
{
    /** @return array<int, array{id: int|string, name: string}> */
    public static function roles(Admin $actor): array
    {
        return Role::where('guard_name', 'admin')->with('permissions')->orderBy('name')->get()
            ->filter(fn ($role) => ($role->name !== 'super-admin' || AdminMutations::isSuper($actor)) && $role->permissions->every(fn ($p) => $p->guard_name === 'admin' && $actor->can($p->name)))
            ->map(fn ($role) => ['id' => $role->id, 'name' => $role->name])->values()->all();
    }

    /** @return array<int, array{id: int, name: string, permissions: array<int, array{id: int|string, name: string}>}> */
    public static function groups(): array
    {
        return PermissionGroup::where('guard_name', 'admin')->orderBy('id')
            ->with(['permissions' => fn ($q) => $q->where('guard_name', 'admin')->whereIn('name', AdminPermissionCatalog::names())->orderBy('id')])
            ->get()->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'permissions' => $g->permissions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all()])->all();
    }
}
