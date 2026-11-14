<?php

namespace App\Jobs\Inventory;

use App\Domain\Inventory\Services\InventoryValuationService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Writes (or rewrites) the valuation snapshot for a business date.
 */
class GenerateInventoryValuationSnapshots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ?string $date = null,
        public ?int $warehouseId = null,
        public ?int $createdBy = null,
    ) {}

    public function handle(InventoryValuationService $valuation): void
    {
        $actor = $this->createdBy !== null
            ? User::query()->find($this->createdBy)
            : null;

        $date = $this->date ?? now()->toDateString();

        $count = $valuation->snapshot($date, $this->warehouseId, $actor);

        Log::info('Generated inventory valuation snapshots.', [
            'date' => $date,
            'warehouse_id' => $this->warehouseId,
            'rows' => $count,
        ]);
    }
}
