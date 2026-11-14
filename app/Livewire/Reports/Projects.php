<?php

namespace App\Livewire\Reports;

use App\Domain\Reporting\Support\ReportCatalogue;
use App\Livewire\Concerns\InteractsWithReports;
use App\Support\Permissions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Project Reports')]
class Projects extends Component
{
    use InteractsWithReports;

    protected function reportGroup(): string
    {
        return 'Project';
    }

    protected function reportPermission(): string
    {
        return Permissions::REPORTS_PROJECT_VIEW;
    }

    protected function defaultReportKey(): string
    {
        return ReportCatalogue::PROJECT_CONSUMPTION;
    }
}
