<?php

namespace App\Livewire\Distribution\Issues;

use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Services\StockIssueService;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Stock Issue')]
class Show extends Component
{
    use HandlesDomainActions;

    public StockIssue $issue;

    public bool $showReversalModal = false;

    public string $reversalReason = '';

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public bool $showAcknowledgeModal = false;

    public string $acknowledgementComment = '';

    public function mount(StockIssue $issue): void
    {
        $this->authorize('view', $issue);

        $this->issue = $issue;
        $this->loadIssue();
    }

    public function post(StockIssueService $service): void
    {
        $this->authorize('post', $this->issue);

        if ($this->runDomainAction(
            fn () => $service->post($this->issue, auth()->user()),
            'Stock issue posted. Inventory has been updated.',
        )) {
            $this->loadIssue();
        }
    }

    public function reverse(StockIssueService $service): void
    {
        $this->authorize('reverse', $this->issue);

        $this->validate([
            'reversalReason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->reverse($this->issue, auth()->user(), $this->reversalReason),
            'Stock issue reversed. A reversing ledger entry was posted.',
        )) {
            $this->showReversalModal = false;
            $this->reversalReason = '';
            $this->loadIssue();
        }
    }

    public function cancel(StockIssueService $service): void
    {
        $this->authorize('cancel', $this->issue);

        $this->validate([
            'cancellationReason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->issue, auth()->user(), $this->cancellationReason),
            'Draft stock issue cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->loadIssue();
        }
    }

    public function acknowledge(StockIssueService $service): void
    {
        $this->authorize('acknowledge', $this->issue);

        $this->validate([
            'acknowledgementComment' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->acknowledge(
                $this->issue,
                auth()->user(),
                $this->acknowledgementComment !== '' ? $this->acknowledgementComment : null,
                null,
                request()->ip(),
            ),
            'Receipt of the issued material has been acknowledged.',
        )) {
            $this->showAcknowledgeModal = false;
            $this->acknowledgementComment = '';
            $this->loadIssue();
        }
    }

    public function delete(StockIssueService $service)
    {
        $this->authorize('delete', $this->issue);

        if ($this->runDomainAction(fn () => $service->delete($this->issue), 'Draft stock issue deleted.')) {
            return redirect()->route('distribution.issues.index');
        }

        return null;
    }

    public function render()
    {
        return view('livewire.distribution.issues.show');
    }

    private function loadIssue(): void
    {
        $this->issue = $this->issue->fresh([
            'requisition.requestingDepartment',
            'requisition.project',
            'warehouse',
            'recipient',
            'issuer',
            'poster',
            'items.item',
            'items.unitOfMeasure',
            'items.dimension',
            'items.batch',
            'acknowledgement.acknowledgedBy',
            'transaction',
        ]);
    }
}
