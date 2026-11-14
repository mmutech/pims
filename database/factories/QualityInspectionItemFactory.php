<?php

namespace Database\Factories;

use App\Domain\Receiving\Models\QualityInspection;
use App\Domain\Receiving\Models\QualityInspectionItem;
use App\Domain\Receiving\Models\StoreReceiptAdviceItem;
use App\Enums\InspectionResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityInspectionItem>
 */
class QualityInspectionItemFactory extends Factory
{
    protected $model = QualityInspectionItem::class;

    public function definition(): array
    {
        $inspected = fake()->numberBetween(1, 100).'.0000';

        return [
            'quality_inspection_id' => QualityInspection::factory(),
            'sra_item_id' => StoreReceiptAdviceItem::factory(),
            'inspected_quantity' => $inspected,
            'accepted_quantity' => $inspected,
            'rejected_quantity' => '0.0000',
            'quarantined_quantity' => '0.0000',
            'result' => InspectionResult::Passed,
            'notes' => null,
        ];
    }
}
