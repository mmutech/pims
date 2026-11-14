<?php

namespace App\Livewire\Distribution\Issues;

use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Services\StockIssueService;
use App\Domain\Inventory\Models\StockReservation;
use App\DTOs\Distribution\StockIssueData;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Livewire\Concerns\UsesDistributionOptions;
use App\Support\Decimal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock Issue')]
class Form extends Component
{
    use HandlesDomainActions, UsesDistributionOptions;

    public ?int $requisitionId = null;

    public bool $hasReservation = false;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public function mount(): void
    {
        $this->authorize('create', StockIssue::class);

        $requisitionId = (int) request()->query('requisition', 0);

        if ($requisitionId === 0) {
            $this->error('Choose an approved material requisition to issue stock against.');

            return;
        }

        $requisition = MaterialRequisition::query()
            ->with(['items.item', 'items.unitOfMeasure', 'destinationWarehouse'])
            ->findOrFail($requisitionId);

        $this->authorize('view', $requisition);

        if (! $requisition->isApprovedForIssue()) {
            $this->error('Stock can only be issued against an approved requisition.');

            return;
        }

        $this->requisitionId = $requisition->id;

        $this->form = [
            'issued_to' => (string) $requisition->requested_by,
            'issued_by' => (string) auth()->id(),
            'issue_date' => now()->toDateString(),
        ];

        $reservation = StockReservation::query()
            ->open()
            ->where('source_type', $requisition->getMorphClass())
            ->where('source_id', $requisition->id)
            ->with(['items.item', 'items.dimension', 'items.batch'])
            ->latest('id')
            ->first();

        if ($reservation === null) {
            $this->error('No open stock reservation exists. Reserve stock for the requisition before issuing.');

            return;
        }

        $requisitionItems = $requisition->items->keyBy('item_id');

        foreach ($reservation->items as $reservationItem) {
            $outstanding = $reservationItem->outstandingQuantity();

            if (! Decimal::isPositive($outstanding)) {
                continue;
            }

            $requisitionItem = $requisitionItems->get($reservationItem->item_id);

            if ($requisitionItem === null) {
                continue;
            }

            $this->lines[] = [
                'material_requisition_item_id' => $requisitionItem->id,
                'stock_reservation_item_id' => $reservationItem->id,
                'item' => $reservationItem->item?->name ?? 'Unknown item',
                'code' => $reservationItem->item?->code ?? '—',
                'unit' => $requisitionItem->unitOfMeasure?->code ?? '—',
                'batch' => $reservationItem->batch?->batch_number ?? '—',
                'dimension' => $reservationItem->dimension?->code ?? '—',
                'outstanding' => $outstanding,
                'quantity' => $outstanding,
            ];
        }

        $this->hasReservation = $this->lines !== [];

        if (! $this->hasReservation) {
            $this->error('The open reservation has no outstanding quantities to issue.');
        }
    }

    public function save(StockIssueService $service)
    {
        if ($this->requisitionId === null) {
            $this->error('No requisition was selected for this issue.');

            return null;
        }

        $this->authorize('create', StockIssue::class);

        $this->validate([
            'form.issued_to' => ['required', 'integer', 'exists:users,id'],
            'form.issued_by' => ['required', 'integer', 'exists:users,id'],
            'form.issue_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = [];

        foreach ($this->lines as $line) {
            $quantity = (string) ($line['quantity'] ?? '0');

            if (! Decimal::isPositive($quantity)) {
                continue;
            }

            if (Decimal::compare($quantity, (string) $line['outstanding']) > 0) {
                $this->error(sprintf(
                    'Issued quantity for %s exceeds the %s still reserved.',
                    $line['item'],
                    $line['outstanding'],
                ));

                return null;
            }

            $items[] = [
                'material_requisition_item_id' => (int) $line['material_requisition_item_id'],
                'stock_reservation_item_id' => (int) $line['stock_reservation_item_id'],
                'quantity' => $quantity,
            ];
        }

        if ($items === []) {
            $this->error('Enter a quantity greater than zero on at least one line.');

            return null;
        }

        $requisition = MaterialRequisition::query()->find($this->requisitionId);

        if ($requisition === null) {
            $this->error('The selected material requisition could not be found.');

            return null;
        }

        $data = StockIssueData::fromArray([
            ...$this->form,
            'material_requisition_id' => $this->requisitionId,
            'warehouse_id' => $requisition->destination_warehouse_id,
            'items' => $items,
        ]);

        $saved = null;

        $ok = $this->runDomainAction(
            function () use ($service, $data, &$saved): void {
                $saved = $service->create($data, auth()->user());
            },
            'Stock issue created as a draft.',
        );

        if ($ok && $saved !== null) {
            return redirect()->route('distribution.issues.show', $saved);
        }

        return null;
    }

    public function render()
    {
        return view('livewire.distribution.issues.form', [
            'users' => $this->scopedUsers(),
            'requisition' => $this->requisitionId !== null
                ? MaterialRequisition::query()->with('destinationWarehouse')->find($this->requisitionId)
                : null,
        ]);
    }
}
