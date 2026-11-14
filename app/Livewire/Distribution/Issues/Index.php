<?php

namespace App\Livewire\Distribution\Issues;

use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Services\StockIssueService;
use App\Enums\IssueStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock Issues')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockIssue::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $issueId, StockIssueService $service): void
    {
        $issue = StockIssue::findOrFail($issueId);
        $this->authorize('delete', $issue);

        if ($this->runDomainAction(fn () => $service->delete($issue), 'Draft stock issue deleted.')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $issues = StockIssue::query()
            ->with(['requisition', 'warehouse', 'recipient', 'issuer', 'acknowledgement'])
            ->visibleTo(auth()->user())
            ->search($this->search)
            ->when(
                $this->statusFilter !== '',
                fn (Builder $query) => $query->where('status', $this->statusFilter),
            )
            ->latest('id')
            ->paginate(15);

        return view('livewire.distribution.issues.index', [
            'issues' => $issues,
            'statusOptions' => IssueStatus::cases(),
        ]);
    }
}
