<?php

namespace App\Domain\Stocktaking\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Stocktaking\Models\StockAdjustment;
use App\Domain\Stocktaking\Models\StockCount;
use App\Domain\Stocktaking\Models\StockCountItem;
use App\Domain\Stocktaking\Models\StockCountRecount;
use App\Domain\Stocktaking\Models\StockCountScope;
use App\Domain\Stocktaking\Models\StockCountVariance;
use App\DTOs\Stocktaking\StockAdjustmentData;
use App\DTOs\Stocktaking\StockAdjustmentItemData;
use App\DTOs\Stocktaking\StockCountData;
use App\DTOs\Stocktaking\StockCountEntryData;
use App\DTOs\Stocktaking\StockCountScopeData;
use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\CountStatus;
use App\Enums\DocumentType;
use App\Enums\VarianceResolution;
use App\Enums\VarianceType;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Permissions;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stocktaking: snapshot, physical counting, recounts, variance analysis and
 * count-to-adjustment hand-off.
 *
 * Counters can record quantities but can never post adjustments. The service
 * enforces that separation in addition to the authorization policies.
 */
class StockCountService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly StockAdjustmentService $adjustments,
    ) {}

    public function create(StockCountData $data, User $actor): StockCount
    {
        if (blank($data->scopeDescription)) {
            throw new DomainException('A stock count scope description is required.');
        }

        return DB::transaction(function () use ($data, $actor): StockCount {
            $count = StockCount::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::StockCount),
                'warehouse_id' => $data->warehouseId,
                'count_type' => $data->countType,
                'scope_description' => $data->scopeDescription,
                'status' => CountStatus::Draft,
                'tolerance_quantity' => Decimal::normalize($data->toleranceQuantity),
                'tolerance_value' => Decimal::normalize($data->toleranceValue, Decimal::MONEY_SCALE),
                'created_by' => $actor->id,
            ]);

            $scopes = $data->scopes !== []
                ? $data->scopes
                : [new StockCountScopeData];

            foreach ($scopes as $scope) {
                StockCountScope::create([
                    'stock_count_id' => $count->id,
                    'warehouse_location_id' => $scope->warehouseLocationId,
                    'item_category_id' => $scope->itemCategoryId,
                    'item_id' => $scope->itemId,
                    'include_zero_balances' => $scope->includeZeroBalances,
                ]);
            }

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($count)
                ->withProperties(['count_type' => $data->countType->value])
                ->log('stock count created');

            return $count->refresh()->load('scopes');
        });
    }

    public function update(StockCount $count, StockCountData $data, User $actor): StockCount
    {
        if (blank($data->scopeDescription)) {
            throw new DomainException('A stock count scope description is required.');
        }

        return DB::transaction(function () use ($count, $data, $actor): StockCount {
            $count = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $count->status->canBeStarted()) {
                throw new DomainException('Only draft stock counts can be edited.');
            }

            $count->fill([
                'warehouse_id' => $data->warehouseId,
                'count_type' => $data->countType,
                'scope_description' => $data->scopeDescription,
                'tolerance_quantity' => Decimal::normalize($data->toleranceQuantity),
                'tolerance_value' => Decimal::normalize($data->toleranceValue, Decimal::MONEY_SCALE),
            ])->save();

            $count->scopes()->delete();

            $scopes = $data->scopes !== []
                ? $data->scopes
                : [new StockCountScopeData];

            foreach ($scopes as $scope) {
                StockCountScope::create([
                    'stock_count_id' => $count->id,
                    'warehouse_location_id' => $scope->warehouseLocationId,
                    'item_category_id' => $scope->itemCategoryId,
                    'item_id' => $scope->itemId,
                    'include_zero_balances' => $scope->includeZeroBalances,
                ]);
            }

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($count)
                ->withProperties(['count_type' => $data->countType->value])
                ->log('stock count updated');

            return $count->refresh()->load('scopes');
        });
    }

    /**
     * Freeze the current on-hand quantities into count lines.
     */
    public function start(StockCount $count, User $actor): StockCount
    {
        return DB::transaction(function () use ($count, $actor): StockCount {
            $count = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $count->canBeStarted()) {
                throw new DomainException('Only draft stock counts can be started.');
            }

            $scopes = $count->scopes()->get();
            $includeZero = $scopes->contains(fn (StockCountScope $scope): bool => $scope->include_zero_balances);

            $balances = $this->scopeQuery($count, $scopes)
                ->when(! $includeZero, fn (Builder $query) => $query->where(function (Builder $query): void {
                    $query->where('on_hand_quantity', '>', 0)
                        ->orWhere('reserved_quantity', '>', 0);
                }))
                ->get();

            if ($balances->isEmpty()) {
                throw new DomainException('No inventory balances match the count scope.');
            }

            foreach ($balances as $balance) {
                StockCountItem::create([
                    'stock_count_id' => $count->id,
                    'item_id' => $balance->item_id,
                    'stock_dimension_id' => $balance->stock_dimension_id,
                    'batch_id' => $balance->batch_id,
                    'system_quantity' => $balance->on_hand_quantity,
                    'system_unit_cost' => $balance->average_unit_cost,
                    'system_value' => $balance->total_value,
                    'is_recount' => false,
                ]);
            }

            $count->forceFill([
                'status' => CountStatus::Counting,
                'snapshot_at' => now(),
                'snapshot_ledger_id' => (int) DB::table('stock_ledger')->max('id'),
                'started_at' => now(),
            ])->save();

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($count)
                ->withProperties(['items' => $balances->count()])
                ->log('stock count started and snapshot captured');

            return $count->refresh()->load('items');
        });
    }

    /**
     * @param  list<StockCountEntryData>  $entries
     */
    public function recordCount(StockCount $count, array $entries, User $actor): StockCount
    {
        if ($entries === []) {
            throw new DomainException('At least one counted quantity is required.');
        }

        return DB::transaction(function () use ($count, $entries, $actor): StockCount {
            $count = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $count->canBeCounted()) {
                throw new DomainException('This stock count is not open for counting.');
            }

            foreach ($entries as $entry) {
                $item = StockCountItem::query()
                    ->where('stock_count_id', $count->id)
                    ->lockForUpdate()
                    ->find($entry->stockCountItemId);

                if ($item === null) {
                    throw new DomainException('The count line does not belong to this stock count.');
                }

                $quantity = Decimal::normalize($entry->countedQuantity);

                if (Decimal::isNegative($quantity)) {
                    throw new DomainException('Counted quantities cannot be negative.');
                }

                $expected = $this->expectedQuantity($count, $item);

                $item->forceFill([
                    'counted_quantity' => $quantity,
                    'variance_quantity' => Decimal::subtract($quantity, $expected, Decimal::QUANTITY_SCALE),
                    'counted_by' => $actor->id,
                    'counted_at' => now(),
                    'notes' => $entry->notes,
                ])->save();
            }

            $count->forceFill(['status' => CountStatus::VarianceReview])->save();

            $this->analyseVariances($count->refresh(), $actor);

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($count)
                ->withProperties(['lines' => count($entries)])
                ->log('stock count quantities recorded');

            return $count->refresh()->load('items');
        });
    }

    public function recount(
        StockCount $count,
        int $stockCountItemId,
        string $countedQuantity,
        User $actor,
        ?string $notes = null,
    ): StockCountItem {
        return DB::transaction(function () use ($count, $stockCountItemId, $countedQuantity, $actor, $notes): StockCountItem {
            $count = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $count->canBeCounted()) {
                throw new DomainException('This stock count is not open for recounts.');
            }

            $item = StockCountItem::query()
                ->where('stock_count_id', $count->id)
                ->lockForUpdate()
                ->findOrFail($stockCountItemId);

            $quantity = Decimal::normalize($countedQuantity);

            if (Decimal::isNegative($quantity)) {
                throw new DomainException('Counted quantities cannot be negative.');
            }

            $nextNumber = (int) StockCountRecount::query()
                ->where('stock_count_item_id', $item->id)
                ->max('recount_number') + 1;

            StockCountRecount::create([
                'stock_count_item_id' => $item->id,
                'recount_number' => $nextNumber,
                'counted_quantity' => $quantity,
                'counted_by' => $actor->id,
                'counted_at' => now(),
                'notes' => $notes,
            ]);

            $expected = $this->expectedQuantity($count, $item);

            $item->forceFill([
                'counted_quantity' => $quantity,
                'variance_quantity' => Decimal::subtract($quantity, $expected, Decimal::QUANTITY_SCALE),
                'counted_by' => $actor->id,
                'counted_at' => now(),
                'is_recount' => true,
                'notes' => $notes ?? $item->notes,
            ])->save();

            $count->forceFill(['status' => CountStatus::Recount])->save();

            $this->analyseVariances($count->refresh(), $actor);

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($count)
                ->withProperties(['stock_count_item_id' => $item->id, 'recount_number' => $nextNumber])
                ->log('stock count recount recorded');

            return $item->refresh()->load('recounts');
        });
    }

    /**
     * @return array{variance_count: int, breached_count: int, quantity: string, value: string}
     */
    public function analyseVariances(StockCount $count, User $actor): array
    {
        $created = 0;
        $breached = 0;
        $quantity = '0';
        $value = '0.00';

        $items = $count->items()->whereNotNull('counted_quantity')->get();

        foreach ($items as $item) {
            $expected = $this->expectedQuantity($count, $item);
            $delta = Decimal::subtract($item->counted_quantity, $expected, Decimal::QUANTITY_SCALE);

            $item->forceFill(['variance_quantity' => $delta])->save();

            if (Decimal::isZero($delta)) {
                $existing = StockCountVariance::query()->where('stock_count_item_id', $item->id)->first();

                if ($existing !== null
                    && ($existing->adjustment === null
                        || $existing->adjustment->status !== AdjustmentStatus::Posted)) {
                    $existing->delete();
                }

                continue;
            }

            $unitCost = $this->unitCost($item);
            $absolute = Decimal::isNegative($delta)
                ? Decimal::subtract('0', $delta, Decimal::QUANTITY_SCALE)
                : $delta;
            $varianceValue = Decimal::multiply($absolute, $unitCost, Decimal::MONEY_SCALE);
            $toleranceBreached = $this->exceedsTolerance($count, $absolute, $varianceValue);

            $variance = StockCountVariance::query()->updateOrCreate(
                ['stock_count_item_id' => $item->id],
                [
                    'stock_count_id' => $count->id,
                    'variance_type' => VarianceType::fromDelta($delta),
                    'quantity' => $delta,
                    'value' => $varianceValue,
                    'tolerance_breached' => $toleranceBreached,
                ],
            );

            if ($variance->resolution === null) {
                $variance->resolution = VarianceResolution::Pending;
                $variance->save();
            }

            $created++;
            $breached += $toleranceBreached ? 1 : 0;
            $quantity = Decimal::add($quantity, $delta, Decimal::QUANTITY_SCALE);
            $value = Decimal::add($value, $varianceValue, Decimal::MONEY_SCALE);
        }

        if ($count->status !== CountStatus::Recount) {
            $count->forceFill(['status' => CountStatus::VarianceReview])->save();
        }

        activity('stocktaking')
            ->causedBy($actor)
            ->performedOn($count)
            ->withProperties(['variances' => $created, 'breached' => $breached])
            ->log('stock count variances analysed');

        return [
            'variance_count' => $created,
            'breached_count' => $breached,
            'quantity' => $quantity,
            'value' => $value,
        ];
    }

    /**
     * Create one adjustment per open variance. Only users who did not schedule
     * or count the stock may perform this step.
     *
     * @return Collection<int, StockAdjustment>
     */
    public function createAdjustments(StockCount $count, User $actor): Collection
    {
        return DB::transaction(function () use ($count, $actor): Collection {
            $count = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $count->canBeAnalysed()) {
                throw new DomainException('Variances can only be adjusted while the count is open.');
            }

            $override = $actor->can(Permissions::WORKFLOW_OVERRIDE);

            if (! $override && (int) $count->created_by === (int) $actor->id) {
                throw new DomainException('The user who scheduled a stock count cannot adjust its variances.');
            }

            if (! $override && $count->items()->where('counted_by', $actor->id)->exists()) {
                throw new DomainException('A counter cannot adjust variances for items they counted.');
            }

            $variances = $count->variances()
                ->where('resolution', VarianceResolution::Pending->value)
                ->whereNull('stock_adjustment_id')
                ->with('stockCountItem.item')
                ->get();

            if ($variances->isEmpty()) {
                throw new DomainException('There are no open variances to adjust.');
            }

            $adjustments = collect();

            foreach ($variances as $variance) {
                $item = $variance->stockCountItem;

                if ($item === null) {
                    continue;
                }

                $type = Decimal::isNegative($variance->quantity)
                    ? AdjustmentType::Decrease
                    : AdjustmentType::Increase;

                $adjustment = $this->adjustments->create(new StockAdjustmentData(
                    warehouseId: $count->warehouse_id,
                    adjustmentType: $type,
                    reasonCode: AdjustmentReason::CountCorrection,
                    reason: 'Stock count '.$count->document_number.' variance correction',
                    items: [
                        new StockAdjustmentItemData(
                            itemId: $item->item_id,
                            unitOfMeasureId: $item->item->base_unit_of_measure_id,
                            stockDimensionId: $item->stock_dimension_id,
                            quantityDelta: (string) $variance->quantity,
                            unitCost: $this->unitCost($item),
                            batchId: $item->batch_id,
                            serialNumberId: $item->serial_number_id,
                            stockCountItemId: $item->id,
                            reason: $variance->notes,
                        ),
                    ],
                    stockCountId: $count->id,
                    autoSubmit: true,
                ), $actor);

                $variance->forceFill([
                    'stock_adjustment_id' => $adjustment->id,
                    'resolution' => VarianceResolution::Pending,
                ])->save();

                $adjustments->push($adjustment);
            }

            $count->forceFill(['status' => CountStatus::AdjustmentPending])->save();

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($count)
                ->withProperties(['adjustments' => $adjustments->count()])
                ->log('stock count adjustments created');

            return $adjustments;
        });
    }

    public function complete(StockCount $count, User $actor): StockCount
    {
        return DB::transaction(function () use ($count, $actor): StockCount {
            $count = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $count->canBeCompleted()) {
                throw new DomainException('Only counts pending adjustments can be completed.');
            }

            $openAdjustments = $count->adjustments()
                ->whereIn('status', [
                    AdjustmentStatus::Draft->value,
                    AdjustmentStatus::PendingApproval->value,
                    AdjustmentStatus::Approved->value,
                ])
                ->exists();

            if ($openAdjustments) {
                throw new DomainException('All count adjustments must be posted before completion.');
            }

            $reconciliationPosted = $count->reconciliations()
                ->where('status', 'posted')
                ->exists();

            if (! $reconciliationPosted) {
                throw new DomainException('The count reconciliation must be posted before completion.');
            }

            $count->forceFill([
                'status' => CountStatus::Completed,
                'completed_at' => now(),
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($count)
                ->log('stock count completed');

            return $count->refresh();
        });
    }

    public function cancel(StockCount $count, User $actor, string $reason): StockCount
    {
        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($count, $actor, $reason): StockCount {
            $count = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $count->canBeCancelled()) {
                throw new DomainException('This stock count cannot be cancelled.');
            }

            if ($count->adjustments()->where('status', AdjustmentStatus::Posted->value)->exists()) {
                throw new DomainException('A count with posted adjustments cannot be cancelled. Reverse them first.');
            }

            $count->forceFill([
                'status' => CountStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($count)
                ->withProperties(['reason' => $reason])
                ->log('stock count cancelled');

            return $count->refresh();
        });
    }

    public function delete(StockCount $count): void
    {
        if (! $count->status->canBeStarted()) {
            throw new DomainException('Only draft stock counts can be deleted.');
        }

        $count->delete();
    }

    /**
     * Expected quantity = snapshot quantity plus net ledger movement after the
     * snapshot, excluding adjustments created by this count.
     */
    public function expectedQuantity(StockCount $count, StockCountItem $item): string
    {
        if ($count->snapshot_at === null) {
            return Decimal::normalize($item->system_quantity);
        }

        $adjustmentTransactions = $count->adjustments()
            ->whereNotNull('stock_transaction_id')
            ->pluck('stock_transaction_id')
            ->all();

        $query = DB::table('stock_ledger')
            ->where('warehouse_id', $count->warehouse_id)
            ->where('item_id', $item->item_id)
            ->where('stock_dimension_id', $item->stock_dimension_id);

        if ($count->snapshot_ledger_id !== null) {
            $query->where('id', '>', $count->snapshot_ledger_id);
        } else {
            $query->where('posted_at', '>=', $count->snapshot_at);
        }

        $this->whereNullable($query, 'batch_id', $item->batch_id);
        $this->whereNullable($query, 'serial_number_id', $item->serial_number_id);

        if ($adjustmentTransactions !== []) {
            $query->whereNotIn('stock_transaction_id', $adjustmentTransactions);
        }

        $totals = $query->selectRaw('COALESCE(SUM(quantity_in), 0) as quantity_in, COALESCE(SUM(quantity_out), 0) as quantity_out')
            ->first();

        $net = Decimal::subtract(
            $totals?->quantity_in ?? '0',
            $totals?->quantity_out ?? '0',
            Decimal::QUANTITY_SCALE,
        );

        return Decimal::add($item->system_quantity, $net, Decimal::QUANTITY_SCALE);
    }

    public function currentOnHand(StockCount $count, StockCountItem $item): string
    {
        $quantity = DB::table('inventory_balances')
            ->where('warehouse_id', $count->warehouse_id)
            ->where('item_id', $item->item_id)
            ->where('stock_dimension_id', $item->stock_dimension_id)
            ->where('batch_key', $item->batch_id ?? 0)
            ->value('on_hand_quantity');

        return Decimal::normalize($quantity ?? '0');
    }

    /**
     * @param  Collection<int, StockCountScope>  $scopes
     */
    private function scopeQuery(StockCount $count, Collection $scopes): Builder
    {
        $query = InventoryBalance::query()->where('warehouse_id', $count->warehouse_id);

        if ($scopes->isEmpty()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($scopes): void {
            foreach ($scopes as $scope) {
                $query->orWhere(function (Builder $query) use ($scope): void {
                    if ($scope->warehouse_location_id !== null) {
                        $query->whereHas(
                            'dimension',
                            fn (Builder $dimension) => $dimension->where('warehouse_location_id', $scope->warehouse_location_id),
                        );
                    }

                    if ($scope->item_category_id !== null) {
                        $query->whereHas(
                            'item',
                            fn (Builder $item) => $item->where('category_id', $scope->item_category_id),
                        );
                    }

                    if ($scope->item_id !== null) {
                        $query->where('item_id', $scope->item_id);
                    }
                });
            }
        });
    }

    private function unitCost(StockCountItem $item): string
    {
        if (Decimal::isPositive($item->system_unit_cost, Decimal::COST_SCALE)) {
            return Decimal::normalize($item->system_unit_cost, Decimal::COST_SCALE);
        }

        return Decimal::normalize($item->item?->standard_cost ?? '0', Decimal::COST_SCALE);
    }

    private function exceedsTolerance(StockCount $count, string $quantity, string $value): bool
    {
        $quantityTolerance = Decimal::normalize($count->tolerance_quantity);
        $valueTolerance = Decimal::normalize($count->tolerance_value, Decimal::MONEY_SCALE);

        if (Decimal::isPositive($quantityTolerance) && Decimal::compare($quantity, $quantityTolerance) > 0) {
            return true;
        }

        if (Decimal::isPositive($valueTolerance, Decimal::MONEY_SCALE) && Decimal::compare($value, $valueTolerance, Decimal::MONEY_SCALE) > 0) {
            return true;
        }

        return ! Decimal::isPositive($quantityTolerance) && ! Decimal::isPositive($valueTolerance, Decimal::MONEY_SCALE);
    }

    private function whereNullable($query, string $column, ?int $value): void
    {
        if ($value === null) {
            $query->whereNull($column);

            return;
        }

        $query->where($column, $value);
    }
}
