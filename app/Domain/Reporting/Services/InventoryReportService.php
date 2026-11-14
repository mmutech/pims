<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Reporting\Support\ReportScope;
use App\DTOs\Reporting\ReportCriteria;
use App\Enums\StockMovementType;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Inventory reporting. Every monetary and quantity aggregate stays in
 * fixed-point decimals; SQL decimals are returned as strings and normalised
 * with App\Support\Decimal before display.
 */
class InventoryReportService
{
    public function valuation(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = InventoryBalance::query()
            ->join('items', 'items.id', '=', 'inventory_balances.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->select([
                'inventory_balances.item_id',
                'inventory_balances.warehouse_id',
                'items.code as item_code',
                'items.name as item_name',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ])
            ->selectRaw('SUM(inventory_balances.on_hand_quantity) as quantity')
            ->selectRaw('SUM(inventory_balances.reserved_quantity) as reserved_quantity')
            ->selectRaw('SUM(inventory_balances.total_value) as total_value')
            ->selectRaw('MAX(inventory_balances.last_movement_at) as last_movement_at')
            ->whereNull('items.deleted_at')
            ->whereNull('warehouses.deleted_at');

        $this->applyBalanceFilters($query, $criteria);

        ReportScope::warehouse($query, $user, 'inventory_balances.warehouse_id');

        $query->groupBy([
            'inventory_balances.item_id',
            'inventory_balances.warehouse_id',
            'items.code',
            'items.name',
            'warehouses.code',
            'warehouses.name',
        ])->orderBy('warehouses.name')->orderBy('items.code');

        return $query->paginate($criteria->perPage)->through(function (InventoryBalance $row): InventoryBalance {
            $quantity = Decimal::normalize((string) $row->quantity, Decimal::QUANTITY_SCALE);
            $value = Decimal::normalize((string) $row->total_value, Decimal::MONEY_SCALE);

            $row->quantity = $quantity;
            $row->reserved_quantity = Decimal::normalize((string) $row->reserved_quantity, Decimal::QUANTITY_SCALE);
            $row->total_value = $value;
            $row->unit_cost = Decimal::isPositive($quantity)
                ? Decimal::divide($value, $quantity, Decimal::COST_SCALE)
                : Decimal::normalize('0', Decimal::COST_SCALE);
            $row->last_movement_at = $this->dateTime($row->last_movement_at);

            return $row;
        });
    }

    public function movements(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = StockLedger::query()
            ->select('movement_type')
            ->selectRaw('COUNT(*) as movements')
            ->selectRaw('SUM(quantity_in) as inbound_quantity')
            ->selectRaw('SUM(quantity_out) as outbound_quantity')
            ->selectRaw('SUM(value_in) as inbound_value')
            ->selectRaw('SUM(value_out) as outbound_value')
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('warehouse_id', $criteria->warehouseId))
            ->when($criteria->itemId !== null, fn (Builder $query) => $query->where('item_id', $criteria->itemId))
            ->when($criteria->search !== null, fn (Builder $query) => $query->whereIn(
                'item_id',
                Item::query()->select('id')->where(function (Builder $query) use ($criteria): void {
                    $query->where('code', 'like', '%'.$criteria->search.'%')
                        ->orWhere('name', 'like', '%'.$criteria->search.'%');
                }),
            ))
            ->groupBy('movement_type')
            ->orderBy('movement_type');

        ReportScope::warehouse($query, $user, 'warehouse_id');
        ReportScope::datesOnTimestamp($query, $criteria, 'posted_at');

        return $query->paginate($criteria->perPage)->through(function (StockLedger $row): StockLedger {
            $inQuantity = Decimal::normalize((string) $row->inbound_quantity, Decimal::QUANTITY_SCALE);
            $outQuantity = Decimal::normalize((string) $row->outbound_quantity, Decimal::QUANTITY_SCALE);
            $inValue = Decimal::normalize((string) $row->inbound_value, Decimal::MONEY_SCALE);
            $outValue = Decimal::normalize((string) $row->outbound_value, Decimal::MONEY_SCALE);

            $row->movement_label = StockMovementType::tryFrom((string) $row->getRawOriginal('movement_type'))?->label()
                ?? (string) $row->getRawOriginal('movement_type');
            $row->inbound_quantity = $inQuantity;
            $row->outbound_quantity = $outQuantity;
            $row->net_quantity = Decimal::subtract($inQuantity, $outQuantity, Decimal::QUANTITY_SCALE);
            $row->inbound_value = $inValue;
            $row->outbound_value = $outValue;
            $row->net_value = Decimal::subtract($inValue, $outValue, Decimal::MONEY_SCALE);
            $row->movements = (int) $row->movements;

            return $row;
        });
    }

    public function slowMoving(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $cutoff = CarbonImmutable::now()->subDays($criteria->movementWindowDays)->startOfDay();

        $query = InventoryBalance::query()
            ->join('items', 'items.id', '=', 'inventory_balances.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->select([
                'inventory_balances.item_id',
                'inventory_balances.warehouse_id',
                'items.code as item_code',
                'items.name as item_name',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ])
            ->selectRaw('SUM(inventory_balances.on_hand_quantity) as quantity')
            ->selectRaw('SUM(inventory_balances.total_value) as total_value')
            ->selectRaw('MAX(inventory_balances.last_movement_at) as last_movement_at')
            ->where('inventory_balances.on_hand_quantity', '>', 0)
            ->whereNull('items.deleted_at')
            ->whereNull('warehouses.deleted_at')
            ->whereNotExists(function (Builder $query) use ($cutoff): void {
                $query->selectRaw('1')
                    ->from('stock_ledger')
                    ->whereColumn('stock_ledger.warehouse_id', 'inventory_balances.warehouse_id')
                    ->whereColumn('stock_ledger.item_id', 'inventory_balances.item_id')
                    ->where('stock_ledger.quantity_out', '>', 0)
                    ->where('stock_ledger.posted_at', '>=', $cutoff);
            });

        $this->applyBalanceFilters($query, $criteria);

        ReportScope::warehouse($query, $user, 'inventory_balances.warehouse_id');

        $query->groupBy([
            'inventory_balances.item_id',
            'inventory_balances.warehouse_id',
            'items.code',
            'items.name',
            'warehouses.code',
            'warehouses.name',
        ])->orderByDesc('total_value');

        return $query->paginate($criteria->perPage)->through(function (InventoryBalance $row): InventoryBalance {
            $row->quantity = Decimal::normalize((string) $row->quantity, Decimal::QUANTITY_SCALE);
            $row->total_value = Decimal::normalize((string) $row->total_value, Decimal::MONEY_SCALE);
            $row->last_movement_at = $this->dateTime($row->last_movement_at);
            $row->days_since_movement = $row->last_movement_at !== null
                ? (int) CarbonImmutable::parse($row->last_movement_at)->diffInDays(CarbonImmutable::now())
                : null;

            return $row;
        });
    }

    public function expiring(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $limit = $criteria->to !== null
            ? CarbonImmutable::parse($criteria->to)->endOfDay()
            : CarbonImmutable::now()->addDays($criteria->expiryWindowDays)->endOfDay();

        $query = InventoryBalance::query()
            ->join('item_batches', 'item_batches.id', '=', 'inventory_balances.batch_id')
            ->join('items', 'items.id', '=', 'inventory_balances.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->select([
                'inventory_balances.item_id',
                'inventory_balances.warehouse_id',
                'inventory_balances.batch_id',
                'items.code as item_code',
                'items.name as item_name',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
                'item_batches.batch_number',
                'item_batches.expiry_date',
            ])
            ->selectRaw('SUM(inventory_balances.on_hand_quantity) as quantity')
            ->selectRaw('SUM(inventory_balances.total_value) as total_value')
            ->whereNotNull('item_batches.expiry_date')
            ->where('inventory_balances.on_hand_quantity', '>', 0)
            ->whereDate('item_batches.expiry_date', '<=', $limit->toDateString())
            ->whereNull('items.deleted_at')
            ->whereNull('warehouses.deleted_at')
            ->when(
                $criteria->from !== null,
                fn (Builder $query) => $query->whereDate('item_batches.expiry_date', '>=', $criteria->from),
            )
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('inventory_balances.warehouse_id', $criteria->warehouseId))
            ->when($criteria->itemId !== null, fn (Builder $query) => $query->where('inventory_balances.item_id', $criteria->itemId))
            ->when($criteria->categoryId !== null, fn (Builder $query) => $query->where('items.category_id', $criteria->categoryId))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($criteria): void {
                $query->where('items.code', 'like', '%'.$criteria->search.'%')
                    ->orWhere('items.name', 'like', '%'.$criteria->search.'%')
                    ->orWhere('item_batches.batch_number', 'like', '%'.$criteria->search.'%');
            }));

        ReportScope::warehouse($query, $user, 'inventory_balances.warehouse_id');

        $query->groupBy([
            'inventory_balances.item_id',
            'inventory_balances.warehouse_id',
            'inventory_balances.batch_id',
            'items.code',
            'items.name',
            'warehouses.code',
            'warehouses.name',
            'item_batches.batch_number',
            'item_batches.expiry_date',
        ])->orderBy('item_batches.expiry_date')->orderBy('items.code');

        return $query->paginate($criteria->perPage)->through(function (InventoryBalance $row): InventoryBalance {
            $row->quantity = Decimal::normalize((string) $row->quantity, Decimal::QUANTITY_SCALE);
            $row->total_value = Decimal::normalize((string) $row->total_value, Decimal::MONEY_SCALE);

            $expiry = CarbonImmutable::parse((string) $row->expiry_date)->startOfDay();
            $days = CarbonImmutable::now()->startOfDay()->diffInDays($expiry, false);

            $row->expiry_date = $expiry->toDateString();
            $row->days_to_expiry = (int) $days;
            $row->expiry_status = $days < 0 ? 'Expired' : ($days <= 30 ? 'Expiring soon' : 'Expiring');

            return $row;
        });
    }

    public function reconciliation(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = $this->reconciliationBase($criteria);

        ReportScope::warehouse($query, $user, 'k.warehouse_id');

        if ($criteria->mismatchesOnly) {
            $this->whereMismatch($query);
        }

        return $query->orderBy('warehouses.name')->orderBy('items.code')
            ->paginate($criteria->perPage)
            ->through(function (object $row): object {
                $balanceQuantity = Decimal::normalize((string) $row->balance_quantity, Decimal::QUANTITY_SCALE);
                $ledgerQuantity = Decimal::subtract(
                    Decimal::normalize((string) $row->ledger_quantity_in, Decimal::QUANTITY_SCALE),
                    Decimal::normalize((string) $row->ledger_quantity_out, Decimal::QUANTITY_SCALE),
                    Decimal::QUANTITY_SCALE,
                );
                $balanceValue = Decimal::normalize((string) $row->balance_value, Decimal::MONEY_SCALE);
                $ledgerValue = Decimal::subtract(
                    Decimal::normalize((string) $row->ledger_value_in, Decimal::MONEY_SCALE),
                    Decimal::normalize((string) $row->ledger_value_out, Decimal::MONEY_SCALE),
                    Decimal::MONEY_SCALE,
                );

                $row->balance_quantity = $balanceQuantity;
                $row->ledger_quantity = $ledgerQuantity;
                $row->difference_quantity = Decimal::subtract($balanceQuantity, $ledgerQuantity, Decimal::QUANTITY_SCALE);
                $row->balance_value = $balanceValue;
                $row->ledger_value = $ledgerValue;
                $row->difference_value = Decimal::subtract($balanceValue, $ledgerValue, Decimal::MONEY_SCALE);

                return $row;
            });
    }

    /**
     * @return array<string, string>
     */
    public function valuationSummary(?User $user, ReportCriteria $criteria): array
    {
        $query = InventoryBalance::query()
            ->join('items', 'items.id', '=', 'inventory_balances.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->whereNull('items.deleted_at')
            ->whereNull('warehouses.deleted_at');

        $this->applyBalanceFilters($query, $criteria);
        ReportScope::warehouse($query, $user, 'inventory_balances.warehouse_id');

        $totals = $query->selectRaw('SUM(inventory_balances.on_hand_quantity) as quantity')
            ->selectRaw('SUM(inventory_balances.total_value) as total_value')
            ->selectRaw('COUNT(DISTINCT inventory_balances.item_id) as items')
            ->first();

        return [
            'Items' => (string) (int) ($totals->items ?? 0),
            'On hand' => $this->quantity($totals->quantity ?? '0'),
            'Value' => $this->money($totals->total_value ?? '0'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function movementsSummary(?User $user, ReportCriteria $criteria): array
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

    /**
     * @return array<string, string>
     */
    public function slowMovingSummary(?User $user, ReportCriteria $criteria): array
    {
        $page = $this->slowMoving($user, $criteria->withPerPage(200));

        return [
            'Items flagged' => (string) $page->total(),
            'Window' => $criteria->movementWindowDays.' days',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function expiringSummary(?User $user, ReportCriteria $criteria): array
    {
        $page = $this->expiring($user, $criteria->withPerPage(200));

        return [
            'Batch balances' => (string) $page->total(),
            'Window' => ($criteria->to ?? now()->addDays($criteria->expiryWindowDays)->toDateString()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function reconciliationSummary(?User $user, ReportCriteria $criteria): array
    {
        $base = $this->reconciliationBase($criteria);
        ReportScope::warehouse($base, $user, 'k.warehouse_id');

        $rows = (clone $base)->count();
        $mismatches = (clone $base)->where(function (Builder $query): void {
            $this->whereMismatch($query);
        })->count();

        return [
            'Warehouse/item pairs' => (string) $rows,
            'Mismatches' => (string) $mismatches,
            'Status' => $mismatches === 0 ? 'Reconciled' : 'Attention required',
        ];
    }

    private function reconciliationBase(ReportCriteria $criteria): Builder
    {
        $balances = DB::table('inventory_balances')
            ->select('warehouse_id', 'item_id')
            ->selectRaw('SUM(on_hand_quantity) as balance_quantity')
            ->selectRaw('SUM(total_value) as balance_value')
            ->groupBy('warehouse_id', 'item_id');

        $ledger = DB::table('stock_ledger')
            ->select('warehouse_id', 'item_id')
            ->selectRaw('SUM(quantity_in) as ledger_quantity_in')
            ->selectRaw('SUM(quantity_out) as ledger_quantity_out')
            ->selectRaw('SUM(value_in) as ledger_value_in')
            ->selectRaw('SUM(value_out) as ledger_value_out')
            ->groupBy('warehouse_id', 'item_id');

        $keys = DB::table('inventory_balances')
            ->select('warehouse_id', 'item_id')
            ->union(DB::table('stock_ledger')->select('warehouse_id', 'item_id'));

        $query = DB::query()
            ->fromSub($keys, 'k')
            ->leftJoinSub($balances, 'b', function ($join): void {
                $join->on('b.warehouse_id', '=', 'k.warehouse_id')
                    ->on('b.item_id', '=', 'k.item_id');
            })
            ->leftJoinSub($ledger, 'l', function ($join): void {
                $join->on('l.warehouse_id', '=', 'k.warehouse_id')
                    ->on('l.item_id', '=', 'k.item_id');
            })
            ->join('items', 'items.id', '=', 'k.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'k.warehouse_id')
            ->select([
                'k.item_id',
                'k.warehouse_id',
                'items.code as item_code',
                'items.name as item_name',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ])
            ->selectRaw('COALESCE(b.balance_quantity, 0) as balance_quantity')
            ->selectRaw('COALESCE(b.balance_value, 0) as balance_value')
            ->selectRaw('COALESCE(l.ledger_quantity_in, 0) as ledger_quantity_in')
            ->selectRaw('COALESCE(l.ledger_quantity_out, 0) as ledger_quantity_out')
            ->selectRaw('COALESCE(l.ledger_value_in, 0) as ledger_value_in')
            ->selectRaw('COALESCE(l.ledger_value_out, 0) as ledger_value_out')
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('k.warehouse_id', $criteria->warehouseId))
            ->when($criteria->itemId !== null, fn (Builder $query) => $query->where('k.item_id', $criteria->itemId))
            ->when($criteria->categoryId !== null, fn (Builder $query) => $query->where('items.category_id', $criteria->categoryId))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($criteria): void {
                $query->where('items.code', 'like', '%'.$criteria->search.'%')
                    ->orWhere('items.name', 'like', '%'.$criteria->search.'%');
            }));

        return $query;
    }

    private function whereMismatch(Builder $query): void
    {
        $query->whereRaw(
            '(ABS(COALESCE(b.balance_quantity, 0) - (COALESCE(l.ledger_quantity_in, 0) - COALESCE(l.ledger_quantity_out, 0))) > 0.00005'
            .' OR ABS(COALESCE(b.balance_value, 0) - (COALESCE(l.ledger_value_in, 0) - COALESCE(l.ledger_value_out, 0))) > 0.005)',
        );
    }

    private function applyBalanceFilters(Builder $query, ReportCriteria $criteria): void
    {
        $query->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('inventory_balances.warehouse_id', $criteria->warehouseId))
            ->when($criteria->itemId !== null, fn (Builder $query) => $query->where('inventory_balances.item_id', $criteria->itemId))
            ->when($criteria->categoryId !== null, fn (Builder $query) => $query->where('items.category_id', $criteria->categoryId))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($criteria): void {
                $query->where('items.code', 'like', '%'.$criteria->search.'%')
                    ->orWhere('items.name', 'like', '%'.$criteria->search.'%');
            }));
    }

    private function quantity(string|int|null $value): string
    {
        return rtrim(rtrim(Decimal::normalize((string) ($value ?? '0'), Decimal::QUANTITY_SCALE), '0'), '.') ?: '0';
    }

    private function money(string|int|null $value): string
    {
        return number_format((float) Decimal::normalize((string) ($value ?? '0'), Decimal::MONEY_SCALE), 2);
    }

    private function dateTime(mixed $value): ?string
    {
        return $value !== null ? CarbonImmutable::parse((string) $value)->toDateTimeString() : null;
    }
}
