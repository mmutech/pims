<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\PurchaseRequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequisitionItem>
 */
class PurchaseRequisitionItemFactory extends Factory
{
    protected $model = PurchaseRequisitionItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50).'.0000';
        $price = fake()->numberBetween(1000, 50000).'.0000';

        return [
            'purchase_requisition_id' => PurchaseRequisition::factory(),
            'item_id' => Item::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'requested_quantity' => $quantity,
            'approved_quantity' => $quantity,
            'converted_quantity' => '0.0000',
            'estimated_unit_price' => $price,
            'estimated_line_total' => '0.00',
            'required_by' => now()->addDays(14)->toDateString(),
            'specification' => null,
            'sort_order' => 0,
        ];
    }
}
