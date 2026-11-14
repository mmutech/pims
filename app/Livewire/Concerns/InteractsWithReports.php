<?php

namespace App\Livewire\Concerns;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Organization\Models\Project;
use App\Domain\Reporting\Services\ReportExportService;
use App\Domain\Reporting\Support\ReportCatalogue;
use App\Domain\Reporting\Support\ReportScope;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Reporting\ReportCriteria;
use App\Enums\ApprovalStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\User;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Shared report screen behaviour: report selection, filtering, summarising and
 * permission-checked exports. Concrete screens only declare their catalogue
 * group, permission and default report.
 */
trait InteractsWithReports
{
    use WithPagination;

    public string $report = '';

    /**
     * @var array<string, mixed>
     */
    public array $form = [
        'from' => '',
        'to' => '',
        'warehouseId' => '',
        'itemId' => '',
        'categoryId' => '',
        'supplierId' => '',
        'projectId' => '',
        'status' => '',
        'search' => '',
        'logName' => '',
        'subjectType' => '',
        'movementWindowDays' => 90,
        'expiryWindowDays' => 90,
        'mismatchesOnly' => false,
    ];

    abstract protected function reportGroup(): string;

    abstract protected function reportPermission(): string;

    abstract protected function defaultReportKey(): string;

    public function mount(): void
    {
        abort_unless(auth()->user()->can($this->reportPermission()), 403);

        $this->form['movementWindowDays'] = (int) config('reports.slow_moving_window_days', 90);
        $this->form['expiryWindowDays'] = (int) config('reports.expiry_window_days', 90);

        $requested = (string) request()->query('report', '');
        $definition = ReportCatalogue::find($requested);

        $this->report = $definition !== null
            && $definition['group'] === $this->reportGroup()
            && auth()->user()->can($definition['view'])
                ? $requested
                : $this->defaultReportKey();
    }

    public function updated(string $property): void
    {
        if ($property === 'report' || str_starts_with($property, 'form.')) {
            $this->resetPage();
        }
    }

    public function export(string $format)
    {
        $definition = ReportCatalogue::find($this->report);

        abort_unless(
            $definition !== null && auth()->user()->can($definition['export']),
            403,
        );

        return redirect()->route('reports.export', [
            'report' => $this->report,
            'format' => $format,
            ...$this->criteria()->toArray(),
        ]);
    }

    public function render()
    {
        $user = auth()->user();
        $group = $this->reportGroup();
        $definition = ReportCatalogue::find($this->report);

        abort_unless(
            $definition !== null
            && $definition['group'] === $group
            && $user->can($definition['view']),
            403,
        );

        $reports = array_filter(
            ReportCatalogue::grouped()[$group] ?? [],
            fn (array $report): bool => $user->can($report['view']),
        );

        $data = app(ReportExportService::class)->paginated($this->report, $user, $this->criteria());

        return view('livewire.reports.screen', [
            'group' => $group,
            'reports' => $reports,
            'definition' => $definition,
            'data' => $data,
            'options' => $this->reportOptions($group, $definition),
            'canExport' => $user->can($definition['export']),
        ]);
    }

    protected function criteria(): ReportCriteria
    {
        return ReportCriteria::fromArray($this->form);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    protected function reportOptions(string $group, array $definition): array
    {
        $user = auth()->user();

        $options = [
            'warehouses' => collect(),
            'items' => collect(),
            'categories' => collect(),
            'suppliers' => collect(),
            'projects' => collect(),
            'statuses' => [],
            'logNames' => collect(),
            'subjectTypes' => collect(),
        ];

        if (in_array($group, ['Inventory', 'Warehouse', 'Procurement', 'Project'], true)) {
            $options['warehouses'] = Warehouse::query()
                ->when(! $user->hasGlobalAccess(), fn ($query) => $query->whereIn('id', $user->activeWarehouseIds()))
                ->orderBy('name')
                ->get(['id', 'code', 'name']);
        }

        if (in_array($group, ['Inventory', 'Warehouse', 'Project'], true)) {
            $options['items'] = Item::query()->orderBy('code')->limit(500)->get(['id', 'code', 'name']);
        }

        if (in_array($group, ['Inventory', 'Project'], true)) {
            $options['categories'] = ItemCategory::query()->orderBy('name')->get(['id', 'name']);
        }

        if ($group === 'Procurement') {
            $options['suppliers'] = Supplier::query()->orderBy('name')->get(['id', 'name']);
            $options['statuses'] = PurchaseOrderStatus::options();
        }

        if ($group === 'Project') {
            $options['projects'] = Project::query()
                ->tap(fn ($query) => ReportScope::project($query, $user, 'projects.id'))
                ->orderBy('name')
                ->get(['id', 'code', 'name']);
        }

        if ($group === 'Audit') {
            $options['logNames'] = Activity::query()
                ->select('log_name')
                ->whereNotNull('log_name')
                ->distinct()
                ->orderBy('log_name')
                ->pluck('log_name');

            $options['subjectTypes'] = Activity::query()
                ->select('subject_type')
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)]);

            if ($definition['key'] === ReportCatalogue::AUDIT_APPROVALS) {
                $options['statuses'] = ApprovalStatus::options();
            }
        }

        return $options;
    }

    protected function currentUser(): ?User
    {
        return auth()->user();
    }
}
