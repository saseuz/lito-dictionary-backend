<?php

namespace App\Console\Commands;

use App\Actions\Admin\AssignAdminRole;
use App\Console\Concerns\SelectsAdminRole;
use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AssignAdminRoleCommand extends Command
{
    use SelectsAdminRole;

    protected $signature = 'app:assign-admin-role';

    protected $description = 'Interactively assign or replace an existing Admin role';

    public function handle(AssignAdminRole $assignRole): int
    {
        $email = Str::lower(trim((string) $this->ask('Admin email')));
        $admin = Admin::query()->where('email', $email)->first();

        if ($admin === null) {
            $this->error('No Admin exists with that email.');

            return self::FAILURE;
        }

        $role = $this->selectAdminRole();

        if ($role === null) {
            return self::FAILURE;
        }

        $currentRoles = $admin->roles->pluck('name')->implode(', ');
        $this->line('Admin: '.$admin->email);
        $this->line('Current role: '.($currentRoles ?: 'none'));
        $this->line('Proposed role: '.$role->name);

        if (! $this->confirm('Change this Admin role?', false)) {
            $this->info('Cancelled. No changes made.');

            return self::SUCCESS;
        }

        try {
            $assignRole->handle($admin, $role);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                $this->error($messages[0]);
            }

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Unable to assign the selected role.');

            return self::FAILURE;
        }

        $this->info('Admin role updated: '.$admin->email.' (role: '.$role->name.')');

        return self::SUCCESS;
    }
}
