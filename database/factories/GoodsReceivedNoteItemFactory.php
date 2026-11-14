<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Domain\Receiving\Models\GoodsReceivedNoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceivedNoteItem>
 */
class GoodsReceivedNoteItemFactory extends Factory
{
    protected $model = GoodsReceivedNoteItem::class;

    public function definition(): array
    {
        return [
            'grn_id' => GoodsReceivedNote::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'item_id' => Item::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'accepted_quantity' => fake()->numberBetween(1, 100).'.0000',
            'rejected_quantity' => '0.0000',
            'unit_cost' => fake()->numberBetween(1000, 50000).'.000000',
            'batch_id' => null,
            'warehouse_bin_id' => null,
            'stock_transaction_item_id' => null,
            'sort_order' => 0,
        ];
    }
}
