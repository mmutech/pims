<?php

namespace App\Livewire\Reports;

use App\Domain\Reporting\Support\ReportCatalogue;
use App\Livewire\Concerns\InteractsWithReports;
use App\Support\Permissions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Warehouse Reports')]
class Warehouses extends Component
{
    use InteractsWithReports;

    protected function reportGroup(): string
    {
        return 'Warehouse';
    }

    protected function reportPermission(): string
    {
        return Permissions::REPORTS_WAREHOUSE_VIEW;
    }

    protected function defaultReportKey(): string
    {
        return ReportCatalogue::WAREHOUSE_UTILIZATION;
    }
}
