<?php

namespace App\Livewire\Approvals;

use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalRequestStageStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('My Approvals')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ApprovalRequest::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $requests = app(ApprovalEngine::class)->pendingFor($user);

        if ($this->search !== '') {
            $term = mb_strtolower($this->search);

            $requests = $requests->filter(function (ApprovalRequest $request) use ($term): bool {
                return str_contains(mb_strtolower($request->document_type), $term)
                    || str_contains(mb_strtolower((string) ($request->metadata['document_number'] ?? '')), $term)
                    || str_contains(mb_strtolower($request->requester?->name ?? ''), $term);
            })->values();
        }

        $overdue = $requests->filter(fn (ApprovalRequest $request) => $request->isOverdue())->count();
        $blocked = $requests->filter(
            fn (ApprovalRequest $request) => $request->currentStage()?->status === ApprovalRequestStageStatus::Blocked,
        )->count();

        $perPage = 15;
        $page = max(1, (int) $this->getPage());
        $total = $requests->count();

        $paginated = new LengthAwarePaginator(
            $requests->slice(($page - 1) * $perPage, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return view('livewire.approvals.index', [
            'requests' => $paginated,
            'overdueCount' => $overdue,
            'blockedCount' => $blocked,
        ]);
    }
}
