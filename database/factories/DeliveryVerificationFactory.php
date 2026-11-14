<?php

namespace Database\Factories;

use App\Domain\Receiving\Models\DeliveryVerification;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\DeliveryVerificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryVerification>
 */
class DeliveryVerificationFactory extends Factory
{
    protected $model = DeliveryVerification::class;

    public function definition(): array
    {
        return [
            'document_number' => 'DV/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'sra_id' => StoreReceiptAdvice::factory(),
            'warehouse_id' => Warehouse::factory(),
            'verified_by' => User::factory(),
            'verified_at' => now(),
            'status' => DeliveryVerificationStatus::Verified,
            'discrepancy_summary' => null,
        ];
    }
}
