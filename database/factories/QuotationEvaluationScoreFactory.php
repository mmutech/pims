<?php

namespace Database\Factories;

use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Domain\Procurement\Models\QuotationEvaluationScore;
use App\Domain\Procurement\Models\SupplierQuotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationEvaluationScore>
 */
class QuotationEvaluationScoreFactory extends Factory
{
    protected $model = QuotationEvaluationScore::class;

    public function definition(): array
    {
        $weight = fake()->numberBetween(10, 40).'.00';
        $score = fake()->numberBetween(50, 100).'.00';

        return [
            'quotation_evaluation_id' => QuotationEvaluation::factory(),
            'supplier_quotation_id' => SupplierQuotation::factory(),
            'criterion' => fake()->randomElement(['price', 'quality', 'delivery', 'compliance']),
            'weight' => $weight,
            'score' => $score,
            'weighted_score' => '0.00',
        ];
    }
}
