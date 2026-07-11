<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepartmentSeeder::class,
            LabelSeeder::class,
        ]);

        // Local development accounts only; production accounts are created
        // via users:import or the admin UI.
        if (app()->environment('local')) {
            User::factory()
                ->create([
                    'name' => 'Local Admin',
                    'email' => 'admin@ewms.test',
                ])
                ->assignRole('Administrator');

            User::factory()
                ->create([
                    'name' => 'Local Employee',
                    'email' => 'employee@ewms.test',
                ])
                ->assignRole('Employee');

            $this->call(BoardSeeder::class);
        }
    }
}
