<?php

namespace App\Livewire;

use App\Domain\Catalog\Models\Item;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\Project;
use App\Domain\Reporting\Services\DashboardMetricsService;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ProjectStatus;
use App\Enums\SupplierStatus;
use App\Models\User;
use App\Support\Permissions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();

        $warehouses = Warehouse::query()
            ->when(! $user->hasGlobalAccess(), fn ($query) => $query->whereIn('id', $user->activeWarehouseIds()));

        $stats = [];

        if ($user->can('viewAny', BusinessUnit::class)) {
            $stats[] = $this->stat('Business Units', BusinessUnit::query()->count(), 'building-office-2', 'organization.business-units.index', 'Operating units across the distribution network.');
        }

        if ($user->can('viewAny', Warehouse::class)) {
            $stats[] = $this->stat('Warehouses', (clone $warehouses)->count(), 'building-storefront', 'warehouses.index', 'Stores you are permitted to view.');
        }

        if ($user->can('viewAny', Item::class)) {
            $stats[] = $this->stat('Catalog Items', Item::query()->count(), 'cube', 'catalog.items.index', 'Items available for procurement and issue.');
        }

        if ($user->can('viewAny', Supplier::class)) {
            $stats[] = $this->stat('Approved Suppliers', Supplier::query()->where('status', SupplierStatus::Active->value)->count(), 'truck', 'suppliers.index', 'Suppliers cleared to receive awards.');
        }

        if ($user->can('viewAny', Project::class)) {
            $stats[] = $this->stat('Active Projects', Project::query()->where('status', ProjectStatus::Active->value)->count(), 'briefcase', 'organization.projects.index', 'Projects currently consuming stock.');
        }

        if ($user->can(Permissions::WORKFLOW_ACT) && $user->can('viewAny', ApprovalRequest::class)) {
            $stats[] = $this->stat(
                'Awaiting Approval',
                app(ApprovalEngine::class)->pendingFor($user)->count(),
                'inbox',
                'approvals.index',
                'Documents waiting for your decision.',
            );
        }

        if ($user->can('viewAny', User::class)) {
            $stats[] = $this->stat('System Users', User::query()->count(), 'users', 'administration.users.index', 'User accounts with access to the system.');
        }

        $recentActivity = $user->can(Permissions::AUDIT_VIEW)
            ? Activity::query()->with('causer')->latest()->limit(8)->get()
            : collect();

        $warehouseSummary = $user->can('viewAny', Warehouse::class)
            ? (clone $warehouses)->withCount(['locations', 'accessGrants'])->orderBy('name')->limit(6)->get()
            : collect();

        $lowStockItems = $user->can('viewAny', Item::class)
            ? Item::query()
                ->where('is_active', true)
                ->where('reorder_level', '>', 0)
                ->orderByDesc('reorder_level')
                ->limit(6)
                ->get()
            : collect();

        $metrics = app(DashboardMetricsService::class);

        return view('livewire.dashboard', [
            'stats' => $stats,
            'kpis' => $metrics->cards($user),
            'stockMovementChart' => $metrics->stockMovementChart($user),
            'recentActivity' => $recentActivity,
            'warehouseSummary' => $warehouseSummary,
            'lowStockItems' => $lowStockItems,
        ]);
    }

    /**
     * @return array{label: string, value: int, icon: string, route: string, description: string}
     */
    private function stat(string $label, int $value, string $icon, string $route, string $description): array
    {
        return compact('label', 'value', 'icon', 'route', 'description');
    }
}
