<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Procurement\Models\RfqItem;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Procurement\Models\SupplierQuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierQuotationItem>
 */
class SupplierQuotationItemFactory extends Factory
{
    protected $model = SupplierQuotationItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 50).'.0000';
        $price = fake()->numberBetween(1000, 50000).'.0000';

        return [
            'supplier_quotation_id' => SupplierQuotation::factory(),
            'rfq_item_id' => RfqItem::factory(),
            'item_id' => Item::factory(),
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'quantity' => $quantity,
            'unit_price' => $price,
            'tax_rate' => '7.5000',
            'discount_amount' => '0.00',
            'line_total' => '0.00',
            'lead_time_days' => null,
        ];
    }
}
