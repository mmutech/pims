<?php

namespace App\Livewire\Procurement\Requisitions;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Services\PurchaseRequisitionService;
use App\Domain\Supplier\Models\Supplier;
use App\DTOs\Procurement\PurchaseRequisitionData;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Purchase Requisition')]
class Form extends Component
{
    use HandlesDomainActions;

    public ?int $requisitionId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?PurchaseRequisition $requisition = null): void
    {
        $user = auth()->user();

        if ($requisition !== null && $requisition->exists) {
            $this->authorize('update', $requisition);

            $this->requisitionId = $requisition->id;
            $this->form = [
                'requesting_department_id' => (string) $requisition->requesting_department_id,
                'business_unit_id' => (string) $requisition->business_unit_id,
                'cost_center_id' => $requisition->cost_center_id !== null ? (string) $requisition->cost_center_id : '',
                'project_id' => $requisition->project_id !== null ? (string) $requisition->project_id : '',
                'suggested_supplier_id' => $requisition->suggested_supplier_id !== null ? (string) $requisition->suggested_supplier_id : '',
                'required_by' => $requisition->required_by?->toDateString() ?? '',
                'justification' => $requisition->justification,
                'currency' => $requisition->currency,
            ];

            $this->items = $requisition->items->map(fn ($item) => [
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'requested_quantity' => (string) $item->requested_quantity,
                'estimated_unit_price' => (string) $item->estimated_unit_price,
                'specification' => (string) $item->specification,
            ])->all();

            return;
        }

        $this->authorize('create', PurchaseRequisition::class);

        $this->form = [
            'requesting_department_id' => $user->department_id !== null ? (string) $user->department_id : '',
            'cost_center_id' => $user->cost_center_id !== null ? (string) $user->cost_center_id : '',
            'project_id' => '',
            'suggested_supplier_id' => '',
            'required_by' => now()->addDays(14)->toDateString(),
            'justification' => '',
            'currency' => 'NGN',
        ];

        $this->items = [$this->blankItem()];
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

    public function save(PurchaseRequisitionService $service)
    {
        $requisition = $this->requisitionId !== null
            ? PurchaseRequisition::findOrFail($this->requisitionId)
            : null;

        $requisition !== null
            ? $this->authorize('update', $requisition)
            : $this->authorize('create', PurchaseRequisition::class);

        $this->validate([
            'form.requesting_department_id' => ['required', 'integer', 'exists:departments,id'],
            'form.business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'form.cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'form.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'form.suggested_supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'form.required_by' => ['nullable', 'date'],
            'form.justification' => ['required', 'string', 'min:10', 'max:5000'],
            'form.currency' => ['required', 'string', 'size:3'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.estimated_unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.specification' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = PurchaseRequisitionData::fromArray([
            ...$this->form,
            'requested_by' => auth()->id(),
            'items' => $this->items,
        ]);

        $saved = $this->runDomainAction(
            fn () => $requisition !== null
                ? $service->update($requisition, $data, auth()->user())
                : $service->create($data, auth()->user()),
            $requisition !== null ? 'Requisition updated.' : 'Requisition created as a draft.',
        );

        if ($saved) {
            $savedRequisition = $requisition ?? PurchaseRequisition::query()->latest('id')->first();

            return redirect()->route('procurement.requisitions.show', $savedRequisition);
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
            'estimated_unit_price' => '0',
            'specification' => '',
        ];
    }

    public function render()
    {
        return view('livewire.procurement.requisitions.form', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'businessUnits' => BusinessUnit::query()->where('is_active', true)->orderBy('name')->get(),
            'costCenters' => CostCenter::query()->where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->approved()->orderBy('name')->get(),
            'catalogItems' => Item::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => UnitOfMeasure::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
