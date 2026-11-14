<?php

namespace Database\Factories;

use App\Domain\Receiving\Models\QualityInspection;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\InspectionMethod;
use App\Enums\QualityInspectionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityInspection>
 */
class QualityInspectionFactory extends Factory
{
    protected $model = QualityInspection::class;

    public function definition(): array
    {
        return [
            'document_number' => 'QI/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'sra_id' => StoreReceiptAdvice::factory(),
            'delivery_verification_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'inspector_id' => User::factory(),
            'inspection_date' => now()->toDateString(),
            'status' => QualityInspectionStatus::Pending,
            'method' => InspectionMethod::Visual,
            'sample_size' => null,
            'summary' => null,
            'completed_at' => null,
        ];
    }
}
