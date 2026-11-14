<?php

namespace App\Jobs\Inventory;

use App\Domain\Inventory\Services\InventoryReconciliationService;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that the append-only ledger, balance projections and open
 * reservations still agree. A discrepancy fails the job so it reaches the
 * failed-jobs table and monitoring.
 */
class ReconcileInventoryBalances implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public ?int $warehouseId = null) {}

    public function handle(InventoryReconciliationService $reconciliation): void
    {
        $report = $reconciliation->reconcile($this->warehouseId);

        if ($reconciliation->isReconciled($report)) {
            Log::info('Inventory reconciliation passed.', [
                'warehouse_id' => $this->warehouseId,
                'balances' => $report['balances'],
                'ledger_lines' => $report['ledger_lines'],
            ]);

            return;
        }

        Log::error('Inventory reconciliation failed.', [
            'warehouse_id' => $this->warehouseId,
            'quantity_discrepancies' => $report['quantity_discrepancies'],
            'value_discrepancies' => $report['value_discrepancies'],
            'reserved_discrepancies' => $report['reserved_discrepancies'],
        ]);

        throw new DomainException(sprintf(
            'Inventory reconciliation failed: %d quantity, %d value and %d reservation discrepancies.',
            count($report['quantity_discrepancies']),
            count($report['value_discrepancies']),
            count($report['reserved_discrepancies']),
        ));
    }
}
