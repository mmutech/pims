<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Reporting\Support\ReportCatalogue;
use App\Domain\Reporting\Support\ReportScope;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\DTOs\Reporting\ReportCriteria;
use App\Enums\ApprovalStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Permission-aware KPI cards for the dashboard. Each card is only produced when
 * the user holds the matching report permission, and every figure is already
 * scoped to the user's warehouses, projects and business unit.
 */
class DashboardMetricsService
{
    public function __construct(
        private readonly InventoryReportService $inventory,
        private readonly ProcurementReportService $procurement,
        private readonly ProjectReportService $projects,
    ) {}

    /**
     * @return list<array{label: string, value: string, hint: string, icon: string, route: string}>
     */
    public function cards(User $user): array
    {
        $cards = [];

        if ($user->can(Permissions::REPORTS_INVENTORY_VIEW)) {
            $valuation = $this->inventory->valuationSummary($user, new ReportCriteria(perPage: 1));
            $cards[] = $this->card(
                'Inventory value',
                $valuation['Value'],
                $valuation['On hand'].' units on hand',
                'banknotes',
                'reports.inventory',
            );

            $expiring = $this->inventory->expiring($user, new ReportCriteria(expiryWindowDays: 30, perPage: 1));
            $cards[] = $this->card(
                'Expiring in 30 days',
                (string) $expiring->total(),
                'Batch balances reaching expiry',
                'clock',
                'reports.inventory',
            );

            $slowMoving = $this->inventory->slowMoving($user, new ReportCriteria(movementWindowDays: 90, perPage: 1));
            $cards[] = $this->card(
                'Slow-moving lines',
                (string) $slowMoving->total(),
                'No outbound movement for 90 days',
                'archive-box',
                'reports.inventory',
            );
        }

        if ($user->can(Permissions::REPORTS_PROCUREMENT_VIEW)) {
            $openOrders = PurchaseOrder::query()
                ->visibleTo($user)
                ->whereIn('status', [
                    PurchaseOrderStatus::Approved->value,
                    PurchaseOrderStatus::PartiallyReceived->value,
                ]);

            $total = (clone $openOrders)->sum('total_amount');

            $cards[] = $this->card(
                'Open purchase orders',
                (string) (clone $openOrders)->count(),
                $this->money((string) $total).' committed',
                'truck',
                'reports.procurement',
            );
        }

        if ($user->can(Permissions::REPORTS_PROJECT_VIEW)) {
            $consumption = $this->projects->consumptionSummary($user, new ReportCriteria(
                from: now()->startOfMonth()->toDateString(),
                to: now()->toDateString(),
                perPage: 1,
            ));

            $cards[] = $this->card(
                'Project consumption (MTD)',
                $consumption['Issued value'],
                $consumption['Projects'].' projects issued this month',
                'briefcase',
                'reports.projects',
            );
        }

        if ($user->can(Permissions::REPORTS_AUDIT_VIEW)) {
            $pending = ApprovalRequest::query()->where('status', ApprovalStatus::Pending->value)->count();

            $cards[] = $this->card(
                'Pending approvals',
                (string) $pending,
                'Documents awaiting a decision',
                'inbox',
                'reports.audit',
            );
        }

        return $cards;
    }

    /**
     * @return array{has_data: bool, points: list<array{label: string, inbound: string, outbound: string, inbound_height: int, outbound_height: int}>}|null
     */
    public function stockMovementChart(User $user): ?array
    {
        if (! $user->can(Permissions::REPORTS_INVENTORY_VIEW)) {
            return null;
        }

        $now = CarbonImmutable::now();
        $firstMonth = $now->startOfMonth()->subMonths(5);
        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', posted_at)",
            'pgsql' => "to_char(posted_at, 'YYYY-MM')",
            default => "DATE_FORMAT(posted_at, '%Y-%m')",
        };

        $query = StockLedger::query()
            ->selectRaw($monthExpression.' as month_key')
            ->selectRaw('SUM(quantity_in) as inbound_quantity')
            ->selectRaw('SUM(quantity_out) as outbound_quantity')
            ->whereBetween('posted_at', [$firstMonth, $now])
            ->groupByRaw($monthExpression)
            ->orderBy('month_key');

        ReportScope::warehouse($query, $user);

        $monthlyMovements = $query->get()->keyBy('month_key');
        $points = [];
        $maximum = 0.0;

        for ($offset = 0; $offset < 6; $offset++) {
            $month = $firstMonth->addMonths($offset);
            $movement = $monthlyMovements->get($month->format('Y-m'));
            $inbound = Decimal::normalize((string) ($movement?->inbound_quantity ?? '0'), Decimal::QUANTITY_SCALE);
            $outbound = Decimal::normalize((string) ($movement?->outbound_quantity ?? '0'), Decimal::QUANTITY_SCALE);

            $maximum = max($maximum, (float) $inbound, (float) $outbound);
            $points[] = [
                'label' => $month->format('M'),
                'inbound' => rtrim(rtrim($inbound, '0'), '.'),
                'outbound' => rtrim(rtrim($outbound, '0'), '.'),
                'inbound_height' => 0,
                'outbound_height' => 0,
            ];
        }

        foreach ($points as &$point) {
            if ($maximum > 0) {
                $point['inbound_height'] = (float) $point['inbound'] > 0
                    ? max(2, (int) round((float) $point['inbound'] / $maximum * 100))
                    : 0;
                $point['outbound_height'] = (float) $point['outbound'] > 0
                    ? max(2, (int) round((float) $point['outbound'] / $maximum * 100))
                    : 0;
            }
        }
        unset($point);

        return [
            'has_data' => $maximum > 0,
            'points' => $points,
        ];
    }

    public function reportGroups(User $user): array
    {
        $groups = [];

        foreach (ReportCatalogue::grouped() as $group => $reports) {
            $definition = reset($reports);

            if ($definition !== false && $user->can($definition['view'])) {
                $groups[$group] = count($reports);
            }
        }

        return $groups;
    }

    /**
     * @return array{label: string, value: string, hint: string, icon: string, route: string}
     */
    private function card(string $label, string $value, string $hint, string $icon, string $route): array
    {
        return compact('label', 'value', 'hint', 'icon', 'route');
    }

    private function money(string $value): string
    {
        return number_format((float) Decimal::normalize($value, Decimal::MONEY_SCALE), 2);
    }
}
