<?php

namespace Database\Factories;

use App\Domain\Receiving\Models\QualityInspectionFinding;
use App\Domain\Receiving\Models\QualityInspectionItem;
use App\Enums\InspectionFindingSeverity;
use App\Enums\InspectionFindingType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityInspectionFinding>
 */
class QualityInspectionFindingFactory extends Factory
{
    protected $model = QualityInspectionFinding::class;

    public function definition(): array
    {
        return [
            'quality_inspection_item_id' => QualityInspectionItem::factory(),
            'finding_type' => InspectionFindingType::Other,
            'severity' => InspectionFindingSeverity::Minor,
            'description' => fake()->sentence(),
            'evidence_path' => null,
            'created_by' => User::factory(),
        ];
    }
}
