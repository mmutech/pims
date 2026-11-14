<?php

namespace Database\Factories;

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\Department;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Enums\PurchaseRequisitionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequisition>
 */
class PurchaseRequisitionFactory extends Factory
{
    protected $model = PurchaseRequisition::class;

    public function definition(): array
    {
        return [
            'document_number' => 'PR/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'requesting_department_id' => Department::factory(),
            'business_unit_id' => BusinessUnit::factory(),
            'cost_center_id' => null,
            'project_id' => null,
            'requested_by' => User::factory(),
            'suggested_supplier_id' => null,
            'required_by' => now()->addDays(14)->toDateString(),
            'justification' => fake()->sentence(12),
            'status' => PurchaseRequisitionStatus::Draft,
            'total_estimated_amount' => '0.00',
            'approved_amount' => '0.00',
            'currency' => 'NGN',
            'version' => 1,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseRequisitionStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
