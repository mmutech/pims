<?php

namespace Database\Factories;

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Enums\RequestForQuotationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestForQuotation>
 */
class RequestForQuotationFactory extends Factory
{
    protected $model = RequestForQuotation::class;

    public function definition(): array
    {
        return [
            'document_number' => 'RFQ/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'purchase_requisition_id' => null,
            'business_unit_id' => BusinessUnit::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'closing_date' => now()->addDays(10)->toDateString(),
            'status' => RequestForQuotationStatus::Draft,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestForQuotationStatus::Issued,
            'issued_at' => now(),
        ]);
    }
}
