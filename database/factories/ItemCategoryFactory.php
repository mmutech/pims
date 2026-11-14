<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemCategory>
 */
class ItemCategoryFactory extends Factory
{
    protected $model = ItemCategory::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'code' => 'CAT-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(2, true).' Category',
            'description' => fake()->sentence(),
            'track_batches' => false,
            'track_serials' => false,
            'requires_quality_inspection' => true,
            'is_active' => true,
        ];
    }

    public function childOf(ItemCategory $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }
}
