<?php

namespace App\Domain\Stocktaking\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\Domain\Stocktaking\Models\StockAdjustment;
use App\Domain\Stocktaking\Models\StockAdjustmentItem;
use App\Domain\Stocktaking\Models\StockCountVariance;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Services\ApprovalEngineService;
use App\DTOs\Inventory\StockPostingData;
use App\DTOs\Inventory\StockPostingLineData;
use App\DTOs\Stocktaking\StockAdjustmentData;
use App\DTOs\Stocktaking\StockAdjustmentItemData;
use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\DocumentType;
use App\Enums\VarianceResolution;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Permissions;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Stock adjustments are the only Phase 7 documents that move stock directly.
 *
 * Controls:
 *  - every quantity change is posted through InventoryPostingService;
 *  - adjustments at or above the configured threshold need approval;
 *  - the creator cannot approve or post their own adjustment;
 *  - corrections create reversal documents instead of mutating posted rows.
 */
class StockAdjustmentService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly InventoryPostingService $posting,
        private readonly ApprovalEngineService $approvals,
    ) {}

    public function create(StockAdjustmentData $data, User $actor): StockAdjustment
    {
        if (blank($data->reason)) {
            throw new DomainException('An adjustment reason is required.');
        }

        if ($data->items === []) {
            throw new DomainException('A stock adjustment must contain at least one item.');
        }

        $warehouse = Warehouse::query()->find($data->warehouseId);

        if ($warehouse === null) {
            throw new DomainException('The adjustment references an unknown warehouse.');
        }

        return DB::transaction(function () use ($data, $actor): StockAdjustment {
            $totalValue = '0.00';
            $prepared = [];

            foreach ($data->items as $index => $line) {
                $quantity = Decimal::normalize($line->quantityDelta, Decimal::QUANTITY_SCALE);

                if (Decimal::isZero($quantity)) {
                    throw new DomainException('Adjustment quantities cannot be zero.');
                }

                if ($data->adjustmentType === AdjustmentType::Increase && Decimal::isNegative($quantity)) {
                    throw new DomainException('Increase adjustments must use positive quantity deltas.');
                }

                if ($data->adjustmentType === AdjustmentType::Decrease && ! Decimal::isNegative($quantity)) {
                    throw new DomainException('Decrease adjustments must use negative quantity deltas.');
                }

                $item = Item::query()->find($line->itemId);

                if ($item === null) {
                    throw new DomainException('The adjustment line references an unknown item.');
                }

                $unitCost = $this->resolveUnitCost($line, $data->warehouseId);
                $valueDelta = Decimal::multiply($quantity, $unitCost, Decimal::MONEY_SCALE);

                $totalValue = Decimal::add(
                    $totalValue,
                    Decimal::isNegative($valueDelta)
                        ? Decimal::subtract('0', $valueDelta, Decimal::MONEY_SCALE)
                        : $valueDelta,
                    Decimal::MONEY_SCALE,
                );

                $prepared[] = [
                    'line' => $line,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'value_delta' => $valueDelta,
                    'sort_order' => $index,
                ];
            }

            $adjustment = StockAdjustment::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::StockAdjustment),
                'warehouse_id' => $data->warehouseId,
                'stock_count_id' => $data->stockCountId,
                'adjustment_type' => $data->adjustmentType,
                'reason_code' => $data->reasonCode,
                'reason' => $data->reason,
                'status' => AdjustmentStatus::Draft,
                'total_value' => $totalValue,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($prepared as $entry) {
                /** @var StockAdjustmentItemData $line */
                $line = $entry['line'];

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $line->itemId,
                    'unit_of_measure_id' => $line->unitOfMeasureId,
                    'stock_dimension_id' => $line->stockDimensionId,
                    'stock_count_item_id' => $line->stockCountItemId,
                    'batch_id' => $line->batchId,
                    'serial_number_id' => $line->serialNumberId,
                    'quantity_delta' => $entry['quantity'],
                    'unit_cost' => $entry['unit_cost'],
                    'value_delta' => $entry['value_delta'],
                    'reason' => $line->reason,
                    'sort_order' => $entry['sort_order'],
                ]);
            }

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($adjustment)
                ->withProperties(['total_value' => $totalValue, 'reason_code' => $data->reasonCode->value])
                ->log('stock adjustment created');

            $adjustment = $adjustment->refresh()->load('items');

            if ($data->autoSubmit) {
                return $this->submit($adjustment, $actor);
            }

            return $adjustment;
        });
    }

    public function update(StockAdjustment $adjustment, StockAdjustmentData $data, User $actor): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $data, $actor): StockAdjustment {
            $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if (! $adjustment->canBeEdited()) {
                throw new DomainException('Only draft stock adjustments can be edited.');
            }

            if ($adjustment->isCreatedBy($actor) === false && ! $actor->hasGlobalAccess()) {
                throw new DomainException('Only the adjustment creator can edit this document.');
            }

            $adjustment->items()->delete();
            $adjustment->forceFill([
                'adjustment_type' => $data->adjustmentType,
                'reason_code' => $data->reasonCode,
                'reason' => $data->reason,
                'stock_count_id' => $data->stockCountId,
                'updated_by' => $actor->id,
            ])->save();

            $totalValue = '0.00';

            foreach ($data->items as $index => $line) {
                $quantity = Decimal::normalize($line->quantityDelta, Decimal::QUANTITY_SCALE);

                if (Decimal::isZero($quantity)) {
                    throw new DomainException('Adjustment quantities cannot be zero.');
                }

                $unitCost = $this->resolveUnitCost($line, $adjustment->warehouse_id);
                $valueDelta = Decimal::multiply($quantity, $unitCost, Decimal::MONEY_SCALE);
                $totalValue = Decimal::add(
                    $totalValue,
                    Decimal::isNegative($valueDelta)
                        ? Decimal::subtract('0', $valueDelta, Decimal::MONEY_SCALE)
                        : $valueDelta,
                    Decimal::MONEY_SCALE,
                );

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $line->itemId,
                    'unit_of_measure_id' => $line->unitOfMeasureId,
                    'stock_dimension_id' => $line->stockDimensionId,
                    'stock_count_item_id' => $line->stockCountItemId,
                    'batch_id' => $line->batchId,
                    'serial_number_id' => $line->serialNumberId,
                    'quantity_delta' => $quantity,
                    'unit_cost' => $unitCost,
                    'value_delta' => $valueDelta,
                    'reason' => $line->reason,
                    'sort_order' => $index,
                ]);
            }

            $adjustment->forceFill(['total_value' => $totalValue])->save();

            return $adjustment->refresh()->load('items');
        });
    }

    public function submit(StockAdjustment $adjustment, User $actor): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor): StockAdjustment {
            $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if (! $adjustment->canBeSubmitted()) {
                throw new DomainException('Only draft stock adjustments can be submitted.');
            }

            if (! $adjustment->items()->exists()) {
                throw new DomainException('A stock adjustment must contain at least one item.');
            }

            if (! $adjustment->requiresApproval()) {
                $adjustment->forceFill([
                    'status' => AdjustmentStatus::Approved,
                    'approved_at' => now(),
                    'approved_by' => null,
                    'updated_by' => $actor->id,
                ])->save();

                return $adjustment->refresh();
            }

            $adjustment->forceFill([
                'status' => AdjustmentStatus::PendingApproval,
                'updated_by' => $actor->id,
            ])->save();

            $request = $this->approvals->submit($adjustment, $actor);

            $adjustment->forceFill(['approval_request_id' => $request->id])->save();

            return $adjustment->refresh()->load('items');
        });
    }

    public function post(StockAdjustment $adjustment, User $actor): StockAdjustment
    {
        return $this->postDocument($adjustment, $actor, enforceSegregation: true);
    }

    public function reverse(StockAdjustment $adjustment, User $actor, string $reason): StockAdjustment
    {
        if (blank($reason)) {
            throw new DomainException('A reversal reason is required.');
        }

        return DB::transaction(function () use ($adjustment, $actor, $reason): StockAdjustment {
            $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if (! $adjustment->canBeReversed()) {
                throw new DomainException('Only posted stock adjustments can be reversed.');
            }

            $transaction = $adjustment->transaction;

            if ($transaction === null) {
                throw new DomainException('This adjustment has no stock transaction to reverse.');
            }

            if ((int) $adjustment->posted_by === (int) $actor->id
                && ! $actor->can(Permissions::WORKFLOW_OVERRIDE)) {
                throw new DomainException('The original poster cannot reverse their own adjustment.');
            }

            $adjustment->load('items');

            $reversal = StockAdjustment::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::StockAdjustment),
                'warehouse_id' => $adjustment->warehouse_id,
                'stock_count_id' => $adjustment->stock_count_id,
                'adjustment_type' => $adjustment->adjustment_type === AdjustmentType::Increase
                    ? AdjustmentType::Decrease
                    : AdjustmentType::Increase,
                'reason_code' => AdjustmentReason::SystemCorrection,
                'reason' => $reason,
                'status' => AdjustmentStatus::Draft,
                'total_value' => $adjustment->total_value,
                'reversal_of_id' => $adjustment->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($adjustment->items as $index => $item) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $reversal->id,
                    'item_id' => $item->item_id,
                    'unit_of_measure_id' => $item->unit_of_measure_id,
                    'stock_dimension_id' => $item->stock_dimension_id,
                    'stock_count_item_id' => $item->stock_count_item_id,
                    'batch_id' => $item->batch_id,
                    'serial_number_id' => $item->serial_number_id,
                    'quantity_delta' => Decimal::subtract('0', $item->quantity_delta, Decimal::QUANTITY_SCALE),
                    'unit_cost' => $item->unit_cost,
                    'value_delta' => Decimal::subtract('0', $item->value_delta, Decimal::MONEY_SCALE),
                    'reason' => $reason,
                    'sort_order' => $index,
                ]);
            }

            $reversal->forceFill([
                'status' => AdjustmentStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ])->save();

            $reversal = $this->postDocument($reversal->refresh(), $actor, enforceSegregation: false);

            $adjustment->forceFill([
                'status' => AdjustmentStatus::Reversed,
                'updated_by' => $actor->id,
            ])->save();

            StockCountVariance::query()
                ->where('stock_adjustment_id', $adjustment->id)
                ->update([
                    'resolution' => VarianceResolution::Pending->value,
                    'notes' => 'Adjustment reversed: '.$reason,
                ]);

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($adjustment)
                ->withProperties(['reversal_id' => $reversal->id, 'reason' => $reason])
                ->log('stock adjustment reversed');

            return $reversal->refresh()->load('items');
        });
    }

    public function cancel(StockAdjustment $adjustment, User $actor, string $reason): StockAdjustment
    {
        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($adjustment, $actor, $reason): StockAdjustment {
            $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if (! $adjustment->canBeCancelled()) {
                throw new DomainException('This stock adjustment cannot be cancelled.');
            }

            if ($adjustment->approval_request_id !== null) {
                $request = $adjustment->approvalRequest;

                if ($request !== null && $request->isPending()) {
                    $this->approvals->cancel($request, $actor, $reason);
                }
            }

            $adjustment->forceFill([
                'status' => AdjustmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ])->save();

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($adjustment)
                ->withProperties(['reason' => $reason])
                ->log('stock adjustment cancelled');

            return $adjustment->refresh();
        });
    }

    public function delete(StockAdjustment $adjustment): void
    {
        if (! $adjustment->canBeEdited()) {
            throw new DomainException('Only draft stock adjustments can be deleted.');
        }

        $adjustment->delete();
    }

    private function postDocument(StockAdjustment $adjustment, User $actor, bool $enforceSegregation): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $enforceSegregation): StockAdjustment {
            $adjustment = StockAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);

            if (! $adjustment->canBePosted()) {
                throw new DomainException('Only draft or approved stock adjustments can be posted.');
            }

            if ($adjustment->status === AdjustmentStatus::PendingApproval) {
                throw new DomainException('The adjustment is still awaiting approval.');
            }

            if ($adjustment->requiresApproval() && $adjustment->approved_at === null) {
                throw new DomainException('This adjustment requires approval before posting.');
            }

            if ($enforceSegregation
                && $adjustment->isCreatedBy($actor)
                && ! $actor->can(Permissions::WORKFLOW_OVERRIDE)) {
                throw new DomainException('The adjustment creator cannot post their own adjustment.');
            }

            $adjustment->load('items');

            if ($adjustment->items->isEmpty()) {
                throw new DomainException('A stock adjustment must contain at least one item.');
            }

            $lines = $adjustment->items->map(fn (StockAdjustmentItem $item) => new StockPostingLineData(
                itemId: $item->item_id,
                unitOfMeasureId: $item->unit_of_measure_id,
                stockDimensionId: $item->stock_dimension_id,
                quantity: $item->absoluteQuantity(),
                unitCost: (string) $item->unit_cost,
                batchId: $item->batch_id,
                serialNumberId: $item->serial_number_id,
                reference: 'stock_adjustment_item:'.$item->id,
            ))->all();

            $transaction = $this->posting->post(new StockPostingData(
                movementType: $adjustment->adjustment_type->movementType(),
                source: $adjustment,
                operation: 'adjustment',
                warehouseId: $adjustment->warehouse_id,
                postedBy: $actor->id,
                lines: $lines,
                metadata: [
                    'reason_code' => $adjustment->reason_code->value,
                    'stock_count_id' => $adjustment->stock_count_id,
                ],
            ));

            $transaction->loadMissing('items');

            foreach ($adjustment->items as $index => $item) {
                $transactionItem = $transaction->items->get($index);

                if ($transactionItem === null) {
                    continue;
                }

                $item->forceFill([
                    'stock_transaction_item_id' => $transactionItem->id,
                    'unit_cost' => $transactionItem->unit_cost,
                ])->save();
            }

            $adjustment->forceFill([
                'status' => AdjustmentStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor->id,
                'stock_transaction_id' => $transaction->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->markVariancesAdjusted($adjustment);

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($adjustment)
                ->withProperties(['transaction_id' => $transaction->id])
                ->log('stock adjustment posted');

            return $adjustment->refresh()->load('items');
        });
    }

    private function markVariancesAdjusted(StockAdjustment $adjustment): void
    {
        if ($adjustment->stock_count_id === null) {
            return;
        }

        StockCountVariance::query()
            ->where('stock_count_id', $adjustment->stock_count_id)
            ->whereIn(
                'stock_count_item_id',
                $adjustment->items->pluck('stock_count_item_id')->filter()->all(),
            )
            ->update([
                'stock_adjustment_id' => $adjustment->id,
                'resolution' => VarianceResolution::Adjusted->value,
            ]);
    }

    private function resolveUnitCost(StockAdjustmentItemData $line, int $warehouseId): string
    {
        $provided = Decimal::normalize($line->unitCost, Decimal::COST_SCALE);

        if (Decimal::isPositive($provided, Decimal::COST_SCALE)) {
            return $provided;
        }

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('stock_dimension_id', $line->stockDimensionId)
            ->where('item_id', $line->itemId)
            ->where('batch_key', $line->batchId ?? 0)
            ->first();

        if ($balance !== null && Decimal::isPositive($balance->average_unit_cost, Decimal::COST_SCALE)) {
            return Decimal::normalize($balance->average_unit_cost, Decimal::COST_SCALE);
        }

        $item = Item::query()->find($line->itemId);

        return Decimal::normalize($item?->standard_cost ?? '0', Decimal::COST_SCALE);
    }
}
