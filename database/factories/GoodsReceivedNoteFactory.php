<?php

namespace Database\Factories;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\GoodsReceivedNoteStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceivedNote>
 */
class GoodsReceivedNoteFactory extends Factory
{
    protected $model = GoodsReceivedNote::class;

    public function definition(): array
    {
        return [
            'document_number' => 'GRN/'.now()->format('Y').'/'.fake()->unique()->numerify('#####'),
            'purchase_order_id' => PurchaseOrder::factory()->approved(),
            'sra_id' => StoreReceiptAdvice::factory(),
            'quality_inspection_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'supplier_id' => Supplier::factory(),
            'received_date' => now()->toDateString(),
            'status' => GoodsReceivedNoteStatus::Draft,
            'posted_at' => null,
            'posted_by' => null,
            'reversal_of_id' => null,
            'stock_transaction_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
