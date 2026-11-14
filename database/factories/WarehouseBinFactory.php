<?php

namespace Database\Factories;

use App\Domain\Warehouse\Models\WarehouseBin;
use App\Domain\Warehouse\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseBin>
 */
class WarehouseBinFactory extends Factory
{
    protected $model = WarehouseBin::class;

    public function definition(): array
    {
        return [
            'warehouse_location_id' => WarehouseLocation::factory(),
            'code' => 'BIN-'.fake()->unique()->numerify('####'),
            'name' => 'Rack '.fake()->bothify('?#'),
            'max_capacity' => '1000.0000',
            'is_active' => true,
        ];
    }

    public function forLocation(WarehouseLocation $location): static
    {
        return $this->state(fn (array $attributes) => [
            'warehouse_location_id' => $location->id,
        ]);
    }
}
