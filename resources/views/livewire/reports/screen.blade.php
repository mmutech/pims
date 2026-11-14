@php
    $key = $definition['key'];
    $dateFiltered = in_array($key, [
        \App\Domain\Reporting\Support\ReportCatalogue::INVENTORY_MOVEMENTS,
        \App\Domain\Reporting\Support\ReportCatalogue::PROCUREMENT_ORDERS,
        \App\Domain\Reporting\Support\ReportCatalogue::PROCUREMENT_SUPPLIER_SPEND,
        \App\Domain\Reporting\Support\ReportCatalogue::WAREHOUSE_THROUGHPUT,
        \App\Domain\Reporting\Support\ReportCatalogue::PROJECT_CONSUMPTION,
        \App\Domain\Reporting\Support\ReportCatalogue::AUDIT_ACTIVITY,
        \App\Domain\Reporting\Support\ReportCatalogue::AUDIT_APPROVALS,
    ], true);
    $showWarehouse = in_array($group, ['Inventory', 'Warehouse', 'Procurement', 'Project'], true);
    $showItem = in_array($group, ['Inventory', 'Warehouse', 'Project'], true);
    $showCategory = in_array($group, ['Inventory', 'Project'], true);
    $showSupplier = $group === 'Procurement';
    $showProject = $group === 'Project';
    $showStatus = $group === 'Procurement' || $key === \App\Domain\Reporting\Support\ReportCatalogue::AUDIT_APPROVALS;
    $showLogFilters = $key === \App\Domain\Reporting\Support\ReportCatalogue::AUDIT_ACTIVITY;
    $formatValue = function (array $column, mixed $value): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($column['type'] ?? 'text') {
            'money' => number_format((float) $value, 2),
            'quantity' => rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0',
            'cost' => number_format((float) $value, 4),
            'integer' => number_format((int) $value),
            'date' => \Carbon\CarbonImmutable::parse((string) $value)->format('d M Y'),
            'datetime' => \Carbon\CarbonImmutable::parse((string) $value)->format('d M Y H:i'),
            default => (string) $value,
        };
    };
@endphp

<div class="space-y-6">
    <x-page-header :title="$definition['title']" :description="$definition['description']" />

    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <flux:select wire:model.live="report" label="Report" class="min-w-72">
                @foreach ($reports as $available)
                    <option value="{{ $available['key'] }}">{{ $available['title'] }}</option>
                @endforeach
            </flux:select>

            <div class="flex flex-wrap items-center gap-2">
                @if ($canExport)
                    <flux:button wire:click="export('xlsx')" icon="arrow-down-tray" variant="filled">Excel</flux:button>
                    <flux:button wire:click="export('csv')" icon="table-cells">CSV</flux:button>
                    <flux:button wire:click="export('pdf')" icon="document-text">PDF</flux:button>
                @else
                    <flux:badge color="amber" icon="lock-closed">Export not permitted</flux:badge>
                @endif
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="form.search" label="Search" placeholder="Code, name or reference" icon="magnifying-glass" />

            @if ($dateFiltered)
                <flux:input wire:model.live="form.from" type="date" label="From" />
                <flux:input wire:model.live="form.to" type="date" label="To" />
            @endif

            @if ($showWarehouse)
                <flux:select wire:model.live="form.warehouseId" label="Warehouse">
                    <option value="">All warehouses</option>
                    @foreach ($options['warehouses'] as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </flux:select>
            @endif

            @if ($showItem)
                <flux:select wire:model.live="form.itemId" label="Item">
                    <option value="">All items</option>
                    @foreach ($options['items'] as $item)
                        <option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }}</option>
                    @endforeach
                </flux:select>
            @endif

            @if ($showCategory)
                <flux:select wire:model.live="form.categoryId" label="Category">
                    <option value="">All categories</option>
                    @foreach ($options['categories'] as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </flux:select>
            @endif

            @if ($showSupplier)
                <flux:select wire:model.live="form.supplierId" label="Supplier">
                    <option value="">All suppliers</option>
                    @foreach ($options['suppliers'] as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </flux:select>
            @endif

            @if ($showProject)
                <flux:select wire:model.live="form.projectId" label="Project">
                    <option value="">All projects</option>
                    @foreach ($options['projects'] as $project)
                        <option value="{{ $project->id }}">{{ $project->code }} — {{ $project->name }}</option>
                    @endforeach
                </flux:select>
            @endif

            @if ($showStatus)
                <flux:select wire:model.live="form.status" label="Status">
                    <option value="">All statuses</option>
                    @foreach ($options['statuses'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            @endif

            @if ($showLogFilters)
                <flux:select wire:model.live="form.logName" label="Module">
                    <option value="">All modules</option>
                    @foreach ($options['logNames'] as $logName)
                        <option value="{{ $logName }}">{{ $logName }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.subjectType" label="Subject type">
                    <option value="">All subject types</option>
                    @foreach ($options['subjectTypes'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            @endif

            @if ($key === \App\Domain\Reporting\Support\ReportCatalogue::INVENTORY_SLOW_MOVING)
                <flux:input wire:model.live="form.movementWindowDays" type="number" min="1" max="3650" label="Idle window (days)" />
            @endif

            @if ($key === \App\Domain\Reporting\Support\ReportCatalogue::INVENTORY_EXPIRING)
                <flux:input wire:model.live="form.expiryWindowDays" type="number" min="1" max="3650" label="Expiry window (days)" />
                <flux:input wire:model.live="form.from" type="date" label="Expiry from" />
            @endif

            @if ($key === \App\Domain\Reporting\Support\ReportCatalogue::INVENTORY_RECONCILIATION)
                <flux:checkbox wire:model.live="form.mismatchesOnly" label="Mismatches only" />
            @endif
        </div>
    </flux:card>

    @if (! empty($data['summary']))
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($data['summary'] as $label => $value)
                <flux:card class="space-y-1">
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</flux:text>
                    <flux:heading size="xl">{{ $value }}</flux:heading>
                </flux:card>
            @endforeach
        </div>
    @endif

    <flux:card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading>{{ $definition['title'] }}</flux:heading>
                <flux:subheading>{{ number_format($data['total']) }} row{{ $data['total'] === 1 ? '' : 's' }} match the current filters.</flux:subheading>
            </div>
            <flux:badge color="zinc">{{ $group }}</flux:badge>
        </div>

        <flux:table :paginate="$data['paginator']" class="mt-4">
            <flux:table.columns>
                @foreach ($definition['columns'] as $column)
                    <flux:table.column :align="($column['align'] ?? null) === 'end' ? 'end' : 'start'">
                        {{ $column['label'] }}
                    </flux:table.column>
                @endforeach
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($data['rows'] as $row)
                    <flux:table.row :key="$loop->index">
                        @foreach ($definition['columns'] as $column)
                            <flux:table.cell :align="($column['align'] ?? null) === 'end' ? 'end' : 'start'">
                                {{ $formatValue($column, $row[$column['key']] ?? null) }}
                            </flux:table.cell>
                        @endforeach
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ count($definition['columns']) }}" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No rows match the selected filters.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @unless ($canExport)
        <flux:callout icon="information-circle" heading="Read-only access">
            You can view this report but your role cannot download exports. Ask an administrator for the matching export permission.
        </flux:callout>
    @endunless
</div>
