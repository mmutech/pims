<?php

namespace App\Livewire\Distribution\Returns;

use App\Domain\Distribution\Models\MaterialReturn;
use App\Domain\Distribution\Models\MaterialReturnItem;
use App\Domain\Distribution\Services\MaterialReturnService;
use App\Enums\ConditionDisposition;
use App\Enums\MaterialCondition;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Material Return')]
class Show extends Component
{
    use HandlesDomainActions;

    public MaterialReturn $materialReturn;

    public bool $showAssessmentModal = false;

    /** @var list<array<string, mixed>> */
    public array $assessmentLines = [];

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public function mount(MaterialReturn $materialReturn): void
    {
        $this->authorize('view', $materialReturn);

        $this->materialReturn = $materialReturn;
        $this->loadReturn();
    }

    public function openAssessmentModal(): void
    {
        $this->authorize('assess', $this->materialReturn);

        $this->assessmentLines = $this->materialReturn->items
            ->map(function (MaterialReturnItem $item): array {
                $latest = $item->assessments->sortByDesc('id')->first();

                return [
                    'material_return_item_id' => $item->id,
                    'item' => $item->item?->name ?? 'Unknown item',
                    'code' => $item->item?->code ?? '—',
                    'returned_quantity' => (string) $item->returned_quantity,
                    'condition' => $latest?->condition->value ?? $item->condition->value,
                    'accepted_quantity' => (string) ($latest?->accepted_quantity ?? $item->returned_quantity),
                    'rejected_quantity' => (string) ($latest?->rejected_quantity ?? '0'),
                    'disposition' => $latest?->disposition->value ?? $this->defaultDisposition($item->condition)->value,
                    'findings' => (string) ($latest?->findings ?? ''),
                ];
            })
            ->all();

        $this->showAssessmentModal = true;
    }

    public function assess(MaterialReturnService $service): void
    {
        $this->authorize('assess', $this->materialReturn);

        $this->validate([
            'assessmentLines' => ['required', 'array', 'min:1'],
            'assessmentLines.*.condition' => ['required', 'string', 'in:good,damaged,defective,quarantined,repairable,disposal_pending'],
            'assessmentLines.*.disposition' => ['required', 'string', 'in:return_to_stock,quarantine,repair,dispose,return_to_supplier'],
            'assessmentLines.*.accepted_quantity' => ['nullable', 'numeric', 'min:0'],
            'assessmentLines.*.rejected_quantity' => ['nullable', 'numeric', 'min:0'],
            'assessmentLines.*.findings' => ['nullable', 'string', 'max:2000'],
        ]);

        $assessments = [];

        foreach ($this->assessmentLines as $line) {
            $assessments[(int) $line['material_return_item_id']] = [
                'material_return_item_id' => (int) $line['material_return_item_id'],
                'condition' => (string) $line['condition'],
                'accepted_quantity' => (string) ($line['accepted_quantity'] ?? '0'),
                'rejected_quantity' => (string) ($line['rejected_quantity'] ?? '0'),
                'disposition' => (string) $line['disposition'],
                'findings' => ($line['findings'] ?? '') !== '' ? (string) $line['findings'] : null,
            ];
        }

        if ($this->runDomainAction(
            fn () => $service->assess($this->materialReturn, $assessments, auth()->user()),
            'Condition assessment recorded.',
        )) {
            $this->showAssessmentModal = false;
            $this->loadReturn();
        }
    }

    public function post(MaterialReturnService $service): void
    {
        $this->authorize('post', $this->materialReturn);

        if ($this->runDomainAction(
            fn () => $service->post($this->materialReturn, auth()->user()),
            'Material return posted. Only good-condition quantities re-entered available stock.',
        )) {
            $this->loadReturn();
        }
    }

    public function cancel(MaterialReturnService $service): void
    {
        $this->authorize('cancel', $this->materialReturn);

        $this->validate([
            'cancellationReason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->runDomainAction(
            fn () => $service->cancel($this->materialReturn, auth()->user(), $this->cancellationReason),
            'Material return cancelled.',
        )) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->loadReturn();
        }
    }

    public function delete(MaterialReturnService $service)
    {
        $this->authorize('delete', $this->materialReturn);

        if ($this->runDomainAction(fn () => $service->delete($this->materialReturn), 'Material return deleted.')) {
            return redirect()->route('distribution.returns.index');
        }

        return null;
    }

    public function render()
    {
        return view('livewire.distribution.returns.show', [
            'conditions' => MaterialCondition::cases(),
            'dispositions' => ConditionDisposition::cases(),
        ]);
    }

    private function loadReturn(): void
    {
        $this->materialReturn = $this->materialReturn->fresh([
            'originalIssue.requisition.project',
            'warehouse',
            'returnedBy',
            'receiver',
            'items.item',
            'items.unitOfMeasure',
            'items.batch',
            'items.dimension',
            'items.assessments.assessor',
            'transaction',
        ]);
    }

    private function defaultDisposition(MaterialCondition $condition): ConditionDisposition
    {
        return match ($condition) {
            MaterialCondition::Good => ConditionDisposition::ReturnToStock,
            MaterialCondition::Damaged,
            MaterialCondition::Defective,
            MaterialCondition::Quarantined,
            MaterialCondition::Repairable => ConditionDisposition::Quarantine,
            MaterialCondition::DisposalPending => ConditionDisposition::Dispose,
        };
    }
}
