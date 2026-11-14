<?php

namespace Database\Factories;

use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Supplier\Models\Supplier;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierQuotation>
 */
class SupplierQuotationFactory extends Factory
{
    protected $model = SupplierQuotation::class;

    public function definition(): array
    {
        return [
            'document_number' => 'QT/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'request_for_quotation_id' => RequestForQuotation::factory(),
            'supplier_id' => Supplier::factory(),
            'currency' => 'NGN',
            'valid_until' => now()->addDays(30)->toDateString(),
            'lead_time_days' => fake()->numberBetween(3, 30),
            'payment_terms' => '30 days net',
            'warranty_terms' => null,
            'subtotal' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => '0.00',
            'status' => SupplierQuotationStatus::Draft,
            'submitted_at' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierQuotationStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierQuotationStatus::Accepted,
            'submitted_at' => now(),
        ]);
    }
}
