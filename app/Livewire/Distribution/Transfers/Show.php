<?php

namespace App\Livewire\Distribution\Transfers;

use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Distribution\Models\StockTransferDiscrepancy;
use App\Domain\Distribution\Models\StockTransferDispatch;
use App\Domain\Distribution\Models\StockTransferDispatchItem;
use App\Domain\Distribution\Services\StockTransferService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\DTOs\Distribution\StockTransferDispatchData;
use App\DTOs\Distribution\StockTransferReceiptData;
use App\Enums\StockDimensionType;
use App\Enums\TransferDiscrepancyResolution;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock Transfer')]
class Show extends Component
{
    use HandlesDomainActions;

    public StockTransfer $transfer;

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public bool $showDispatchModal = false;

    /** @var array<string, mixed> */
    public array $dispatchForm = [];

    /** @var list<array<string, mixed>> */
    public array $dispatchLines = [];

    public bool $showReceiveModal = false;

    public ?int $receiveDispatchId = null;

    /** @var list<array<string, mixed>> */
    public array $receiveLines = [];

    public string $receiveNotes = '';

    public bool $showResolveModal = false;

    public ?int $selectedDiscrepancyId = null;

    public string $discrepancyResolution = 'write_off';

    public string $discrepancyNotes = '';

    public function mount(StockTransfer $transfer): void
    {
        $this->authorize('view', $transfer);

        $this->transfer = $transfer;
        $this->loadTransfer();
    }

    public function submit(StockTransferService $service): void
    {
        $this->authorize('submit', $this->transfer);

        if ($this->runDomainAction(
            fn () => $service->submit($this->transfer, auth()->user()),
            'Stock transfer submitted for approval.',
        )) {
            $this->loadTransfer();
        }
    }

    public function openDispatchModal(): void
    {
        $this->authorize('dispatch', $this->transfer);

        $this->dispatchForm = [
            'vehicle_number' => '',
            'driver_name' => '',
            'dispatched_at' => now()->toDateString(),
        ];

        $this->dispatchLines = $this->transfer->items
            ->map(function ($item): ?array {
                $remaining = Decimal::subtract(
                    $item->approved_quantity,
                    $item->dispatched_quantity,
                    Decimal::QUANTITY_SCALE,
                );

                if (! Decimal::isPositive($remaining)) {
                    return null;
                }

                return [
                    'stock_transfer_item_id' => $item->id,
                    'item' => $item->item?->name ?? 'Unknown item',
                    'code' => $item->item?->code ?? '—',
                    'unit' => $item->unitOfMeasure?->code ?? '—',
                    'remaining' => $remaining,
                    'quantity' => $remaining,
                    'batch_id' => '',
                    'requires_batch' => (bool) ($item->item?->tracking_type?->requiresBatch() ?? false),
                    'batches' => $this->batchOptions($this->transfer->source_warehouse_id, $item->item_id),
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($this->dispatchLines === []) {
            $this->error('All approved quantities have already been dispatched.');

            return;
        }

        $this->showDispatchModal = true;
    }

    public function postDispatch(StockTransferService $service): void
    {
        $this->authorize('dispatch', $this->transfer);

        $this->validate([
            'dispatchForm.vehicle_number' => ['nullable', 'string', 'max:100'],
            'dispatchForm.driver_name' => ['nullable', 'string', 'max:150'],
            'dispatchForm.dispatched_at' => ['nullable', 'date'],
            'dispatchLines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'dispatchLines.*.batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
        ]);

        $lines = [];

        foreach ($this->dispatchLines as $line) {
            $quantity = (string) ($line['quantity'] ?? '0');

            if (! Decimal::isPositive($quantity)) {
                continue;
            }

            if (Decimal::compare($quantity, (string) $line['remaining']) > 0) {
                $this->error(sprintf(
                    'Dispatch quantity for %s exceeds the %s still outstanding.',
                    $line['item'],
                    $line['remaining'],
                ));

                return;
            }

            if ($line['requires_batch'] && ($line['batch_id'] ?? '') === '') {
                $this->error(sprintf('A batch must be selected for %s.', $line['item']));

                return;
            }

            $lines[] = [
                'stock_transfer_item_id' => (int) $line['stock_transfer_item_id'],
                'quantity' => $quantity,
                'batch_id' => ($line['batch_id'] ?? '') !== '' ? (int) $line['batch_id'] : null,
            ];
        }

        if ($lines === []) {
            $this->error('Enter a quantity greater than zero on at least one dispatch line.');

            return;
        }

        $data = StockTransferDispatchData::fromArray([
            ...$this->dispatchForm,
            'stock_transfer_id' => $this->transfer->id,
            'dispatched_by' => auth()->id(),
            'lines' => $lines,
        ]);

        if ($this->runDomainAction(
            fn () => $service->dispatch($data, auth()->user()),
            'Stock dispatched. Quantities are now in transit to the destination warehouse.',
        )) {
            $this->showDispatchModal = false;
            $this->loadTransfer();
        }
    }

    public function openReceiveModal(): void
    {
        $this->authorize('receive', $this->transfer);

        $dispatch = $this->outstandingDispatches()->first();

        if ($dispatch === null) {
            $this->error('There are no dispatched quantities outstanding to receive.');

            return;
        }

        $this->receiveDispatchId = $dispatch->id;
        $this->receiveNotes = '';
        $this->buildReceiveLines();
        $this->showReceiveModal = true;
    }

    public function updatedReceiveDispatchId(): void
    {
        $this->buildReceiveLines();
    }

    public function receive(StockTransferService $service): void
    {
        $this->authorize('receive', $this->transfer);

        $this->validate([
            'receiveDispatchId' => ['required', 'integer', 'exists:stock_transfer_dispatches,id'],
            'receiveNotes' => ['nullable', 'string', 'max:1000'],
            'receiveLines.*.received_quantity' => ['nullable', 'numeric', 'min:0'],
            'receiveLines.*.damaged_quantity' => ['nullable', 'numeric', 'min:0'],
            'receiveLines.*.missing_quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = [];

        foreach ($this->receiveLines as $line) {
            $received = (string) ($line['received_quantity'] ?? '0');
            $damaged = (string) ($line['damaged_quantity'] ?? '0');
            $missing = (string) ($line['missing_quantity'] ?? '0');

            $accounted = Decimal::add(
                Decimal::add($received, $damaged, Decimal::QUANTITY_SCALE),
                $missing,
                Decimal::QUANTITY_SCALE,
            );

            if (! Decimal::isPositive($accounted)) {
                continue;
            }

            if (Decimal::compare($accounted, (string) $line['remaining']) > 0) {
                $this->error(sprintf(
                    'Receipt quantities for %s exceed the %s still outstanding.',
                    $line['item'],
                    $line['remaining'],
                ));

                return;
            }

            $lines[] = [
                'dispatch_item_id' => (int) $line['dispatch_item_id'],
                'received_quantity' => $received,
                'damaged_quantity' => $damaged,
                'missing_quantity' => $missing,
            ];
        }

        if ($lines === []) {
            $this->error('Record at least one received, damaged, or missing quantity.');

            return;
        }

        $data = StockTransferReceiptData::fromArray([
            'dispatch_id' => $this->receiveDispatchId,
            'received_by' => auth()->id(),
            'received_at' => now()->toDateString(),
            'notes' => $this->receiveNotes !== '' ? $this->receiveNotes : null,
            'lines' => $lines,
        ]);

        if ($this->runDomainAction(
            fn () => $service->receive($data, auth()->user()),
            'Transfer receipt posted. Damaged goods are quarantined and shortages remain in transit.',
        )) {
            $this->showReceiveModal = false;
            $this->loadTransfer();
        }
    }

    public function openResolveModal(int $discrepancyId): void
    {
        $this->authorize('resolveDiscrepancy', $this->transfer);

        $this->selectedDiscrepancyId = $discrepancyId;
        $this->discrepancyResolution = TransferDiscrepancyResolution::WriteOff->value;
        $this->discrepancyNotes = '';
        $this->showResolveModal = true;
    }

    public function resolve(StockTransferService $service): void
    {
        $this->authorize('resolveDiscrepancy', $this->transfer);

        $this->validate([
            'selectedDiscrepancyId' => ['required', 'integer', 'exists:stock_transfer_discrepancies,id'],
            'discrepancyResolution' => ['required', 'string', 'in:write_off,return_to_source,claim,replacement'],
            'discrepancyNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $discrepancy = StockTransferDiscrepancy::findOrFail($this->selectedDiscrepancyId);

        if ($this->runDomainAction(
            fn () => $service->resolveDiscrepancy(
                $discrepancy,
                TransferDiscrepancyResolution::from($this->discrepancyResolution),
                auth()->user(),
                $this->discrepancyNotes !== '' ? $this->discrepancyNotes : null,
            ),
            'Transfer discrepancy resolved.',
        )) {
            $this->showResolveModal = false;
            $this->loadTransfer();
        }
    }

    public function cancel(StockTransferService $service): void
    {
        $this->authorize('cancel', $this->transfer);

        $this->validate([
            'cancellationReason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->transfer, auth()->user(), $this->cancellationReason),
            'Stock transfer cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->loadTransfer();
        }
    }

    public function delete(StockTransferService $service)
    {
        $this->authorize('delete', $this->transfer);

        if ($this->runDomainAction(fn () => $service->delete($this->transfer), 'Draft transfer deleted.')) {
            return redirect()->route('distribution.transfers.index');
        }

        return null;
    }

    public function render()
    {
        $approval = ApprovalRequest::query()
            ->where('document_type', $this->transfer->approvalDocumentType())
            ->where('document_id', $this->transfer->id)
            ->latest('id')
            ->first();

        return view('livewire.distribution.transfers.show', [
            'approval' => $approval,
            'outstandingDispatches' => $this->outstandingDispatches(),
            'pendingDiscrepancies' => $this->transfer->discrepancies
                ->filter(fn (StockTransferDiscrepancy $discrepancy) => $discrepancy->resolution->isPending())
                ->values(),
        ]);
    }

    /**
     * @return Collection<int, StockTransferDispatch>
     */
    private function outstandingDispatches(): Collection
    {
        return $this->transfer->dispatches
            ->filter(fn (StockTransferDispatch $dispatch) => $dispatch->status === 'posted'
                && $dispatch->items->contains(
                    fn (StockTransferDispatchItem $item) => Decimal::compare(
                        $item->received_quantity,
                        $item->quantity,
                    ) < 0
                ))
            ->values();
    }

    private function buildReceiveLines(): void
    {
        $this->receiveLines = [];

        if ($this->receiveDispatchId === null) {
            return;
        }

        $dispatch = StockTransferDispatch::query()
            ->with(['items.item', 'items.transferItem.unitOfMeasure'])
            ->find($this->receiveDispatchId);

        if ($dispatch === null) {
            return;
        }

        foreach ($dispatch->items as $item) {
            $remaining = Decimal::subtract($item->quantity, $item->received_quantity, Decimal::QUANTITY_SCALE);

            if (! Decimal::isPositive($remaining)) {
                continue;
            }

            $this->receiveLines[] = [
                'dispatch_item_id' => $item->id,
                'item' => $item->item?->name ?? 'Unknown item',
                'code' => $item->item?->code ?? '—',
                'unit' => $item->transferItem?->unitOfMeasure?->code ?? '—',
                'remaining' => $remaining,
                'received_quantity' => $remaining,
                'damaged_quantity' => '0',
                'missing_quantity' => '0',
            ];
        }
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function batchOptions(int $warehouseId, int $itemId): array
    {
        return InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->where('available_quantity', '>', 0)
            ->whereNotNull('batch_id')
            ->whereHas('dimension', fn ($query) => $query
                ->where('type', StockDimensionType::Warehouse->value)
                ->where('is_active', true))
            ->with('batch')
            ->get()
            ->map(fn (InventoryBalance $balance): ?array => $balance->batch !== null ? [
                'id' => (int) $balance->batch->id,
                'label' => (string) $balance->batch->batch_number,
            ] : null)
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }

    private function loadTransfer(): void
    {
        $this->transfer = $this->transfer->fresh([
            'sourceWarehouse',
            'destinationWarehouse',
            'requester',
            'approvedBy',
            'items.item',
            'items.unitOfMeasure',
            'dispatches.items.item',
            'dispatches.items.transferItem.unitOfMeasure',
            'dispatches.dispatchedBy',
            'receipts.receivedBy',
            'discrepancies.dispatchItem.item',
            'discrepancies.resolvedBy',
        ]);
    }
}
