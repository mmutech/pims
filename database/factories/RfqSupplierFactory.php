<?php

namespace Database\Factories;

use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\RfqSupplier;
use App\Domain\Supplier\Models\Supplier;
use App\Enums\RfqSupplierStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqSupplier>
 */
class RfqSupplierFactory extends Factory
{
    protected $model = RfqSupplier::class;

    public function definition(): array
    {
        return [
            'request_for_quotation_id' => RequestForQuotation::factory(),
            'supplier_id' => Supplier::factory(),
            'invited_at' => now(),
            'responded_at' => null,
            'status' => RfqSupplierStatus::Invited,
        ];
    }
}
