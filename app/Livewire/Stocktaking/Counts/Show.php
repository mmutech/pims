<?php

namespace App\Livewire\Stocktaking\Counts;

use App\Domain\Stocktaking\Models\StockCount;
use App\Domain\Stocktaking\Models\StockReconciliation;
use App\Domain\Stocktaking\Services\StockCountService;
use App\Domain\Stocktaking\Services\StockReconciliationService;
use App\DTOs\Stocktaking\StockCountEntryData;
use App\Enums\CountStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock Count')]
class Show extends Component
{
    use HandlesDomainActions;

    public StockCount $count;

    public bool $showCountModal = false;

    /** @var list<array<string, mixed>> */
    public array $countLines = [];

    public bool $showRecountModal = false;

    public ?int $recountItemId = null;

    public string $recountQuantity = '';

    public string $recountNotes = '';

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public function mount(StockCount $count): void
    {
        $this->authorize('view', $count);

        $this->count = $count;
        $this->loadCount();
    }

    public function start(StockCountService $service): void
    {
        $this->authorize('start', $this->count);

        if ($this->runDomainAction(
            fn () => $service->start($this->count, auth()->user()),
            'Stock count started and the inventory snapshot captured.',
        )) {
            $this->loadCount();
        }
    }

    public function openCountModal(): void
    {
        $this->authorize('count', $this->count);

        $this->countLines = $this->count->items->map(fn ($item): array => [
            'stock_count_item_id' => $item->id,
            'item' => $item->item?->name ?? 'Unknown item',
            'code' => $item->item?->code ?? '—',
            'unit' => $item->item?->baseUnit?->code ?? '—',
            'batch' => $item->batch?->batch_number ?? '—',
            'system_quantity' => (string) $item->system_quantity,
            'counted_quantity' => $item->counted_quantity !== null ? (string) $item->counted_quantity : '',
            'notes' => (string) ($item->notes ?? ''),
        ])->all();

        $this->showCountModal = true;
    }

    public function saveCounts(StockCountService $service): void
    {
        $this->authorize('count', $this->count);

        $this->validate([
            'countLines' => ['required', 'array', 'min:1'],
            'countLines.*.counted_quantity' => ['nullable', 'numeric', 'min:0'],
            'countLines.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $entries = [];

        foreach ($this->countLines as $line) {
            $quantity = trim((string) ($line['counted_quantity'] ?? ''));

            if ($quantity === '') {
                continue;
            }

            $entries[] = new StockCountEntryData(
                stockCountItemId: (int) $line['stock_count_item_id'],
                countedQuantity: $quantity,
                notes: trim((string) ($line['notes'] ?? '')) !== '' ? (string) $line['notes'] : null,
            );
        }

        if ($entries === []) {
            $this->error('Enter at least one counted quantity.');

            return;
        }

        if ($this->runDomainAction(
            fn () => $service->recordCount($this->count, $entries, auth()->user()),
            'Counted quantities recorded and variances analysed.',
        )) {
            $this->showCountModal = false;
            $this->loadCount();
        }
    }

    public function openRecountModal(int $stockCountItemId): void
    {
        $this->authorize('recount', $this->count);

        $item = $this->count->items->firstWhere('id', $stockCountItemId);

        if ($item === null) {
            $this->error('The count line could not be found.');

            return;
        }

        $this->recountItemId = $item->id;
        $this->recountQuantity = $item->counted_quantity !== null ? (string) $item->counted_quantity : '';
        $this->recountNotes = '';
        $this->showRecountModal = true;
    }

    public function saveRecount(StockCountService $service): void
    {
        $this->authorize('recount', $this->count);

        $this->validate([
            'recountItemId' => ['required', 'integer'],
            'recountQuantity' => ['required', 'numeric', 'min:0'],
            'recountNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->recount(
                $this->count,
                (int) $this->recountItemId,
                $this->recountQuantity,
                auth()->user(),
                $this->recountNotes !== '' ? $this->recountNotes : null,
            ),
            'Recount recorded and the variance recalculated.',
        )) {
            $this->showRecountModal = false;
            $this->recountItemId = null;
            $this->recountQuantity = '';
            $this->recountNotes = '';
            $this->loadCount();
        }
    }

    public function createAdjustments(StockCountService $service): void
    {
        $this->authorize('adjust', $this->count);

        if ($this->runDomainAction(
            fn () => $service->createAdjustments($this->count, auth()->user()),
            'Adjustments created from the open count variances.',
        )) {
            $this->loadCount();
        }
    }

    public function prepareReconciliation(StockReconciliationService $service): void
    {
        $this->authorize('reconcile', $this->count);

        if ($this->runDomainAction(
            fn () => $service->prepare($this->count, auth()->user()),
            'Reconciliation prepared from the current ledger position.',
        )) {
            $this->loadCount();
        }
    }

    public function approveReconciliation(StockReconciliationService $service, int $reconciliationId): void
    {
        $reconciliation = $this->findReconciliation($reconciliationId);

        $this->authorize('approve', $reconciliation);

        if ($this->runDomainAction(
            fn () => $service->approve($reconciliation, auth()->user()),
            'Reconciliation approved.',
        )) {
            $this->loadCount();
        }
    }

    public function postReconciliation(StockReconciliationService $service, int $reconciliationId): void
    {
        $reconciliation = $this->findReconciliation($reconciliationId);

        $this->authorize('post', $reconciliation);

        if ($this->runDomainAction(
            fn () => $service->post($reconciliation, auth()->user()),
            'Reconciliation posted; the count now balances to the ledger.',
        )) {
            $this->loadCount();
        }
    }

    public function complete(StockCountService $service): void
    {
        $this->authorize('complete', $this->count);

        if ($this->runDomainAction(
            fn () => $service->complete($this->count, auth()->user()),
            'Stock count completed.',
        )) {
            $this->loadCount();
        }
    }

    public function cancel(StockCountService $service): void
    {
        $this->authorize('cancel', $this->count);

        $this->validate([
            'cancellationReason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->count, auth()->user(), $this->cancellationReason),
            'Stock count cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->loadCount();
        }
    }

    public function delete(StockCountService $service)
    {
        $this->authorize('delete', $this->count);

        if ($this->runDomainAction(
            fn () => $service->delete($this->count),
            'Stock count deleted.',
        )) {
            return redirect()->route('stocktaking.counts.index');
        }

        return null;
    }

    private function findReconciliation(int $reconciliationId): StockReconciliation
    {
        $reconciliation = StockReconciliation::query()
            ->where('stock_count_id', $this->count->id)
            ->findOrFail($reconciliationId);

        return $reconciliation;
    }

    private function loadCount(): void
    {
        $this->count->refresh()->load([
            'warehouse',
            'creator',
            'scopes.location',
            'scopes.category',
            'scopes.item',
            'items.item.baseUnit',
            'items.batch',
            'items.countedBy',
            'items.recounts.countedBy',
            'variances.stockCountItem.item',
            'variances.adjustment',
            'adjustments',
            'reconciliations.preparedBy',
            'reconciliations.approvedBy',
        ]);
    }

    public function render()
    {
        $reconciliation = $this->count->reconciliations
            ->sortByDesc('id')
            ->first();

        $hideSystemQuantities = $this->count->hidesSystemQuantity()
            && $this->count->status === CountStatus::Counting;

        return view('livewire.stocktaking.counts.show', [
            'reconciliation' => $reconciliation,
            'hideSystemQuantities' => $hideSystemQuantities,
            'openVariances' => $this->count->variances
                ->filter(fn ($variance) => $variance->isOpen())
                ->values(),
        ]);
    }
}
