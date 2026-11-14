<?php

namespace Database\Factories;

use App\Domain\Procurement\Models\PurchaseOrderAmendment;
use App\Domain\Procurement\Models\PurchaseOrderAmendmentItem;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderAmendmentItem>
 */
class PurchaseOrderAmendmentItemFactory extends Factory
{
    protected $model = PurchaseOrderAmendmentItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50).'.0000';
        $price = fake()->numberBetween(1000, 50000).'.0000';

        return [
            'purchase_order_amendment_id' => PurchaseOrderAmendment::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'original_quantity' => $quantity,
            'amended_quantity' => $quantity,
            'original_unit_price' => $price,
            'amended_unit_price' => $price,
            'original_line_total' => '0.00',
            'amended_line_total' => '0.00',
        ];
    }
}
