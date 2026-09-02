<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_tag' => 'AST-'.fake()->unique()->numberBetween(1000, 9999),
            'asset_category_id' => AssetCategory::factory(),
            'name' => fake()->randomElement(['MacBook Pro 14"', 'Dell Latitude', 'iPhone 13', 'LG UltraFine']),
            'serial_number' => strtoupper(Str::random(10)),
            'manufacturer' => fake()->randomElement(['Apple', 'Dell', 'LG', 'HP']),
            'purchase_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'purchase_cost' => fake()->numberBetween(20000, 250000),
            'status' => Asset::STATUS_IN_STOCK,
            'condition' => 'good',
        ];
    }
}
