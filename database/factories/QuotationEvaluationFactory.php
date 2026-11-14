<?php

namespace Database\Factories;

use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Enums\QuotationEvaluationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationEvaluation>
 */
class QuotationEvaluationFactory extends Factory
{
    protected $model = QuotationEvaluation::class;

    public function definition(): array
    {
        return [
            'document_number' => 'QE/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'request_for_quotation_id' => RequestForQuotation::factory(),
            'status' => QuotationEvaluationStatus::Draft,
            'evaluated_by' => null,
            'evaluated_at' => null,
            'recommended_quotation_id' => null,
            'comments' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
