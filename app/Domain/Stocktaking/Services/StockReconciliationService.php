<?php

namespace App\Domain\Stocktaking\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Stocktaking\Models\StockCount;
use App\Domain\Stocktaking\Models\StockReconciliation;
use App\Enums\AdjustmentStatus;
use App\Enums\DocumentType;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Permissions;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Proves that a stocktake reconciles:
 *
 *     snapshot + movements after snapshot + count adjustments = current ledger
 *
 * The reconciliation is a separate, approvable document so a count cannot be
 * completed without an auditable balancing statement.
 */
class StockReconciliationService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly StockCountService $counts,
    ) {}

    /**
     * @return array{
     *     snapshot_quantity: string,
     *     movement_quantity: string,
     *     adjustment_quantity: string,
     *     final_quantity: string,
     *     system_quantity: string,
     *     difference_quantity: string
     * }
     */
    public function reconcile(StockCount $count): array
    {
        $count->loadMissing('items');

        $snapshot = '0';
        $expected = '0';
        $final = '0';

        foreach ($count->items as $item) {
            $snapshot = Decimal::add($snapshot, $item->system_quantity, Decimal::QUANTITY_SCALE);
            $expected = Decimal::add($expected, $this->counts->expectedQuantity($count, $item), Decimal::QUANTITY_SCALE);
            $final = Decimal::add($final, $this->counts->currentOnHand($count, $item), Decimal::QUANTITY_SCALE);
        }

        $movement = Decimal::subtract($expected, $snapshot, Decimal::QUANTITY_SCALE);
        $adjustment = $this->postedAdjustmentQuantity($count);
        $system = $final;
        $difference = Decimal::subtract(
            Decimal::add(Decimal::add($snapshot, $movement), $adjustment),
            $system,
            Decimal::QUANTITY_SCALE,
        );

        return [
            'snapshot_quantity' => $snapshot,
            'movement_quantity' => $movement,
            'adjustment_quantity' => $adjustment,
            'final_quantity' => $final,
            'system_quantity' => $system,
            'difference_quantity' => $difference,
        ];
    }

    public function prepare(StockCount $count, User $actor): StockReconciliation
    {
        return DB::transaction(function () use ($count, $actor): StockReconciliation {
            $count = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if ($count->snapshot_at === null) {
                throw new DomainException('Start the stock count before preparing a reconciliation.');
            }

            $values = $this->reconcile($count);

            $reconciliation = StockReconciliation::query()
                ->where('stock_count_id', $count->id)
                ->lockForUpdate()
                ->first();

            if ($reconciliation !== null && $reconciliation->status->value !== 'draft') {
                throw new DomainException('An approved or posted reconciliation cannot be recalculated.');
            }

            if ($reconciliation === null) {
                $reconciliation = new StockReconciliation([
                    'document_number' => $this->sequences->nextNumber(DocumentType::StockReconciliation),
                    'stock_count_id' => $count->id,
                    'warehouse_id' => $count->warehouse_id,
                    'prepared_by' => $actor->id,
                ]);
            }

            $reconciliation->fill([
                ...$values,
                'status' => 'draft',
                'prepared_by' => $actor->id,
            ])->save();

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($reconciliation)
                ->withProperties($values)
                ->log('stock count reconciliation prepared');

            return $reconciliation->refresh();
        });
    }

    public function approve(StockReconciliation $reconciliation, User $actor): StockReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor): StockReconciliation {
            $reconciliation = StockReconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);

            if (! $reconciliation->canBeApproved()) {
                throw new DomainException('Only draft reconciliations can be approved.');
            }

            if ((int) $reconciliation->prepared_by === (int) $actor->id
                && ! $actor->can(Permissions::WORKFLOW_OVERRIDE)) {
                throw new DomainException('The preparer cannot approve their own reconciliation.');
            }

            $reconciliation->forceFill([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($reconciliation)
                ->log('stock count reconciliation approved');

            return $reconciliation->refresh();
        });
    }

    public function post(StockReconciliation $reconciliation, User $actor): StockReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor): StockReconciliation {
            $reconciliation = StockReconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);

            if (! $reconciliation->canBePosted()) {
                throw new DomainException('Only approved reconciliations can be posted.');
            }

            $count = StockCount::query()->findOrFail($reconciliation->stock_count_id);
            $values = $this->reconcile($count);

            if (! Decimal::isZero($values['difference_quantity'])) {
                throw new DomainException(sprintf(
                    'The stock count does not reconcile; the difference is %s.',
                    $values['difference_quantity'],
                ));
            }

            $reconciliation->fill([
                ...$values,
                'status' => 'posted',
                'posted_at' => now(),
            ])->save();

            activity('stocktaking')
                ->causedBy($actor)
                ->performedOn($reconciliation)
                ->withProperties($values)
                ->log('stock count reconciliation posted');

            return $reconciliation->refresh();
        });
    }

    public function latest(StockCount $count): ?StockReconciliation
    {
        return StockReconciliation::query()
            ->where('stock_count_id', $count->id)
            ->latest('id')
            ->first();
    }

    private function postedAdjustmentQuantity(StockCount $count): string
    {
        $quantity = DB::table('stock_adjustment_items')
            ->join('stock_adjustments', 'stock_adjustments.id', '=', 'stock_adjustment_items.stock_adjustment_id')
            ->where('stock_adjustments.stock_count_id', $count->id)
            ->where('stock_adjustments.status', AdjustmentStatus::Posted->value)
            ->sum('stock_adjustment_items.quantity_delta');

        return Decimal::normalize($quantity ?? '0');
    }
}
