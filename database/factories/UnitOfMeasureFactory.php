<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Enums\UnitOfMeasureType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    public function definition(): array
    {
        return [
            'code' => 'UOM-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->word().' Unit',
            'symbol' => fake()->lexify('???'),
            'type' => UnitOfMeasureType::Quantity,
            'decimal_places' => 0,
            'is_active' => true,
        ];
    }

    public function pieces(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'PCS-'.fake()->unique()->numerify('###'),
            'name' => 'Pieces',
            'symbol' => 'pcs',
            'type' => UnitOfMeasureType::Quantity,
            'decimal_places' => 0,
        ]);
    }
}
