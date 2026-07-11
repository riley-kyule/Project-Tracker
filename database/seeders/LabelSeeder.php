<?php

namespace Database\Seeders;

use App\Models\Label;
use Illuminate\Database\Seeder;

class LabelSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            ['name' => 'Bug', 'color' => '#dc2626'],
            ['name' => 'Improvement', 'color' => '#2478be'],
            ['name' => 'Content', 'color' => '#7c3aed'],
            ['name' => 'SEO', 'color' => '#2e9bd6'],
            ['name' => 'Urgent Client', 'color' => '#ea580c'],
            ['name' => 'Internal', 'color' => '#64748b'],
        ];

        foreach ($labels as $label) {
            Label::firstOrCreate(['name' => $label['name']], $label);
        }
    }
}
