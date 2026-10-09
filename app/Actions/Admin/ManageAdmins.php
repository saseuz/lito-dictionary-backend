<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Support\AdminMutations;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class ManageAdmins
{
    /** @param array<string, mixed> $attributes */
    public function create(Admin $actor, array $attributes, Role $role): Admin
    {
        return AdminMutations::transaction(function () use ($actor, $attributes, $role) {
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('create', Admin::class);
            // Only explicitly allowed scalar attributes ever reach mass assignment.
            $admin = Admin::create(array_intersect_key($attributes, array_flip(['name', 'email', 'password'])));

            return app(AssignAdminRole::class)->handle($admin, $role, $actor);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Admin $actor, Admin $admin, array $attributes, ?Role $role): Admin
    {
        return AdminMutations::transaction(function () use ($actor, $admin, $attributes, $role) {
            $actor = $actor->fresh();
            $admin = $admin->fresh();
            AdminMutations::protectTarget($actor, $admin);
            if ($attributes !== []) {
                Gate::forUser($actor)->authorize('update', $admin);
                $admin->fill(array_intersect_key($attributes, array_flip(['name', 'email'])));
                if ($admin->isDirty('email')) {
                    $admin->email_verified_at = null;
                }
                $admin->save();
            }
            if ($role !== null) {
                app(AssignAdminRole::class)->handle($admin, $role, $actor);
            }

            return $admin;
        });
    }

    public function delete(Admin $actor, Admin $admin): void
    {
        AdminMutations::transaction(function () use ($actor, $admin): void {
            $actor = $actor->fresh();
            $admin = $admin->fresh();
            Gate::forUser($actor)->authorize('delete', $admin);
            if ($actor->is($admin)) {
                throw ValidationException::withMessages(['delete' => 'You cannot delete your own Admin account.']);
            }
            AdminMutations::protectTarget($actor, $admin);
            AdminMutations::protectLastSuper($admin);
            $admin->delete();
        });
    }
}
