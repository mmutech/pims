<?php

namespace App\Livewire\Distribution\Returns;

use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Distribution\Models\MaterialReturn;
use App\Domain\Distribution\Models\MaterialReturnItem;
use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Services\MaterialReturnService;
use App\DTOs\Distribution\MaterialReturnData;
use App\Enums\IssueStatus;
use App\Enums\MaterialCondition;
use App\Enums\MaterialReturnType;
use App\Enums\ReturnStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Livewire\Concerns\UsesDistributionOptions;
use App\Support\Decimal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Material Return')]
class Form extends Component
{
    use HandlesDomainActions, UsesDistributionOptions;

    public ?int $returnId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?MaterialReturn $return = null): void
    {
        if ($return !== null && $return->exists) {
            $this->authorize('update', $return);

            $return->load(['items.item', 'items.unitOfMeasure', 'items.batch']);

            $this->returnId = $return->id;
            $this->form = [
                'return_type' => $return->return_type->value,
                'warehouse_id' => (string) $return->warehouse_id,
                'original_issue_id' => $return->original_issue_id !== null ? (string) $return->original_issue_id : '',
                'returned_by' => (string) $return->returned_by,
                'reason' => (string) $return->reason,
            ];

            $this->items = $return->items->map(fn (MaterialReturnItem $item): array => [
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'item' => $item->item?->name ?? 'Unknown item',
                'code' => $item->item?->code ?? '—',
                'unit' => $item->unitOfMeasure?->code ?? '—',
                'max' => (string) $item->returned_quantity,
                'returned_quantity' => (string) $item->returned_quantity,
                'condition' => $item->condition->value,
                'batch_id' => $item->batch_id !== null ? (string) $item->batch_id : '',
                'requires_batch' => (bool) ($item->item?->tracking_type?->requiresBatch() ?? false),
                'batches' => [],
                'serial_number_id' => $item->serial_number_id,
                'notes' => (string) $item->notes,
            ])->all();

            return;
        }

        $this->authorize('create', MaterialReturn::class);

        $warehouses = $this->accessibleWarehouses();
        $issueId = (int) request()->query('issue', 0);

        $this->form = [
            'return_type' => MaterialReturnType::Project->value,
            'warehouse_id' => (string) ($warehouses->first()->id ?? ''),
            'original_issue_id' => $issueId > 0 ? (string) $issueId : '',
            'returned_by' => (string) auth()->id(),
            'reason' => '',
        ];

        if ($issueId > 0) {
            $this->loadIssueLines();
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'form.original_issue_id') {
            $this->loadIssueLines();
        }
    }

    public function save(MaterialReturnService $service)
    {
        $return = $this->returnId !== null
            ? MaterialReturn::query()->findOrFail($this->returnId)
            : null;

        $return !== null
            ? $this->authorize('update', $return)
            : $this->authorize('create', MaterialReturn::class);

        $this->validate([
            'form.return_type' => ['required', 'string', 'in:project,department'],
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.original_issue_id' => ['required', 'integer', 'exists:stock_issues,id'],
            'form.returned_by' => ['required', 'integer', 'exists:users,id'],
            'form.reason' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.returned_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.condition' => ['required', 'string', 'in:good,damaged,defective,quarantined,repairable,disposal_pending'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
        ]);

        $lines = [];

        foreach ($this->items as $item) {
            $quantity = (string) $item['returned_quantity'];

            if (Decimal::compare($quantity, (string) $item['max']) > 0) {
                $this->error(sprintf(
                    'Return quantity for %s exceeds the %s available to return.',
                    $item['item'],
                    $item['max'],
                ));

                return null;
            }

            if ($item['requires_batch'] && ($item['batch_id'] ?? '') === '') {
                $this->error(sprintf('A batch must be selected for %s.', $item['item']));

                return null;
            }

            $lines[] = [
                'item_id' => (int) $item['item_id'],
                'unit_of_measure_id' => (int) $item['unit_of_measure_id'],
                'returned_quantity' => $quantity,
                'condition' => (string) $item['condition'],
                'unit_cost' => '0',
                'batch_id' => ($item['batch_id'] ?? '') !== '' ? (int) $item['batch_id'] : null,
                'serial_number_id' => $item['serial_number_id'] ?? null,
                'notes' => ($item['notes'] ?? '') !== '' ? (string) $item['notes'] : null,
            ];
        }

        $data = MaterialReturnData::fromArray([
            ...$this->form,
            'returned_by' => (int) $this->form['returned_by'],
            'items' => $lines,
        ]);

        $saved = null;

        $ok = $this->runDomainAction(
            function () use ($service, $return, $data, &$saved): void {
                $saved = $return !== null
                    ? $service->update($return, $data, auth()->user())
                    : $service->create($data, auth()->user());
            },
            $return !== null ? 'Material return updated.' : 'Material return created as a draft.',
        );

        if ($ok && $saved !== null) {
            return redirect()->route('distribution.returns.show', $saved);
        }

        return null;
    }

    private function loadIssueLines(): void
    {
        $this->items = [];

        $issueId = (int) ($this->form['original_issue_id'] ?? 0);

        if ($issueId === 0) {
            return;
        }

        $issue = StockIssue::query()
            ->with(['items.item', 'items.unitOfMeasure'])
            ->find($issueId);

        if ($issue === null) {
            $this->error('The selected stock issue could not be found.');

            return;
        }

        if ($issue->status !== IssueStatus::Posted) {
            $this->error('Only posted stock issues can be returned against.');

            return;
        }

        $this->form['warehouse_id'] = (string) $issue->warehouse_id;

        $previouslyReturned = MaterialReturnItem::query()
            ->join('material_returns', 'material_returns.id', '=', 'material_return_items.material_return_id')
            ->where('material_returns.original_issue_id', $issue->id)
            ->whereNull('material_returns.deleted_at')
            ->whereNotIn('material_returns.status', [
                ReturnStatus::Cancelled->value,
                ReturnStatus::Rejected->value,
            ])
            ->selectRaw('material_return_items.item_id, SUM(material_return_items.returned_quantity) as returned_total')
            ->groupBy('material_return_items.item_id')
            ->pluck('returned_total', 'item_id');

        foreach ($issue->items->groupBy('item_id') as $itemId => $issueItems) {
            $issued = $issueItems->reduce(
                fn (string $carry, $line): string => Decimal::add($carry, $line->issued_quantity, Decimal::QUANTITY_SCALE),
                Decimal::normalize('0'),
            );

            $returned = Decimal::normalize(
                (string) ($previouslyReturned[$itemId] ?? '0'),
                Decimal::QUANTITY_SCALE,
            );

            $remaining = Decimal::subtract($issued, $returned, Decimal::QUANTITY_SCALE);

            if (! Decimal::isPositive($remaining)) {
                continue;
            }

            $first = $issueItems->first();
            $batchIds = $issueItems->pluck('batch_id')->filter()->unique()->values();
            $batches = $batchIds->isNotEmpty()
                ? ItemBatch::query()
                    ->whereIn('id', $batchIds->all())
                    ->orderBy('batch_number')
                    ->get()
                    ->map(fn (ItemBatch $batch): array => [
                        'id' => (int) $batch->id,
                        'label' => (string) $batch->batch_number,
                    ])
                    ->all()
                : [];

            $requiresSerial = (bool) ($first->item?->tracking_type?->requiresSerial() ?? false);

            $this->items[] = [
                'item_id' => (string) $first->item_id,
                'unit_of_measure_id' => (string) $first->unit_of_measure_id,
                'item' => $first->item?->name ?? 'Unknown item',
                'code' => $first->item?->code ?? '—',
                'unit' => $first->unitOfMeasure?->code ?? '—',
                'max' => $remaining,
                'returned_quantity' => $requiresSerial ? '1' : $remaining,
                'condition' => MaterialCondition::Good->value,
                'batch_id' => $batchIds->count() === 1 ? (string) $batchIds->first() : '',
                'requires_batch' => (bool) ($first->item?->tracking_type?->requiresBatch() ?? false),
                'batches' => $batches,
                'serial_number_id' => $first->serial_number_id,
                'notes' => '',
            ];
        }

        if ($this->items === []) {
            $this->error('The selected issue has no remaining quantity available to return.');
        }
    }

    public function render()
    {
        $issues = StockIssue::query()
            ->with('requisition')
            ->where('status', IssueStatus::Posted)
            ->visibleTo(auth()->user())
            ->latest('id')
            ->limit(100)
            ->get();

        return view('livewire.distribution.returns.form', [
            'warehouses' => $this->accessibleWarehouses(),
            'users' => $this->scopedUsers(),
            'issues' => $issues,
            'conditions' => MaterialCondition::cases(),
        ]);
    }
}
