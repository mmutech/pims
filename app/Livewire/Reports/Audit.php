<?php

namespace App\Livewire\Reports;

use App\Domain\Reporting\Support\ReportCatalogue;
use App\Livewire\Concerns\InteractsWithReports;
use App\Support\Permissions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Audit Reports')]
class Audit extends Component
{
    use InteractsWithReports;

    protected function reportGroup(): string
    {
        return 'Audit';
    }

    protected function reportPermission(): string
    {
        return Permissions::REPORTS_AUDIT_VIEW;
    }

    protected function defaultReportKey(): string
    {
        return ReportCatalogue::AUDIT_ACTIVITY;
    }
}
