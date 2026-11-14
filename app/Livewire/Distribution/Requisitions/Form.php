<?php

namespace App\Livewire\Distribution\Requisitions;

use App\Domain\Catalog\Models\Item;
use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Services\MaterialRequisitionService;
use App\DTOs\Distribution\MaterialRequisitionData;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Livewire\Concerns\UsesDistributionOptions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Material Requisition')]
class Form extends Component
{
    use HandlesDomainActions, UsesDistributionOptions;

    public ?int $requisitionId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?MaterialRequisition $requisition = null): void
    {
        if ($requisition !== null && $requisition->exists) {
            $this->authorize('update', $requisition);

            $this->requisitionId = $requisition->id;
            $this->form = [
                'requesting_department_id' => (string) $requisition->requesting_department_id,
                'cost_center_id' => $requisition->cost_center_id !== null ? (string) $requisition->cost_center_id : '',
                'project_id' => $requisition->project_id !== null ? (string) $requisition->project_id : '',
                'destination_warehouse_id' => (string) $requisition->destination_warehouse_id,
                'purpose' => (string) $requisition->purpose,
                'required_by' => $requisition->required_by?->toDateString() ?? '',
                'priority' => (string) $requisition->priority,
            ];

            $this->items = $requisition->items->map(fn ($item): array => [
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'requested_quantity' => (string) $item->requested_quantity,
                'notes' => (string) $item->notes,
            ])->all();

            return;
        }

        $this->authorize('create', MaterialRequisition::class);

        $user = auth()->user();
        $warehouses = $this->accessibleWarehouses();

        $this->form = [
            'requesting_department_id' => $user->department_id !== null ? (string) $user->department_id : '',
            'cost_center_id' => $user->cost_center_id !== null ? (string) $user->cost_center_id : '',
            'project_id' => '',
            'destination_warehouse_id' => (string) ($warehouses->first()->id ?? ''),
            'purpose' => '',
            'required_by' => now()->addDays(7)->toDateString(),
            'priority' => 'normal',
        ];

        $this->items = [$this->blankItem()];
    }

    public function updated(string $property): void
    {
        if (! str_ends_with($property, '.item_id')) {
            return;
        }

        $parts = explode('.', $property);

        if (count($parts) !== 3) {
            return;
        }

        $index = (int) $parts[1];
        $item = Item::query()->with('baseUnit')->find((int) ($this->items[$index]['item_id'] ?? 0));

        if ($item !== null) {
            $this->items[$index]['unit_of_measure_id'] = (string) $item->base_unit_of_measure_id;
        }
    }

    public function addItem(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);

        if ($this->items === []) {
            $this->items = [$this->blankItem()];
        }
    }

    public function save(MaterialRequisitionService $service)
    {
        $requisition = $this->requisitionId !== null
            ? MaterialRequisition::query()->findOrFail($this->requisitionId)
            : null;

        $requisition !== null
            ? $this->authorize('update', $requisition)
            : $this->authorize('create', MaterialRequisition::class);

        $this->validate([
            'form.requesting_department_id' => ['required', 'integer', 'exists:departments,id'],
            'form.cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'form.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'form.destination_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.purpose' => ['required', 'string', 'max:2000'],
            'form.required_by' => ['nullable', 'date'],
            'form.priority' => ['required', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = MaterialRequisitionData::fromArray([
            ...$this->form,
            'requested_by' => auth()->id(),
            'items' => array_map(fn (array $item): array => [
                'item_id' => (int) $item['item_id'],
                'unit_of_measure_id' => (int) $item['unit_of_measure_id'],
                'requested_quantity' => (string) $item['requested_quantity'],
                'notes' => ($item['notes'] ?? '') !== '' ? (string) $item['notes'] : null,
            ], $this->items),
        ]);

        $saved = null;

        $ok = $this->runDomainAction(
            function () use ($service, $requisition, $data, &$saved): void {
                $saved = $requisition !== null
                    ? $service->update($requisition, $data, auth()->user())
                    : $service->create($data, auth()->user());
            },
            $requisition !== null ? 'Material requisition updated.' : 'Material requisition created as a draft.',
        );

        if ($ok && $saved !== null) {
            return redirect()->route('distribution.requisitions.show', $saved);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function blankItem(): array
    {
        return [
            'item_id' => '',
            'unit_of_measure_id' => '',
            'requested_quantity' => '1',
            'notes' => '',
        ];
    }

    public function render()
    {
        return view('livewire.distribution.requisitions.form', [
            'departments' => $this->scopedDepartments(),
            'costCenters' => $this->scopedCostCenters(),
            'projects' => $this->scopedProjects(),
            'warehouses' => $this->accessibleWarehouses(),
            'catalogItems' => $this->catalogItems(),
            'units' => $this->unitOptions(),
        ]);
    }
}
