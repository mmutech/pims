<?php

namespace App\Livewire\Stocktaking\Counts;

use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Stocktaking\Models\StockCount;
use App\Domain\Stocktaking\Services\StockCountService;
use App\Domain\Warehouse\Models\WarehouseLocation;
use App\DTOs\Stocktaking\StockCountData;
use App\DTOs\Stocktaking\StockCountScopeData;
use App\Enums\CountType;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Livewire\Concerns\UsesDistributionOptions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock Count')]
class Form extends Component
{
    use HandlesDomainActions, UsesDistributionOptions;

    public ?int $countId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $scopes = [];

    public function mount(?StockCount $count = null): void
    {
        if ($count !== null && $count->exists) {
            $this->authorize('update', $count);

            $count->load('scopes');

            $this->countId = $count->id;
            $this->form = [
                'warehouse_id' => (string) $count->warehouse_id,
                'count_type' => $count->count_type->value,
                'scope_description' => (string) $count->scope_description,
                'tolerance_quantity' => (string) $count->tolerance_quantity,
                'tolerance_value' => (string) $count->tolerance_value,
            ];

            $this->scopes = $count->scopes->map(fn ($scope): array => [
                'warehouse_location_id' => $scope->warehouse_location_id !== null ? (string) $scope->warehouse_location_id : '',
                'item_category_id' => $scope->item_category_id !== null ? (string) $scope->item_category_id : '',
                'item_id' => $scope->item_id !== null ? (string) $scope->item_id : '',
                'include_zero_balances' => (bool) $scope->include_zero_balances,
            ])->all();

            if ($this->scopes === []) {
                $this->addScope();
            }

            return;
        }

        $this->authorize('create', StockCount::class);

        $warehouses = $this->accessibleWarehouses();

        $this->form = [
            'warehouse_id' => (string) ($warehouses->first()->id ?? ''),
            'count_type' => CountType::Cycle->value,
            'scope_description' => '',
            'tolerance_quantity' => '0',
            'tolerance_value' => '0.00',
        ];

        $this->addScope();
    }

    public function updatedFormWarehouseId(): void
    {
        foreach (array_keys($this->scopes) as $index) {
            $this->scopes[$index]['warehouse_location_id'] = '';
        }
    }

    public function addScope(): void
    {
        $this->scopes[] = [
            'warehouse_location_id' => '',
            'item_category_id' => '',
            'item_id' => '',
            'include_zero_balances' => false,
        ];
    }

    public function removeScope(int $index): void
    {
        unset($this->scopes[$index]);

        $this->scopes = array_values($this->scopes);

        if ($this->scopes === []) {
            $this->addScope();
        }
    }

    public function save(StockCountService $service)
    {
        $count = $this->countId !== null
            ? StockCount::query()->findOrFail($this->countId)
            : null;

        $count !== null
            ? $this->authorize('update', $count)
            : $this->authorize('create', StockCount::class);

        $this->validate([
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.count_type' => ['required', 'string', 'in:'.implode(',', CountType::values())],
            'form.scope_description' => ['required', 'string', 'max:2000'],
            'form.tolerance_quantity' => ['nullable', 'numeric', 'min:0'],
            'form.tolerance_value' => ['nullable', 'numeric', 'min:0'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*.warehouse_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'scopes.*.item_category_id' => ['nullable', 'integer', 'exists:item_categories,id'],
            'scopes.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
        ]);

        $scopeData = [];

        foreach ($this->scopes as $scope) {
            $scopeData[] = new StockCountScopeData(
                warehouseLocationId: ($scope['warehouse_location_id'] ?? '') !== ''
                    ? (int) $scope['warehouse_location_id']
                    : null,
                itemCategoryId: ($scope['item_category_id'] ?? '') !== ''
                    ? (int) $scope['item_category_id']
                    : null,
                itemId: ($scope['item_id'] ?? '') !== ''
                    ? (int) $scope['item_id']
                    : null,
                includeZeroBalances: (bool) ($scope['include_zero_balances'] ?? false),
            );
        }

        $data = new StockCountData(
            warehouseId: (int) $this->form['warehouse_id'],
            countType: CountType::from((string) $this->form['count_type']),
            scopeDescription: (string) $this->form['scope_description'],
            scopes: $scopeData,
            toleranceQuantity: (string) ($this->form['tolerance_quantity'] ?? '0'),
            toleranceValue: (string) ($this->form['tolerance_value'] ?? '0'),
        );

        $saved = null;

        $ok = $this->runDomainAction(
            function () use ($service, $count, $data, &$saved): void {
                $saved = $count !== null
                    ? $service->update($count, $data, auth()->user())
                    : $service->create($data, auth()->user());
            },
            $count !== null ? 'Stock count updated.' : 'Stock count created as a draft.',
        );

        if ($ok && $saved !== null) {
            return redirect()->route('stocktaking.counts.show', $saved);
        }

        return null;
    }

    public function render()
    {
        $warehouseId = $this->form['warehouse_id'] ?? null;

        $locations = WarehouseLocation::query()
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.stocktaking.counts.form', [
            'warehouses' => $this->accessibleWarehouses(),
            'locations' => $locations,
            'categories' => ItemCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'catalogItems' => $this->catalogItems(),
            'types' => CountType::cases(),
        ]);
    }
}
