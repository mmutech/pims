<?php

namespace Database\Factories;

use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\StockDimensionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockDimension>
 */
class StockDimensionFactory extends Factory
{
    protected $model = StockDimension::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'warehouse_location_id' => null,
            'warehouse_bin_id' => null,
            'code' => 'W'.fake()->unique()->numerify('#####').':L0:B0:warehouse',
            'type' => StockDimensionType::Warehouse,
            'is_available' => true,
            'is_active' => true,
        ];
    }
}
