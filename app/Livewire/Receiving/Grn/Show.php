<?php

namespace App\Livewire\Receiving\Grn;

use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Domain\Receiving\Services\GoodsReceivedNoteService;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Goods Received Note')]
class Show extends Component
{
    use HandlesDomainActions;

    public GoodsReceivedNote $grn;

    public bool $showReverseModal = false;

    public string $reversalReason = '';

    public function mount(GoodsReceivedNote $grn): void
    {
        $this->authorize('view', $grn);

        $this->grn = $grn;
        $this->loadGrn();
    }

    public function post(GoodsReceivedNoteService $service): void
    {
        $this->authorize('post', $this->grn);

        $this->runDomainAction(
            fn () => $service->post($this->grn, auth()->user()),
            'Goods received note posted to stock.',
        );

        $this->loadGrn();
    }

    public function cancel(GoodsReceivedNoteService $service): void
    {
        $this->authorize('cancel', $this->grn);

        $this->runDomainAction(
            fn () => $service->cancel($this->grn, auth()->user()),
            'Goods received note cancelled.',
        );

        $this->loadGrn();
    }

    public function reverse(GoodsReceivedNoteService $service): void
    {
        $this->authorize('reverse', $this->grn);

        $this->validate([
            'reversalReason' => ['required', 'string', 'max:500'],
        ]);

        $reversed = $this->runDomainAction(
            fn () => $service->reverse($this->grn, auth()->user(), $this->reversalReason),
            'Goods received note reversed.',
        );

        if ($reversed) {
            $this->showReverseModal = false;
            $this->reversalReason = '';
        }

        $this->loadGrn();
    }

    public function render()
    {
        return view('livewire.receiving.grn.show', [
            'acceptedTotal' => $this->grn->items->sum(fn ($item) => (float) $item->accepted_quantity),
            'rejectedTotal' => $this->grn->items->sum(fn ($item) => (float) $item->rejected_quantity),
        ]);
    }

    private function loadGrn(): void
    {
        $this->grn = $this->grn->fresh([
            'purchaseOrder',
            'sra',
            'qualityInspection',
            'warehouse',
            'supplier',
            'creator',
            'poster',
            'reversalOf',
            'stockTransaction',
            'items.item',
            'items.unitOfMeasure',
            'items.batch',
            'items.warehouseBin',
        ]);
    }
}
