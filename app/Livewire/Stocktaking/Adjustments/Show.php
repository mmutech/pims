<?php

namespace App\Livewire\Stocktaking\Adjustments;

use App\Domain\Stocktaking\Models\StockAdjustment;
use App\Domain\Stocktaking\Services\StockAdjustmentService;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock Adjustment')]
class Show extends Component
{
    use HandlesDomainActions;

    public StockAdjustment $adjustment;

    public bool $showReverseModal = false;

    public string $reversalReason = '';

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public function mount(StockAdjustment $adjustment): void
    {
        $this->authorize('view', $adjustment);

        $this->adjustment = $adjustment;
        $this->loadAdjustment();
    }

    public function submit(StockAdjustmentService $service): void
    {
        $this->authorize('submit', $this->adjustment);

        if ($this->runDomainAction(
            fn () => $service->submit($this->adjustment, auth()->user()),
            'Stock adjustment submitted for approval.',
        )) {
            $this->loadAdjustment();
        }
    }

    public function post(StockAdjustmentService $service): void
    {
        $this->authorize('post', $this->adjustment);

        if ($this->runDomainAction(
            fn () => $service->post($this->adjustment, auth()->user()),
            'Stock adjustment posted to the ledger.',
        )) {
            $this->loadAdjustment();
        }
    }

    public function reverse(StockAdjustmentService $service): void
    {
        $this->authorize('reverse', $this->adjustment);

        $this->validate([
            'reversalReason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->reverse($this->adjustment, auth()->user(), $this->reversalReason),
            'Stock adjustment reversed with an equal and opposite document.',
        )) {
            $this->showReverseModal = false;
            $this->reversalReason = '';
            $this->loadAdjustment();
        }
    }

    public function cancel(StockAdjustmentService $service): void
    {
        $this->authorize('cancel', $this->adjustment);

        $this->validate([
            'cancellationReason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->adjustment, auth()->user(), $this->cancellationReason),
            'Stock adjustment cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->loadAdjustment();
        }
    }

    public function delete(StockAdjustmentService $service)
    {
        $this->authorize('delete', $this->adjustment);

        if ($this->runDomainAction(
            fn () => $service->delete($this->adjustment),
            'Stock adjustment deleted.',
        )) {
            return redirect()->route('stocktaking.adjustments.index');
        }

        return null;
    }

    private function loadAdjustment(): void
    {
        $this->adjustment->refresh()->load([
            'warehouse',
            'stockCount',
            'creator',
            'approvedBy',
            'postedBy',
            'reversalOf',
            'reversals',
            'items.item',
            'items.unitOfMeasure',
            'items.batch',
            'transaction',
        ]);
    }

    public function render()
    {
        $approval = ApprovalRequest::query()
            ->where('document_type', $this->adjustment->approvalDocumentType())
            ->where('document_id', $this->adjustment->id)
            ->latest('id')
            ->first();

        return view('livewire.stocktaking.adjustments.show', [
            'approval' => $approval,
        ]);
    }
}
