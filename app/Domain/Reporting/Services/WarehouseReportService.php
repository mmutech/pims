<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Reporting\Support\ReportScope;
use App\DTOs\Reporting\ReportCriteria;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Warehouse reporting: what each warehouse holds and how much stock moves
 * through it inside a period. Both reports honour the user's warehouse scope.
 */
class WarehouseReportService
{
    public function utilization(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = InventoryBalance::query()
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->select([
                'inventory_balances.warehouse_id',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ])
            ->selectRaw('COUNT(DISTINCT inventory_balances.item_id) as item_count')
            ->selectRaw('SUM(inventory_balances.on_hand_quantity) as quantity')
            ->selectRaw('SUM(inventory_balances.reserved_quantity) as reserved_quantity')
            ->selectRaw('SUM(inventory_balances.available_quantity) as available_quantity')
            ->selectRaw('SUM(inventory_balances.total_value) as total_value')
            ->whereNull('warehouses.deleted_at')
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('inventory_balances.warehouse_id', $criteria->warehouseId))
            ->when($criteria->itemId !== null, fn (Builder $query) => $query->where('inventory_balances.item_id', $criteria->itemId))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($criteria): void {
                $query->where('warehouses.code', 'like', '%'.$criteria->search.'%')
                    ->orWhere('warehouses.name', 'like', '%'.$criteria->search.'%');
            }))
            ->groupBy('inventory_balances.warehouse_id', 'warehouses.code', 'warehouses.name')
            ->orderByDesc('total_value');

        ReportScope::warehouse($query, $user, 'inventory_balances.warehouse_id');

        return $query->paginate($criteria->perPage)->through(function (InventoryBalance $row): InventoryBalance {
            $row->item_count = (int) $row->item_count;
            $row->quantity = Decimal::normalize((string) $row->quantity, Decimal::QUANTITY_SCALE);
            $row->reserved_quantity = Decimal::normalize((string) $row->reserved_quantity, Decimal::QUANTITY_SCALE);
            $row->available_quantity = Decimal::normalize((string) $row->available_quantity, Decimal::QUANTITY_SCALE);
            $row->total_value = Decimal::normalize((string) $row->total_value, Decimal::MONEY_SCALE);

            return $row;
        });
    }

    public function throughput(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = StockLedger::query()
            ->join('warehouses', 'warehouses.id', '=', 'stock_ledger.warehouse_id')
            ->select([
                'stock_ledger.warehouse_id',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ])
            ->selectRaw('SUM(stock_ledger.quantity_in) as inbound_quantity')
            ->selectRaw('SUM(stock_ledger.quantity_out) as outbound_quantity')
            ->selectRaw('SUM(stock_ledger.value_in) as inbound_value')
            ->selectRaw('SUM(stock_ledger.value_out) as outbound_value')
            ->whereNull('warehouses.deleted_at')
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('stock_ledger.warehouse_id', $criteria->warehouseId))
            ->when($criteria->itemId !== null, fn (Builder $query) => $query->where('stock_ledger.item_id', $criteria->itemId))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($criteria): void {
                $query->where('warehouses.code', 'like', '%'.$criteria->search.'%')
                    ->orWhere('warehouses.name', 'like', '%'.$criteria->search.'%');
            }))
            ->groupBy('stock_ledger.warehouse_id', 'warehouses.code', 'warehouses.name')
            ->orderBy('warehouses.name');

        ReportScope::warehouse($query, $user, 'stock_ledger.warehouse_id');
        ReportScope::datesOnTimestamp($query, $criteria, 'stock_ledger.posted_at');

        return $query->paginate($criteria->perPage)->through(function (StockLedger $row): StockLedger {
            $inQuantity = Decimal::normalize((string) $row->inbound_quantity, Decimal::QUANTITY_SCALE);
            $outQuantity = Decimal::normalize((string) $row->outbound_quantity, Decimal::QUANTITY_SCALE);
            $inValue = Decimal::normalize((string) $row->inbound_value, Decimal::MONEY_SCALE);
            $outValue = Decimal::normalize((string) $row->outbound_value, Decimal::MONEY_SCALE);

            $row->inbound_quantity = $inQuantity;
            $row->outbound_quantity = $outQuantity;
            $row->net_quantity = Decimal::subtract($inQuantity, $outQuantity, Decimal::QUANTITY_SCALE);
            $row->inbound_value = $inValue;
            $row->outbound_value = $outValue;
            $row->net_value = Decimal::subtract($inValue, $outValue, Decimal::MONEY_SCALE);

            return $row;
        });
    }

    /**
     * @return array<string, string>
     */
    public function utilizationSummary(?User $user, ReportCriteria $criteria): array
    {
        $query = InventoryBalance::query()
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->whereNull('warehouses.deleted_at')
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('inventory_balances.warehouse_id', $criteria->warehouseId))
            ->when($criteria->itemId !== null, fn (Builder $query) => $query->where('inventory_balances.item_id', $criteria->itemId));

        ReportScope::warehouse($query, $user, 'inventory_balances.warehouse_id');

        $totals = $query->selectRaw('COUNT(DISTINCT inventory_balances.warehouse_id) as warehouses')
            ->selectRaw('COUNT(DISTINCT inventory_balances.item_id) as items')
            ->selectRaw('SUM(inventory_balances.on_hand_quantity) as quantity')
            ->selectRaw('SUM(inventory_balances.total_value) as total_value')
            ->first();

        return [
            'Warehouses' => (string) (int) ($totals->warehouses ?? 0),
            'Items' => (string) (int) ($totals->items ?? 0),
            'On hand' => $this->quantity((string) ($totals->quantity ?? '0')),
            'Value' => $this->money((string) ($totals->total_value ?? '0')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function throughputSummary(?User $user, ReportCriteria $criteria): array
    {
        $query = StockLedger::query();

        ReportScope::warehouse($query, $user, 'warehouse_id');
        ReportScope::datesOnTimestamp($query, $criteria, 'posted_at');

        $totals = $query->selectRaw('SUM(quantity_in) as inbound_quantity')
            ->selectRaw('SUM(quantity_out) as outbound_quantity')
            ->selectRaw('SUM(value_in) as inbound_value')
            ->selectRaw('SUM(value_out) as outbound_value')
            ->first();

        $in = Decimal::normalize((string) ($totals->inbound_quantity ?? '0'), Decimal::QUANTITY_SCALE);
        $out = Decimal::normalize((string) ($totals->outbound_quantity ?? '0'), Decimal::QUANTITY_SCALE);
        $inValue = Decimal::normalize((string) ($totals->inbound_value ?? '0'), Decimal::MONEY_SCALE);
        $outValue = Decimal::normalize((string) ($totals->outbound_value ?? '0'), Decimal::MONEY_SCALE);

        return [
            'Qty in' => $this->quantity($in),
            'Qty out' => $this->quantity($out),
            'Net qty' => $this->quantity(Decimal::subtract($in, $out, Decimal::QUANTITY_SCALE)),
            'Net value' => $this->money(Decimal::subtract($inValue, $outValue, Decimal::MONEY_SCALE)),
        ];
    }

    private function quantity(string|int|null $value): string
    {
        return rtrim(rtrim(Decimal::normalize((string) ($value ?? '0'), Decimal::QUANTITY_SCALE), '0'), '.') ?: '0';
    }

    private function money(string|int|null $value): string
    {
        return number_format((float) Decimal::normalize((string) ($value ?? '0'), Decimal::MONEY_SCALE), 2);
    }
}
