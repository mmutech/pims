<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Inventory\Models\StockTransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransactionItem>
 */
class StockTransactionItemFactory extends Factory
{
    protected $model = StockTransactionItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50).'.0000';
        $unitCost = fake()->numberBetween(1000, 50000).'.000000';

        return [
            'stock_transaction_id' => StockTransaction::factory(),
            'item_id' => Item::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => bcmul($quantity, $unitCost, 2),
            'stock_dimension_id' => StockDimension::factory(),
            'batch_id' => null,
            'serial_number_id' => null,
            'direction' => 1,
            'metadata' => null,
        ];
    }
}
