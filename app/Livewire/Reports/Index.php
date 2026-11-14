<?php

namespace App\Livewire\Reports;

use App\Domain\Reporting\Support\ReportCatalogue;
use App\Models\User;
use App\Support\Permissions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Reports')]
class Index extends Component
{
    public const SCREENS = [
        'Inventory' => 'reports.inventory',
        'Procurement' => 'reports.procurement',
        'Warehouse' => 'reports.warehouses',
        'Project' => 'reports.projects',
        'Audit' => 'reports.audit',
    ];

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless(
            $user->can(Permissions::REPORTS_INVENTORY_VIEW)
            || $user->can(Permissions::REPORTS_PROCUREMENT_VIEW)
            || $user->can(Permissions::REPORTS_WAREHOUSE_VIEW)
            || $user->can(Permissions::REPORTS_PROJECT_VIEW)
            || $user->can(Permissions::REPORTS_AUDIT_VIEW),
            403,
        );
    }

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();

        $groups = [];

        foreach (ReportCatalogue::grouped() as $group => $reports) {
            $visible = array_filter($reports, fn (array $report): bool => $user->can($report['view']));

            if ($visible === []) {
                continue;
            }

            $groups[$group] = [
                'route' => self::SCREENS[$group] ?? 'reports.index',
                'reports' => array_values($visible),
            ];
        }

        return view('livewire.reports.index', ['groups' => $groups]);
    }
}
