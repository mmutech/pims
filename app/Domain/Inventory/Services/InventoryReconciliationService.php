<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockReservationItem;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Compares the append-only ledger with the balance projections.
 *
 * The ledger is authoritative for quantity and value. Balances are derived and
 * may only be changed by InventoryPostingService, so any difference is a defect.
 */
class InventoryReconciliationService
{
    /**
     * @return array{
     *     balances: int,
     *     ledger_lines: int,
     *     quantity_discrepancies: list<array<string, mixed>>,
     *     value_discrepancies: list<array<string, mixed>>,
     *     reserved_discrepancies: list<array<string, mixed>>
     * }
     */
    public function reconcile(?int $warehouseId = null): array
    {
        $ledger = DB::table('stock_ledger')
            ->selectRaw('warehouse_id, stock_dimension_id, item_id, COALESCE(batch_id, 0) as batch_key')
            ->selectRaw('SUM(quantity_in) - SUM(quantity_out) as quantity')
            ->selectRaw('SUM(value_in) - SUM(value_out) as value')
            ->selectRaw('COUNT(*) as line_count')
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->groupBy('warehouse_id', 'stock_dimension_id', 'item_id', DB::raw('COALESCE(batch_id, 0)'))
            ->get();

        $balances = InventoryBalance::query()
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->get();

        $ledgerByKey = $ledger->keyBy(fn ($row): string => $this->key(
            (int) $row->warehouse_id,
            (int) $row->stock_dimension_id,
            (int) $row->item_id,
            (int) $row->batch_key,
        ));

        $balanceByKey = $balances->keyBy(fn (InventoryBalance $balance): string => $this->key(
            $balance->warehouse_id,
            $balance->stock_dimension_id,
            $balance->item_id,
            $balance->batch_id ?? 0,
        ));

        $quantityDiscrepancies = [];
        $valueDiscrepancies = [];

        $keys = $ledgerByKey->keys()->merge($balanceByKey->keys())->unique();

        foreach ($keys as $key) {
            $ledgerRow = $ledgerByKey->get($key);
            $balance = $balanceByKey->get($key);

            $ledgerQuantity = Decimal::normalize((string) ($ledgerRow->quantity ?? '0'), Decimal::QUANTITY_SCALE);
            $ledgerValue = Decimal::normalize((string) ($ledgerRow->value ?? '0'), Decimal::MONEY_SCALE);
            $balanceQuantity = Decimal::normalize((string) ($balance->on_hand_quantity ?? '0'), Decimal::QUANTITY_SCALE);
            $balanceValue = Decimal::normalize((string) ($balance->total_value ?? '0'), Decimal::MONEY_SCALE);

            if (Decimal::compare($ledgerQuantity, $balanceQuantity, Decimal::QUANTITY_SCALE) !== 0) {
                $quantityDiscrepancies[] = [
                    'key' => $key,
                    'ledger_quantity' => $ledgerQuantity,
                    'balance_quantity' => $balanceQuantity,
                ];
            }

            if (Decimal::compare($ledgerValue, $balanceValue, Decimal::MONEY_SCALE) !== 0) {
                $valueDiscrepancies[] = [
                    'key' => $key,
                    'ledger_value' => $ledgerValue,
                    'balance_value' => $balanceValue,
                ];
            }
        }

        $reservedDiscrepancies = $this->reservedDiscrepancies($warehouseId);

        return [
            'balances' => $balances->count(),
            'ledger_lines' => (int) $ledger->sum('line_count'),
            'quantity_discrepancies' => $quantityDiscrepancies,
            'value_discrepancies' => $valueDiscrepancies,
            'reserved_discrepancies' => $reservedDiscrepancies,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function isReconciled(array $report): bool
    {
        return $report['quantity_discrepancies'] === []
            && $report['value_discrepancies'] === []
            && $report['reserved_discrepancies'] === [];
    }

    public function assertReconciled(?int $warehouseId = null): array
    {
        $report = $this->reconcile($warehouseId);

        if (! $this->isReconciled($report)) {
            throw new DomainException(sprintf(
                'Inventory reconciliation failed: %d quantity, %d value and %d reservation discrepancies.',
                count($report['quantity_discrepancies']),
                count($report['value_discrepancies']),
                count($report['reserved_discrepancies']),
            ));
        }

        return $report;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reservedDiscrepancies(?int $warehouseId): array
    {
        $reservations = StockReservationItem::query()
            ->selectRaw('stock_reservations.warehouse_id')
            ->selectRaw('stock_reservation_items.item_id, stock_reservation_items.stock_dimension_id')
            ->selectRaw('COALESCE(stock_reservation_items.batch_id, 0) as batch_key')
            ->selectRaw('SUM(stock_reservation_items.quantity - stock_reservation_items.consumed_quantity - stock_reservation_items.released_quantity) as outstanding')
            ->join('stock_reservations', 'stock_reservations.id', '=', 'stock_reservation_items.stock_reservation_id')
            ->when($warehouseId !== null, fn ($query) => $query->where('stock_reservations.warehouse_id', $warehouseId))
            ->whereIn('stock_reservations.status', ['active', 'partially_consumed'])
            ->groupBy('stock_reservations.warehouse_id', 'stock_reservation_items.item_id', 'stock_reservation_items.stock_dimension_id', DB::raw('COALESCE(stock_reservation_items.batch_id, 0)'))
            ->get();

        $reservationByKey = $reservations->keyBy(fn ($row): string => $this->key(
            (int) $row->warehouse_id,
            (int) $row->stock_dimension_id,
            (int) $row->item_id,
            (int) $row->batch_key,
        ));

        $balances = InventoryBalance::query()
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->where('reserved_quantity', '>', 0)
            ->get();

        $balanceByKey = $balances->keyBy(fn (InventoryBalance $balance): string => $this->key(
            $balance->warehouse_id,
            $balance->stock_dimension_id,
            $balance->item_id,
            $balance->batch_id ?? 0,
        ));

        $keys = $reservationByKey->keys()->merge($balanceByKey->keys())->unique();
        $discrepancies = [];

        foreach ($keys as $key) {
            $expected = Decimal::normalize(
                (string) ($balanceByKey->get($key)->reserved_quantity ?? '0'),
                Decimal::QUANTITY_SCALE,
            );
            $actual = Decimal::normalize(
                (string) ($reservationByKey->get($key)->outstanding ?? '0'),
                Decimal::QUANTITY_SCALE,
            );

            if (Decimal::compare($expected, $actual, Decimal::QUANTITY_SCALE) !== 0) {
                $discrepancies[] = [
                    'key' => $key,
                    'balance_reserved' => $expected,
                    'reservation_outstanding' => $actual,
                ];
            }
        }

        return $discrepancies;
    }

    private function key(int $warehouseId, int $dimensionId, int $itemId, int $batchKey): string
    {
        return "{$warehouseId}|{$dimensionId}|{$itemId}|{$batchKey}";
    }
}
