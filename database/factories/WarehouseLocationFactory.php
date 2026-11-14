<?php

namespace Database\Factories;

use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseLocation;
use App\Enums\WarehouseLocationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseLocation>
 */
class WarehouseLocationFactory extends Factory
{
    protected $model = WarehouseLocation::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'code' => 'LOC-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true).' Location',
            'type' => WarehouseLocationType::Storage,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function forWarehouse(Warehouse $warehouse): static
    {
        return $this->state(fn (array $attributes) => [
            'warehouse_id' => $warehouse->id,
        ]);
    }
}
