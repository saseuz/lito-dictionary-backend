<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminPermissionCatalog;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SaveRoleRequest extends ManagementRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('admin');
        $role = $this->targetRole();
        if ($role && $role->guard_name !== 'admin') {
            return false;
        }
        if (! $role && ! $actor->can('create', Role::class)) {
            return false;
        }
        if ($role && ! $actor->can('update', $role) && ! $actor->can('assignPermissions', $role)) {
            return false;
        }
        if ($role && $this->exists('name') && ! $actor->can('update', $role)) {
            return false;
        }

        return ! $this->exists('permission_ids') || $actor->can('assignPermissions', $role ?? new Role(['guard_name' => 'admin']));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $role = $this->targetRole();

        return [
            'name' => [$role ? 'sometimes' : 'required', 'required', 'string', 'max:255', Rule::unique('roles')->where('guard_name', 'admin')->ignore($role?->id)],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')->where('guard_name', 'admin')->whereIn('name', AdminPermissionCatalog::names())],
        ];
    }

    private function targetRole(): ?Role
    {
        $role = $this->route('role');
        abort_if($role !== null && ! $role instanceof Role, 404);

        return $role;
    }
}
