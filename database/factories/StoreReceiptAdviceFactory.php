<?php

namespace Database\Factories;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\ReceiptStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreReceiptAdvice>
 */
class StoreReceiptAdviceFactory extends Factory
{
    protected $model = StoreReceiptAdvice::class;

    public function definition(): array
    {
        return [
            'document_number' => 'SRA/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'purchase_order_id' => PurchaseOrder::factory()->approved(),
            'warehouse_id' => Warehouse::factory(),
            'supplier_id' => Supplier::factory(),
            'expected_delivery_date' => now()->addDays(3)->toDateString(),
            'vehicle_number' => fake()->bothify('KJA-###-??'),
            'driver_name' => fake()->name(),
            'driver_phone' => fake()->numerify('+234 80# ### ####'),
            'delivery_note_number' => fake()->bothify('DN-####'),
            'status' => ReceiptStatus::Draft,
            'received_by' => User::factory(),
            'verified_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    public function pendingVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReceiptStatus::PendingVerification,
        ]);
    }

    public function pendingInspection(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReceiptStatus::PendingInspection,
        ]);
    }
}
