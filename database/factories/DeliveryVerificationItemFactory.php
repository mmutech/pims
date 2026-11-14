<?php

namespace Database\Factories;

use App\Domain\Receiving\Models\DeliveryVerification;
use App\Domain\Receiving\Models\DeliveryVerificationItem;
use App\Domain\Receiving\Models\StoreReceiptAdviceItem;
use App\Enums\DeliveryCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryVerificationItem>
 */
class DeliveryVerificationItemFactory extends Factory
{
    protected $model = DeliveryVerificationItem::class;

    public function definition(): array
    {
        $verified = fake()->numberBetween(1, 100).'.0000';

        return [
            'verification_id' => DeliveryVerification::factory(),
            'sra_item_id' => StoreReceiptAdviceItem::factory(),
            'verified_quantity' => $verified,
            'damaged_quantity' => '0.0000',
            'short_quantity' => '0.0000',
            'condition' => DeliveryCondition::Good,
            'notes' => null,
        ];
    }
}
