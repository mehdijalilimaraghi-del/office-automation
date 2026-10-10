<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CorePermissionSeeder::class,
            CoreRoleSeeder::class,
            DefaultDepartmentSeeder::class,
            SharedProjectSeeder::class,
        ]);
    }
}
