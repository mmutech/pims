<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Receiving\Models\SupplierReturn;
use App\Domain\Receiving\Models\SupplierReturnItem;
use App\Enums\DeliveryCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierReturnItem>
 */
class SupplierReturnItemFactory extends Factory
{
    protected $model = SupplierReturnItem::class;

    public function definition(): array
    {
        return [
            'supplier_return_id' => SupplierReturn::factory(),
            'item_id' => Item::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'quantity' => fake()->numberBetween(1, 20).'.0000',
            'unit_cost' => fake()->numberBetween(1000, 50000).'.000000',
            'batch_id' => null,
            'serial_numbers' => null,
            'condition' => DeliveryCondition::Good,
            'stock_transaction_item_id' => null,
            'sort_order' => 0,
        ];
    }
}
