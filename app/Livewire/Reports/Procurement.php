<?php

namespace App\Livewire\Reports;

use App\Domain\Reporting\Support\ReportCatalogue;
use App\Livewire\Concerns\InteractsWithReports;
use App\Support\Permissions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Procurement Reports')]
class Procurement extends Component
{
    use InteractsWithReports;

    protected function reportGroup(): string
    {
        return 'Procurement';
    }

    protected function reportPermission(): string
    {
        return Permissions::REPORTS_PROCUREMENT_VIEW;
    }

    protected function defaultReportKey(): string
    {
        return ReportCatalogue::PROCUREMENT_ORDERS;
    }
}
