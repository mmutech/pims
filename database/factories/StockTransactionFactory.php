<?php

namespace Database\Factories;

use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\StockMovementType;
use App\Enums\StockTransactionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransaction>
 */
class StockTransactionFactory extends Factory
{
    protected $model = StockTransaction::class;

    public function definition(): array
    {
        return [
            'transaction_number' => 'STX/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'transaction_type' => StockMovementType::Receipt,
            'source_type' => 'manual',
            'source_id' => fake()->numberBetween(1, 1000),
            'idempotency_key' => fake()->unique()->uuid(),
            'warehouse_id' => Warehouse::factory(),
            'destination_warehouse_id' => null,
            'status' => StockTransactionStatus::Posted,
            'posted_by' => User::factory(),
            'posted_at' => now(),
            'reversed_by_transaction_id' => null,
            'reversal_reason' => null,
            'metadata' => null,
        ];
    }
}
