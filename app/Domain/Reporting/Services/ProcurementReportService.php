<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Reporting\Support\ReportScope;
use App\DTOs\Reporting\ReportCriteria;
use App\Enums\PurchaseOrderStatus;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Procurement reporting. Purchase orders have no explicit order date column,
 * so the report uses the creation date as the committed order date. Row-level
 * visibility follows the same warehouse/creator rule as the procurement
 * screens.
 */
class ProcurementReportService
{
    public function orders(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'purchase_orders.delivery_warehouse_id')
            ->select([
                'purchase_orders.id',
                'purchase_orders.document_number',
                'purchase_orders.currency',
                'purchase_orders.status',
                'suppliers.name as supplier_name',
                'warehouses.name as warehouse_name',
            ])
            ->selectRaw('DATE(purchase_orders.created_at) as order_date')
            ->selectRaw('purchase_orders.total_amount as total_amount')
            ->whereNull('suppliers.deleted_at')
            ->when($criteria->supplierId !== null, fn (Builder $query) => $query->where('purchase_orders.supplier_id', $criteria->supplierId))
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('purchase_orders.delivery_warehouse_id', $criteria->warehouseId))
            ->when($criteria->status !== null, fn (Builder $query) => $query->where('purchase_orders.status', $criteria->status))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($criteria): void {
                $query->where('purchase_orders.document_number', 'like', '%'.$criteria->search.'%')
                    ->orWhere('suppliers.name', 'like', '%'.$criteria->search.'%');
            }));

        if ($user !== null) {
            $query->visibleTo($user);
        }

        ReportScope::dates($query, $criteria, 'purchase_orders.created_at');

        return $query->orderByDesc('purchase_orders.created_at')
            ->orderByDesc('purchase_orders.id')
            ->paginate($criteria->perPage)
            ->through(function (PurchaseOrder $row): PurchaseOrder {
                $row->order_date = $row->order_date !== null
                    ? CarbonImmutable::parse((string) $row->order_date)->toDateString()
                    : null;
                $row->status_label = $this->statusLabel($row->status);
                $row->total_amount = Decimal::normalize((string) $row->total_amount, Decimal::MONEY_SCALE);

                return $row;
            });
    }

    public function supplierSpend(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->select([
                'purchase_orders.supplier_id',
                'purchase_orders.currency',
                'suppliers.name as supplier_name',
            ])
            ->selectRaw('COUNT(purchase_orders.id) as orders')
            ->selectRaw('SUM(purchase_orders.total_amount) as total_amount')
            ->selectRaw('MAX(DATE(purchase_orders.created_at)) as last_order_date')
            ->whereNull('suppliers.deleted_at')
            ->when($criteria->supplierId !== null, fn (Builder $query) => $query->where('purchase_orders.supplier_id', $criteria->supplierId))
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('purchase_orders.delivery_warehouse_id', $criteria->warehouseId))
            ->when($criteria->status !== null, fn (Builder $query) => $query->where('purchase_orders.status', $criteria->status))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where('suppliers.name', 'like', '%'.$criteria->search.'%'))
            ->groupBy('purchase_orders.supplier_id', 'purchase_orders.currency', 'suppliers.name')
            ->orderByDesc('total_amount');

        if ($user !== null) {
            $query->visibleTo($user);
        }

        ReportScope::dates($query, $criteria, 'purchase_orders.created_at');

        return $query->paginate($criteria->perPage)
            ->through(function (PurchaseOrder $row): PurchaseOrder {
                $row->orders = (int) $row->orders;
                $row->total_amount = Decimal::normalize((string) $row->total_amount, Decimal::MONEY_SCALE);
                $row->last_order_date = $row->last_order_date !== null
                    ? CarbonImmutable::parse((string) $row->last_order_date)->toDateString()
                    : null;

                return $row;
            });
    }

    /**
     * @return array<string, string>
     */
    public function ordersSummary(?User $user, ReportCriteria $criteria): array
    {
        $query = PurchaseOrder::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->whereNull('suppliers.deleted_at')
            ->when($criteria->supplierId !== null, fn (Builder $query) => $query->where('purchase_orders.supplier_id', $criteria->supplierId))
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('purchase_orders.delivery_warehouse_id', $criteria->warehouseId))
            ->when($criteria->status !== null, fn (Builder $query) => $query->where('purchase_orders.status', $criteria->status));

        if ($user !== null) {
            $query->visibleTo($user);
        }

        ReportScope::dates($query, $criteria, 'purchase_orders.created_at');

        $totals = $query->selectRaw('COUNT(purchase_orders.id) as orders')
            ->selectRaw('SUM(purchase_orders.total_amount) as total_amount')
            ->selectRaw('COUNT(DISTINCT purchase_orders.supplier_id) as suppliers')
            ->first();

        return [
            'Orders' => (string) (int) ($totals->orders ?? 0),
            'Suppliers' => (string) (int) ($totals->suppliers ?? 0),
            'Committed value' => $this->money((string) ($totals->total_amount ?? '0')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function supplierSpendSummary(?User $user, ReportCriteria $criteria): array
    {
        $page = $this->supplierSpend($user, $criteria->withPerPage(200));

        $total = '0';

        foreach ($page->items() as $row) {
            $total = Decimal::add($total, (string) $row->total_amount, Decimal::MONEY_SCALE);
        }

        return [
            'Suppliers' => (string) $page->total(),
            'Committed value' => $this->money($total),
        ];
    }

    private function statusLabel(mixed $status): string
    {
        if ($status instanceof PurchaseOrderStatus) {
            return $status->label();
        }

        return PurchaseOrderStatus::tryFrom((string) $status)?->label() ?? (string) $status;
    }

    private function money(string|int|null $value): string
    {
        return number_format((float) Decimal::normalize((string) ($value ?? '0'), Decimal::MONEY_SCALE), 2);
    }
}
