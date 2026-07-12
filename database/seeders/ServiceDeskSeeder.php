<?php

namespace Database\Seeders;

use App\Models\SlaPolicy;
use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

class ServiceDeskSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Account and Access' => 'high',
            'Hardware' => 'medium',
            'Software' => 'medium',
            'Website' => 'high',
            'CRM' => 'high',
            'Network' => 'high',
            'Server' => 'critical',
            'Cybersecurity' => 'critical',
            'Other' => 'low',
        ];

        foreach ($categories as $name => $priority) {
            TicketCategory::firstOrCreate(['name' => $name], ['default_priority' => $priority]);
        }

        $policies = [
            ['priority' => 'critical', 'first_response_minutes' => 30, 'resolution_minutes' => 240],
            ['priority' => 'high', 'first_response_minutes' => 60, 'resolution_minutes' => 480],
            ['priority' => 'medium', 'first_response_minutes' => 240, 'resolution_minutes' => 1440],
            ['priority' => 'low', 'first_response_minutes' => 480, 'resolution_minutes' => 4320],
        ];

        foreach ($policies as $policy) {
            SlaPolicy::firstOrCreate(['priority' => $policy['priority']], $policy);
        }
    }
}
