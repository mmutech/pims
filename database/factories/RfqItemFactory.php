<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\RfqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqItem>
 */
class RfqItemFactory extends Factory
{
    protected $model = RfqItem::class;

    public function definition(): array
    {
        return [
            'request_for_quotation_id' => RequestForQuotation::factory(),
            'purchase_requisition_item_id' => null,
            'item_id' => Item::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'quantity' => fake()->numberBetween(1, 50).'.0000',
            'specification' => null,
            'sort_order' => 0,
        ];
    }
}
