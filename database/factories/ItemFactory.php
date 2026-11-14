<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Enums\ItemTrackingType;
use App\Enums\ValuationMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'category_id' => ItemCategory::factory(),
            'code' => 'ITM-'.fake()->unique()->numerify('#####'),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'brand' => fake()->company(),
            'model' => fake()->bothify('MDL-####'),
            'manufacturer_part_number' => fake()->bothify('MPN-####'),
            'tracking_type' => ItemTrackingType::None,
            'base_unit_of_measure_id' => UnitOfMeasure::factory(),
            'minimum_stock' => '0.0000',
            'maximum_stock' => '1000.0000',
            'reorder_level' => '10.0000',
            'reorder_quantity' => '50.0000',
            'standard_cost' => '1000.0000',
            'valuation_method' => ValuationMethod::WeightedAverage,
            'requires_quality_inspection' => true,
            'shelf_life_days' => null,
            'is_active' => true,
        ];
    }

    public function forCategory(ItemCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    public function batchTracked(): static
    {
        return $this->state(fn (array $attributes) => [
            'tracking_type' => ItemTrackingType::Batch,
        ]);
    }

    public function serialTracked(): static
    {
        return $this->state(fn (array $attributes) => [
            'tracking_type' => ItemTrackingType::Serial,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
