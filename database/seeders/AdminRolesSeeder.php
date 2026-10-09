<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminRolesSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('super-admin', 'admin');
    }
}
