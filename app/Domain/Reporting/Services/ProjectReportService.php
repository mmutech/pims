<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Distribution\Models\StockIssueItem;
use App\Domain\Reporting\Support\ReportScope;
use App\DTOs\Reporting\ReportCriteria;
use App\Enums\IssueStatus;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Project reporting. Only posted stock issues count as consumption, so the
 * report always reconciles to the distribution ledger.
 */
class ProjectReportService
{
    public function consumption(?User $user, ReportCriteria $criteria): LengthAwarePaginator
    {
        $query = StockIssueItem::query()
            ->join('stock_issues', 'stock_issues.id', '=', 'stock_issue_items.stock_issue_id')
            ->join('material_requisitions', 'material_requisitions.id', '=', 'stock_issues.material_requisition_id')
            ->join('projects', 'projects.id', '=', 'material_requisitions.project_id')
            ->join('items', 'items.id', '=', 'stock_issue_items.item_id')
            ->select([
                'projects.code as project_code',
                'projects.name as project_name',
                'items.code as item_code',
                'items.name as item_name',
            ])
            ->selectRaw('SUM(stock_issue_items.issued_quantity) as quantity')
            ->selectRaw('SUM(stock_issue_items.total_cost) as total_value')
            ->where('stock_issues.status', IssueStatus::Posted->value)
            ->whereNull('projects.deleted_at')
            ->whereNull('items.deleted_at')
            ->when($criteria->projectId !== null, fn (Builder $query) => $query->where('projects.id', $criteria->projectId))
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('stock_issues.warehouse_id', $criteria->warehouseId))
            ->when($criteria->itemId !== null, fn (Builder $query) => $query->where('stock_issue_items.item_id', $criteria->itemId))
            ->when($criteria->categoryId !== null, fn (Builder $query) => $query->where('items.category_id', $criteria->categoryId))
            ->when($criteria->search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($criteria): void {
                $query->where('projects.code', 'like', '%'.$criteria->search.'%')
                    ->orWhere('projects.name', 'like', '%'.$criteria->search.'%')
                    ->orWhere('items.code', 'like', '%'.$criteria->search.'%')
                    ->orWhere('items.name', 'like', '%'.$criteria->search.'%');
            }))
            ->groupBy('projects.code', 'projects.name', 'items.code', 'items.name')
            ->orderBy('projects.name')
            ->orderBy('items.code');

        ReportScope::project($query, $user, 'material_requisitions.project_id');
        ReportScope::dates($query, $criteria, 'stock_issues.issue_date');

        return $query->paginate($criteria->perPage)->through(function (StockIssueItem $row): StockIssueItem {
            $row->quantity = Decimal::normalize((string) $row->quantity, Decimal::QUANTITY_SCALE);
            $row->total_value = Decimal::normalize((string) $row->total_value, Decimal::MONEY_SCALE);

            return $row;
        });
    }

    /**
     * @return array<string, string>
     */
    public function consumptionSummary(?User $user, ReportCriteria $criteria): array
    {
        $query = StockIssueItem::query()
            ->join('stock_issues', 'stock_issues.id', '=', 'stock_issue_items.stock_issue_id')
            ->join('material_requisitions', 'material_requisitions.id', '=', 'stock_issues.material_requisition_id')
            ->join('projects', 'projects.id', '=', 'material_requisitions.project_id')
            ->where('stock_issues.status', IssueStatus::Posted->value)
            ->whereNull('projects.deleted_at')
            ->when($criteria->projectId !== null, fn (Builder $query) => $query->where('projects.id', $criteria->projectId))
            ->when($criteria->warehouseId !== null, fn (Builder $query) => $query->where('stock_issues.warehouse_id', $criteria->warehouseId))
            ->when($criteria->itemId !== null, fn (Builder $query) => $query->where('stock_issue_items.item_id', $criteria->itemId));

        ReportScope::project($query, $user, 'material_requisitions.project_id');
        ReportScope::dates($query, $criteria, 'stock_issues.issue_date');

        $totals = $query->selectRaw('COUNT(DISTINCT projects.id) as projects')
            ->selectRaw('SUM(stock_issue_items.issued_quantity) as quantity')
            ->selectRaw('SUM(stock_issue_items.total_cost) as total_value')
            ->first();

        return [
            'Projects' => (string) (int) ($totals->projects ?? 0),
            'Issued qty' => $this->quantity((string) ($totals->quantity ?? '0')),
            'Issued value' => $this->money((string) ($totals->total_value ?? '0')),
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
