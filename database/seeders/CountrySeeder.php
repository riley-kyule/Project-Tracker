<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['iso_code' => 'US', 'name' => 'United States', 'region' => 'Americas'],
            ['iso_code' => 'GB', 'name' => 'United Kingdom', 'region' => 'Europe'],
            ['iso_code' => 'KE', 'name' => 'Kenya', 'region' => 'Africa'],
            ['iso_code' => 'NG', 'name' => 'Nigeria', 'region' => 'Africa'],
            ['iso_code' => 'ZA', 'name' => 'South Africa', 'region' => 'Africa'],
            ['iso_code' => 'AE', 'name' => 'United Arab Emirates', 'region' => 'Middle East'],
            ['iso_code' => 'IN', 'name' => 'India', 'region' => 'Asia'],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(['iso_code' => $country['iso_code']], $country);
        }
    }
}
