<?php

namespace App\Livewire\Procurement\Orders;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Services\PurchaseOrderAmendmentService;
use App\Domain\Procurement\Services\PurchaseOrderService;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Purchase Order')]
class Show extends Component
{
    use HandlesDomainActions;

    public PurchaseOrder $order;

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public bool $showAmendmentModal = false;

    /** @var array<string, mixed> */
    public array $amendmentForm = [];

    public function mount(PurchaseOrder $order): void
    {
        $this->order = $order->load([
            'supplier',
            'deliveryWarehouse',
            'purchaseRequisition',
            'supplierQuotation',
            'items.item',
            'items.unitOfMeasure',
            'amendments',
        ]);

        $this->authorize('view', $this->order);
    }

    public function submit(PurchaseOrderService $service): void
    {
        $this->authorize('submit', $this->order);

        if ($this->runDomainAction(
            fn () => $service->submit($this->order, auth()->user()),
            'Purchase order submitted for approval.',
        )) {
            $this->order->refresh();
        }
    }

    public function cancel(PurchaseOrderService $service): void
    {
        $this->authorize('cancel', $this->order);

        $this->validate(['cancellationReason' => ['required', 'string', 'max:500']]);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->order, auth()->user(), $this->cancellationReason),
            'Purchase order cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->order->refresh();
        }
    }

    public function close(PurchaseOrderService $service): void
    {
        $this->authorize('close', $this->order);

        if ($this->runDomainAction(
            fn () => $service->close($this->order, auth()->user()),
            'Purchase order closed.',
        )) {
            $this->order->refresh();
        }
    }

    public function openAmendmentModal(): void
    {
        $this->authorize('amend', $this->order);

        $this->amendmentForm = [
            'reason' => '',
            'submit_now' => true,
            'items' => $this->order->items->map(fn ($item) => [
                'purchase_order_item_id' => $item->id,
                'amended_quantity' => (string) $item->ordered_quantity,
                'amended_unit_price' => (string) $item->unit_price,
            ])->all(),
        ];

        $this->resetValidation();
        $this->showAmendmentModal = true;
    }

    public function createAmendment(PurchaseOrderAmendmentService $service): void
    {
        $this->authorize('amend', $this->order);

        $this->validate([
            'amendmentForm.reason' => ['required', 'string', 'max:500'],
            'amendmentForm.items' => ['required', 'array', 'min:1'],
            'amendmentForm.items.*.amended_quantity' => ['required', 'numeric', 'min:0'],
            'amendmentForm.items.*.amended_unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $amendment = $service->create(
                $this->order,
                $this->amendmentForm['reason'],
                $this->amendmentForm['items'],
                auth()->user(),
            );

            if ((bool) $this->amendmentForm['submit_now']) {
                $service->submit($amendment, auth()->user());
            }

            $this->showAmendmentModal = false;
            $this->order->refresh();
            $this->success('Amendment recorded.');
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());
        }
    }

    public function render()
    {
        $approval = ApprovalRequest::query()
            ->where('document_type', $this->order->approvalDocumentType())
            ->where('document_id', $this->order->id)
            ->latest('id')
            ->first();

        $amendments = $this->order->amendments()->with('items')->latest('amendment_number')->get();

        $amendmentApprovals = ApprovalRequest::query()
            ->where('document_type', 'purchase_order_amendment')
            ->whereIn('document_id', $amendments->pluck('id'))
            ->latest('id')
            ->get()
            ->unique('document_id')
            ->keyBy('document_id');

        return view('livewire.procurement.orders.show', [
            'approval' => $approval,
            'amendments' => $amendments,
            'amendmentApprovals' => $amendmentApprovals,
        ]);
    }
}
