<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Validation\Rule;

class SaveAdminRequest extends ManagementRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('admin');
        $admin = $this->targetAdmin();
        if (! $admin) {
            return $actor->can('create', Admin::class) && $actor->can('assignRole', new Admin);
        }
        if (! $actor->can('update', $admin) && ! $actor->can('assignRole', $admin)) {
            return false;
        }
        if (($this->exists('name') || $this->exists('email')) && ! $actor->can('update', $admin)) {
            return false;
        }

        return ! $this->exists('role_id') || $actor->can('assignRole', $admin);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $admin = $this->targetAdmin();
        $presence = $admin ? 'sometimes' : 'required';
        $rules = [
            'name' => [$presence, 'required', 'string', 'max:255'],
            'email' => [$presence, 'required', 'email', 'max:255', Rule::unique('admins')->ignore($admin?->id)],
            'role_id' => [$presence, 'required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'admin')],
        ];
        if (! $admin) {
            $rules['password'] = ['required', 'string', 'min:12', 'confirmed'];
            $rules['password_confirmation'] = ['required', 'string'];
        }

        return $rules;
    }

    private function targetAdmin(): ?Admin
    {
        $admin = $this->route('admin');
        abort_if($admin !== null && ! $admin instanceof Admin, 404);

        return $admin;
    }
}
