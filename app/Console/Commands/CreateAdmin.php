<?php

namespace App\Console\Commands;

use App\Actions\Admin\AssignAdminRole;
use App\Console\Concerns\SelectsAdminRole;
use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class CreateAdmin extends Command
{
    use SelectsAdminRole;

    protected $signature = 'app:create-admin';

    protected $description = 'Interactively create an Admin account';

    public function handle(AssignAdminRole $assignRole): int
    {
        $role = $this->selectAdminRole();

        if ($role === null) {
            return self::FAILURE;
        }

        $input = [
            'name' => trim((string) $this->ask('Name')),
            'email' => Str::lower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Password', false),
            'password_confirmation' => $this->secret('Confirm password', false),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Admin::class)],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            (new Admin)->getConnection()->transaction(function () use ($input, $role, $assignRole): void {
                // The model generates the UUID and hashes the password through its cast.
                $admin = Admin::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => $input['password'],
                ]);

                $assignRole->handle($admin, $role);
            });
        } catch (Throwable) {
            // Do not print exception details that could contain account input.
            $this->error('Unable to create Admin with the selected role. No account was created.');

            return self::FAILURE;
        }

        $this->info('Admin created: '.$input['email'].' (role: '.$role->name.')');

        return self::SUCCESS;
    }
}
