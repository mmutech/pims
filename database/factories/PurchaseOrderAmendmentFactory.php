<?php

namespace Database\Factories;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseOrderAmendment;
use App\Enums\PurchaseOrderAmendmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderAmendment>
 */
class PurchaseOrderAmendmentFactory extends Factory
{
    protected $model = PurchaseOrderAmendment::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'amendment_number' => 1,
            'reason' => fake()->sentence(),
            'status' => PurchaseOrderAmendmentStatus::Draft,
            'requested_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
            'submitted_at' => null,
        ];
    }
}
