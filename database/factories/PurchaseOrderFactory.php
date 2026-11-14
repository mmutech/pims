<?php

namespace Database\Factories;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\PurchaseOrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'document_number' => 'PO/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'supplier_id' => Supplier::factory(),
            'delivery_warehouse_id' => Warehouse::factory(),
            'purchase_requisition_id' => null,
            'supplier_quotation_id' => null,
            'currency' => 'NGN',
            'payment_terms' => '30 days net',
            'incoterms' => null,
            'expected_delivery_date' => now()->addDays(21)->toDateString(),
            'subtotal' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => '0.00',
            'status' => PurchaseOrderStatus::Draft,
            'version' => 1,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
