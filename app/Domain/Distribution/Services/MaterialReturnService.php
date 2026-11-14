<?php

namespace App\Domain\Distribution\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Catalog\Models\Item;
use App\Domain\Distribution\Models\MaterialReturn;
use App\Domain\Distribution\Models\MaterialReturnAssessment;
use App\Domain\Distribution\Models\MaterialReturnItem;
use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Models\StockIssueItem;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Distribution\MaterialReturnData;
use App\DTOs\Distribution\MaterialReturnItemData;
use App\DTOs\Inventory\StockPostingData;
use App\DTOs\Inventory\StockPostingLineData;
use App\Enums\ConditionDisposition;
use App\Enums\DocumentType;
use App\Enums\IssueStatus;
use App\Enums\MaterialCondition;
use App\Enums\MaterialReturnType;
use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Project and department material returns.
 *
 * A return is always tied to a posted stock issue. Condition assessment is
 * mandatory before posting: only accepted good-condition quantities re-enter
 * available stock, while damaged or defective quantities are held in a
 * non-available quarantine dimension. Supplier returns remain owned by the
 * receiving module.
 */
class MaterialReturnService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly InventoryPostingService $posting,
    ) {}

    public function create(MaterialReturnData $data, User $actor): MaterialReturn
    {
        $this->validateHeader($data);
        $this->validateItems($data);

        return DB::transaction(function () use ($data, $actor): MaterialReturn {
            $return = MaterialReturn::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::MaterialReturn),
                'return_type' => $data->returnType,
                'original_issue_id' => $data->originalIssueId,
                'original_grn_id' => $data->originalGrnId,
                'supplier_id' => $data->supplierId,
                'warehouse_id' => $data->warehouseId,
                'returned_by' => $data->returnedBy,
                'reason' => $data->reason,
                'status' => ReturnStatus::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncItems($return, $data);

            return $return->refresh()->load('items.item', 'items.unitOfMeasure');
        });
    }

    public function update(MaterialReturn $return, MaterialReturnData $data, User $actor): MaterialReturn
    {
        if (! $return->canBeEdited()) {
            throw new DomainException('Only draft or pending-inspection returns can be edited.');
        }

        if ($return->items()->whereHas('assessments')->exists()) {
            throw new DomainException('Assessed returns cannot be edited.');
        }

        $this->validateHeader($data);
        $this->validateItems($data, $return->id);

        return DB::transaction(function () use ($return, $data, $actor): MaterialReturn {
            $return->update([
                'return_type' => $data->returnType,
                'original_issue_id' => $data->originalIssueId,
                'original_grn_id' => $data->originalGrnId,
                'supplier_id' => $data->supplierId,
                'warehouse_id' => $data->warehouseId,
                'returned_by' => $data->returnedBy,
                'reason' => $data->reason,
                'updated_by' => $actor->id,
            ]);

            $return->items()->delete();
            $this->syncItems($return, $data);

            return $return->refresh()->load('items.item', 'items.unitOfMeasure');
        });
    }

    /**
     * Record the condition assessment for every return line.
     *
     * Each line accepts an array with:
     *  - condition: MaterialCondition value
     *  - accepted_quantity: string
     *  - rejected_quantity: string
     *  - disposition: ConditionDisposition value
     *  - findings / evidence_path: optional text
     *
     * @param  array<int|string, array<string, mixed>>  $assessments  keyed by return item id
     */
    public function assess(MaterialReturn $return, array $assessments, User $actor): MaterialReturn
    {
        return DB::transaction(function () use ($return, $assessments, $actor): MaterialReturn {
            $return = MaterialReturn::query()->lockForUpdate()->findOrFail($return->id);

            if (! $return->canBeAssessed()) {
                throw new DomainException('This return has already been assessed or posted.');
            }

            $return->load('items.item');

            if ($return->items->isEmpty()) {
                throw new DomainException('A material return must contain at least one line.');
            }

            $normalized = $this->normalizeAssessments($return, $assessments);

            foreach ($return->items as $item) {
                if (! array_key_exists($item->id, $normalized)) {
                    throw new DomainException('Every return line must be assessed before posting.');
                }

                $line = $normalized[$item->id];

                $this->validateAssessment(
                    $item,
                    $line['condition'],
                    $line['disposition'],
                    $line['accepted_quantity'],
                    $line['rejected_quantity'],
                );

                MaterialReturnAssessment::create([
                    'material_return_item_id' => $item->id,
                    'assessor_id' => $actor->id,
                    'assessed_at' => now(),
                    'condition' => $line['condition'],
                    'accepted_quantity' => $line['accepted_quantity'],
                    'rejected_quantity' => $line['rejected_quantity'],
                    'disposition' => $line['disposition'],
                    'findings' => $line['findings'],
                    'evidence_path' => $line['evidence_path'],
                ]);

                $item->forceFill([
                    'condition' => $line['condition'],
                    'accepted_quantity' => $line['accepted_quantity'],
                    'rejected_quantity' => $line['rejected_quantity'],
                ])->save();
            }

            $return->forceFill([
                'status' => ReturnStatus::Assessed,
                'updated_by' => $actor->id,
            ])->save();

            activity('distribution')
                ->causedBy($actor)
                ->performedOn($return)
                ->log('material return assessed');

            return $return->refresh()->load('items.item', 'items.unitOfMeasure', 'items.assessments');
        });
    }

    /**
     * Post accepted quantities back to stock.
     *
     * Good-condition returns re-enter the available warehouse dimension.
     * Damaged/repairable material is posted into quarantine and never becomes
     * available for issue. Disposal or return-to-supplier lines create no stock
     * movement because the material never re-entered inventory.
     */
    public function post(MaterialReturn $return, User $actor): MaterialReturn
    {
        if ($return->status === ReturnStatus::Posted) {
            return $return;
        }

        if (! $return->canBePosted()) {
            throw new DomainException('Only assessed material returns can be posted.');
        }

        return DB::transaction(function () use ($return, $actor): MaterialReturn {
            $return = MaterialReturn::query()->lockForUpdate()->findOrFail($return->id);
            $return->load('items.item', 'items.assessments');

            if ($return->items->isEmpty()) {
                throw new DomainException('A material return must contain at least one line.');
            }

            $lines = [];
            $contexts = [];

            foreach ($return->items as $item) {
                $assessment = $item->assessments->sortByDesc('id')->first();

                if ($assessment === null) {
                    throw new DomainException('Every return line must be assessed before posting.');
                }

                $accepted = Decimal::normalize($item->accepted_quantity, Decimal::QUANTITY_SCALE);

                if (! Decimal::isPositive($accepted)) {
                    continue;
                }

                $dimensionType = $assessment->disposition->targetDimensionType();

                if ($dimensionType === null) {
                    continue;
                }

                if ($assessment->disposition === ConditionDisposition::ReturnToStock
                    && ! $item->condition->isAvailableForStock()) {
                    throw new DomainException('Only good-condition returns can re-enter available stock.');
                }

                if ($assessment->disposition !== ConditionDisposition::ReturnToStock
                    && $item->condition->isAvailableForStock()) {
                    throw new DomainException('Good-condition returns must be returned to stock.');
                }

                $dimension = StockDimension::resolve($return->warehouse_id, type: $dimensionType);
                $unitCost = Decimal::isPositive($item->unit_cost)
                    ? Decimal::normalize($item->unit_cost, Decimal::COST_SCALE)
                    : '0';

                $lines[] = new StockPostingLineData(
                    itemId: $item->item_id,
                    unitOfMeasureId: $item->unit_of_measure_id,
                    stockDimensionId: $dimension->id,
                    quantity: $accepted,
                    unitCost: $unitCost,
                    batchId: $item->batch_id,
                    serialNumberId: $item->serial_number_id,
                    reference: 'material_return_item:'.$item->id,
                );

                $contexts[] = ['item' => $item];
            }

            $transactionId = null;

            if ($lines !== []) {
                $transaction = $this->posting->post(new StockPostingData(
                    movementType: StockMovementType::ReturnIn,
                    source: $return,
                    operation: 'material_return_post',
                    warehouseId: $return->warehouse_id,
                    postedBy: $actor->id,
                    lines: $lines,
                    metadata: [
                        'material_return_id' => $return->id,
                        'original_issue_id' => $return->original_issue_id,
                    ],
                ));

                $transaction->loadMissing('items');

                foreach ($contexts as $index => $context) {
                    $transactionItem = $transaction->items->get($index);

                    if ($transactionItem === null) {
                        continue;
                    }

                    /** @var MaterialReturnItem $item */
                    $item = $context['item'];

                    $item->forceFill([
                        'stock_dimension_id' => $transactionItem->stock_dimension_id,
                        'stock_transaction_item_id' => $transactionItem->id,
                        'unit_cost' => $transactionItem->unit_cost,
                    ])->save();
                }

                $transactionId = $transaction->id;
            }

            $return->forceFill([
                'status' => ReturnStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor->id,
                'stock_transaction_id' => $transactionId,
                'updated_by' => $actor->id,
            ])->save();

            activity('distribution')
                ->causedBy($actor)
                ->performedOn($return)
                ->withProperties(['stock_transaction_id' => $transactionId])
                ->log('material return posted');

            return $return->refresh()->load('items.item', 'items.unitOfMeasure');
        });
    }

    public function cancel(MaterialReturn $return, User $actor, string $reason): MaterialReturn
    {
        if (! $return->canBeCancelled()) {
            throw new DomainException('This material return cannot be cancelled.');
        }

        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        $return->forceFill([
            'status' => ReturnStatus::Cancelled,
            'updated_by' => $actor->id,
        ])->save();

        activity('distribution')
            ->causedBy($actor)
            ->performedOn($return)
            ->withProperties(['reason' => $reason])
            ->log('material return cancelled');

        return $return->refresh();
    }

    public function delete(MaterialReturn $return): void
    {
        if (! $return->canBeEdited()) {
            throw new DomainException('Only draft or pending-inspection returns can be deleted.');
        }

        $return->delete();
    }

    private function validateHeader(MaterialReturnData $data): void
    {
        if ($data->returnType === MaterialReturnType::Supplier) {
            throw new DomainException('Supplier returns are handled by the receiving module.');
        }

        if (blank(trim($data->reason))) {
            throw new DomainException('A return reason is required.');
        }

        if (Warehouse::query()->whereKey($data->warehouseId)->doesntExist()) {
            throw new DomainException('The warehouse could not be found.');
        }

        if ($data->returnType->requiresOriginalIssue() && $data->originalIssueId === null) {
            throw new DomainException('Project and department returns must reference the original stock issue.');
        }

        if ($data->originalIssueId !== null) {
            $issue = StockIssue::query()->find($data->originalIssueId);

            if ($issue === null) {
                throw new DomainException('The original stock issue could not be found.');
            }

            if ((int) $issue->warehouse_id !== $data->warehouseId) {
                throw new DomainException('The return warehouse does not match the original issue warehouse.');
            }

            if ($issue->status !== IssueStatus::Posted) {
                throw new DomainException('Only posted stock issues can be returned against.');
            }
        }
    }

    private function validateItems(MaterialReturnData $data, ?int $excludeReturnId = null): void
    {
        if ($data->items === []) {
            throw new DomainException('A material return must contain at least one line.');
        }

        $issueItems = [];

        if ($data->originalIssueId !== null) {
            $issueItems = StockIssueItem::query()
                ->where('stock_issue_id', $data->originalIssueId)
                ->get()
                ->groupBy('item_id');
        }

        $seen = [];

        foreach ($data->items as $line) {
            $quantity = Decimal::normalize($line->returnedQuantity, Decimal::QUANTITY_SCALE);

            if (! Decimal::isPositive($quantity)) {
                throw new DomainException('Returned quantities must be greater than zero.');
            }

            if (isset($seen[$line->itemId])) {
                throw new DomainException('Duplicate item lines are not allowed. Combine quantities instead.');
            }

            $seen[$line->itemId] = true;

            $item = Item::query()->find($line->itemId);

            if ($item === null) {
                throw new DomainException('A return line references an unknown item.');
            }

            if ($item->tracking_type->requiresBatch() && $line->batchId === null) {
                throw new DomainException(sprintf(
                    '%s is batch tracked and requires a batch on the return.',
                    $item->name,
                ));
            }

            if ($item->tracking_type->requiresSerial()) {
                if ($line->serialNumberId === null) {
                    throw new DomainException(sprintf(
                        '%s is serial tracked and requires a serial number on the return.',
                        $item->name,
                    ));
                }

                if (Decimal::compare($quantity, '1') !== 0) {
                    throw new DomainException('Serial-tracked return lines must have a quantity of exactly one.');
                }
            }

            if ($data->originalIssueId !== null) {
                $issued = $this->issuedQuantity($issueItems->get($line->itemId) ?? collect());

                if (! Decimal::isPositive($issued)) {
                    throw new DomainException(sprintf(
                        '%s does not appear on the original stock issue.',
                        $item->name,
                    ));
                }

                $previouslyReturned = $this->returnedQuantity(
                    $data->originalIssueId,
                    $line->itemId,
                    $excludeReturnId,
                );

                if (Decimal::compare(
                    Decimal::add($previouslyReturned, $quantity, Decimal::QUANTITY_SCALE),
                    $issued,
                ) > 0) {
                    throw new DomainException(sprintf(
                        'Return quantity for %s exceeds the %s issued less previous returns.',
                        $item->name,
                        $issued,
                    ));
                }
            }
        }
    }

    /**
     * @param  Collection<int, StockIssueItem>  $items
     */
    private function issuedQuantity($items): string
    {
        return $items->reduce(
            fn (string $carry, StockIssueItem $item): string => Decimal::add(
                $carry,
                $item->issued_quantity,
                Decimal::QUANTITY_SCALE,
            ),
            Decimal::normalize('0'),
        );
    }

    private function returnedQuantity(int $issueId, int $itemId, ?int $excludeReturnId = null): string
    {
        $query = MaterialReturnItem::query()
            ->join('material_returns', 'material_returns.id', '=', 'material_return_items.material_return_id')
            ->where('material_returns.original_issue_id', $issueId)
            ->whereNull('material_returns.deleted_at')
            ->where('material_return_items.item_id', $itemId)
            ->whereNotIn('material_returns.status', [
                ReturnStatus::Cancelled->value,
                ReturnStatus::Rejected->value,
            ]);

        if ($excludeReturnId !== null) {
            $query->where('material_return_items.material_return_id', '<>', $excludeReturnId);
        }

        return Decimal::normalize(
            (string) $query->sum('material_return_items.returned_quantity'),
            Decimal::QUANTITY_SCALE,
        );
    }

    private function syncItems(MaterialReturn $return, MaterialReturnData $data): void
    {
        $issueItems = $data->originalIssueId !== null
            ? StockIssueItem::query()
                ->where('stock_issue_id', $data->originalIssueId)
                ->get()
                ->groupBy('item_id')
            : collect();

        foreach ($data->items as $index => $line) {
            MaterialReturnItem::create([
                'material_return_id' => $return->id,
                'item_id' => $line->itemId,
                'unit_of_measure_id' => $line->unitOfMeasureId,
                'returned_quantity' => Decimal::normalize($line->returnedQuantity, Decimal::QUANTITY_SCALE),
                'accepted_quantity' => '0',
                'rejected_quantity' => '0',
                'condition' => $line->condition,
                'batch_id' => $line->batchId,
                'serial_number_id' => $line->serialNumberId,
                'unit_cost' => $this->resolveUnitCost($line, $issueItems),
                'notes' => $line->notes,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  Collection<int, StockIssueItem>  $issueItems
     */
    private function resolveUnitCost(MaterialReturnItemData $line, $issueItems): string
    {
        if (Decimal::isPositive($line->unitCost)) {
            return Decimal::normalize($line->unitCost, Decimal::COST_SCALE);
        }

        /** @var StockIssueItem|null $issueItem */
        $issueItem = $issueItems->get($line->itemId)?->first();

        if ($issueItem !== null && Decimal::isPositive($issueItem->unit_cost)) {
            return Decimal::normalize($issueItem->unit_cost, Decimal::COST_SCALE);
        }

        return '0';
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $assessments
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAssessments(MaterialReturn $return, array $assessments): array
    {
        $items = $return->items->keyBy('id');
        $normalized = [];

        foreach ($assessments as $key => $entry) {
            $entry = is_array($entry) ? $entry : (array) $entry;

            $itemId = isset($entry['material_return_item_id'])
                ? (int) $entry['material_return_item_id']
                : (int) $key;

            /** @var MaterialReturnItem|null $item */
            $item = $items->get($itemId);

            if ($item === null) {
                throw new DomainException('An assessment references an unknown return line.');
            }

            $condition = $entry['condition'] ?? $item->condition;
            $condition = $condition instanceof MaterialCondition
                ? $condition
                : MaterialCondition::from((string) $condition);

            $disposition = $entry['disposition'] ?? $this->defaultDisposition($condition);
            $disposition = $disposition instanceof ConditionDisposition
                ? $disposition
                : ConditionDisposition::from((string) $disposition);

            $returned = Decimal::normalize($item->returned_quantity, Decimal::QUANTITY_SCALE);
            $accepted = isset($entry['accepted_quantity'])
                ? Decimal::normalize((string) $entry['accepted_quantity'], Decimal::QUANTITY_SCALE)
                : $returned;
            $rejected = isset($entry['rejected_quantity'])
                ? Decimal::normalize((string) $entry['rejected_quantity'], Decimal::QUANTITY_SCALE)
                : Decimal::subtract($returned, $accepted, Decimal::QUANTITY_SCALE);

            $normalized[$itemId] = [
                'condition' => $condition,
                'accepted_quantity' => $accepted,
                'rejected_quantity' => $rejected,
                'disposition' => $disposition,
                'findings' => isset($entry['findings']) ? (string) $entry['findings'] : null,
                'evidence_path' => isset($entry['evidence_path']) ? (string) $entry['evidence_path'] : null,
            ];
        }

        return $normalized;
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

    private function validateAssessment(
        MaterialReturnItem $item,
        MaterialCondition $condition,
        ConditionDisposition $disposition,
        string $accepted,
        string $rejected,
    ): void {
        if (Decimal::isNegative($accepted) || Decimal::isNegative($rejected)) {
            throw new DomainException('Assessment quantities cannot be negative.');
        }

        $accounted = Decimal::add($accepted, $rejected, Decimal::QUANTITY_SCALE);

        if (Decimal::compare($accounted, $item->returned_quantity) !== 0) {
            throw new DomainException('Accepted and rejected quantities must equal the returned quantity.');
        }

        if ($condition->isAvailableForStock() && $disposition !== ConditionDisposition::ReturnToStock) {
            throw new DomainException('Good-condition returns must be returned to stock.');
        }

        if (! $condition->isAvailableForStock() && $disposition === ConditionDisposition::ReturnToStock) {
            throw new DomainException('Only good-condition returns can re-enter available stock.');
        }

        if ($condition === MaterialCondition::DisposalPending
            && ! in_array($disposition, [ConditionDisposition::Dispose, ConditionDisposition::ReturnToSupplier], true)) {
            throw new DomainException('Disposal-pending returns must be disposed or returned to the supplier.');
        }

        if ($disposition->addsStock() && ! Decimal::isPositive($accepted)) {
            throw new DomainException('Accepted quantity must be greater than zero for stock-holding dispositions.');
        }
    }
}
