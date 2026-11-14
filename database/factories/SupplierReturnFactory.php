<?php

namespace Database\Factories;

use App\Domain\Receiving\Models\SupplierReturn;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\ReturnStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierReturn>
 */
class SupplierReturnFactory extends Factory
{
    protected $model = SupplierReturn::class;

    public function definition(): array
    {
        return [
            'document_number' => 'SRT/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'supplier_id' => Supplier::factory(),
            'grn_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'reason' => fake()->sentence(),
            'status' => ReturnStatus::Draft,
            'return_date' => now()->toDateString(),
            'credit_note_number' => null,
            'posted_at' => null,
            'posted_by' => null,
            'created_by' => User::factory(),
        ];
    }
}
