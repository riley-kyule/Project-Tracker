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
            'Product and Engineering',
            'Sales',
            'Finance',
            'Human Resources',
            'Marketing',
            'IT',
            'Legal',
            'Management',
        ];

        foreach ($departments as $name) {
            Department::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }

        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();

        foreach (['SEO', 'Social Media', 'Content'] as $name) {
            Department::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'parent_department_id' => $marketing->id],
            );
        }
    }
}
