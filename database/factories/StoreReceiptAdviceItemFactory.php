<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Models\StoreReceiptAdviceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreReceiptAdviceItem>
 */
class StoreReceiptAdviceItemFactory extends Factory
{
    protected $model = StoreReceiptAdviceItem::class;

    public function definition(): array
    {
        $expected = fake()->numberBetween(1, 100).'.0000';

        return [
            'sra_id' => StoreReceiptAdvice::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'item_id' => Item::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'expected_quantity' => $expected,
            'delivered_quantity' => $expected,
            'batch_number' => null,
            'serial_numbers' => null,
            'condition_notes' => null,
            'sort_order' => 0,
        ];
    }
}
