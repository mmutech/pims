<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBalance>
 */
class InventoryBalanceFactory extends Factory
{
    protected $model = InventoryBalance::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(0, 500).'.0000';

        return [
            'warehouse_id' => Warehouse::factory(),
            'stock_dimension_id' => StockDimension::factory(),
            'item_id' => Item::factory(),
            'batch_id' => null,
            'batch_key' => 0,
            'on_hand_quantity' => $quantity,
            'reserved_quantity' => '0.0000',
            'available_quantity' => $quantity,
            'average_unit_cost' => fake()->numberBetween(1000, 50000).'.000000',
            'total_value' => '0.00',
            'last_movement_at' => now(),
            'version' => 0,
        ];
    }
}
