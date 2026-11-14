<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50).'.0000';
        $price = fake()->numberBetween(1000, 50000).'.0000';

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'item_id' => Item::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'ordered_quantity' => $quantity,
            'received_quantity' => '0.0000',
            'rejected_quantity' => '0.0000',
            'unit_price' => $price,
            'tax_rate' => '7.5000',
            'discount_amount' => '0.00',
            'line_total' => '0.00',
            'required_by' => null,
            'cost_center_id' => null,
            'project_id' => null,
            'purchase_requisition_item_id' => null,
            'sort_order' => 0,
        ];
    }
}
