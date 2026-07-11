<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'SEO',
            'Product and Engineering',
            'Sales',
            'Finance',
            'Human Resources',
            'Marketing',
            'IT',
            'Legal',
            'Content',
            'Management',
        ];

        foreach ($departments as $name) {
            Department::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
