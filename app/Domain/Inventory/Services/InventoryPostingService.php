<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryCostLayer;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\StockReservationItem;
use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Inventory\Models\StockTransactionItem;
use App\DTOs\Inventory\StockPostingData;
use App\DTOs\Inventory\StockPostingLineData;
use App\Enums\DocumentType;
use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;
use App\Enums\StockTransactionStatus;
use App\Enums\ValuationMethod;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single entry point for every stock movement.
 *
 * Guarantees:
 *  - one posted transaction per (source, operation) via a unique idempotency key;
 *  - balance rows are locked before mutation, preventing lost updates;
 *  - negative available stock is rejected;
 *  - the ledger is append-only and stores the running balance after posting;
 *  - corrections are new reversal transactions, never mutations.
 */
class InventoryPostingService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
    ) {}

    public function post(StockPostingData $data): StockTransaction
    {
        $this->validate($data);

        return DB::transaction(function () use ($data): StockTransaction {
            $existing = StockTransaction::query()
                ->where('idempotency_key', $data->idempotencyKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $postedAt = $data->postedAt !== null ? Carbon::parse($data->postedAt) : now();

            $transaction = StockTransaction::create([
                'transaction_number' => $data->transactionNumber
                    ?? $this->sequences->nextNumber(DocumentType::StockTransaction),
                'transaction_type' => $data->movementType,
                'source_type' => $data->source->getMorphClass(),
                'source_id' => $data->source->getKey(),
                'idempotency_key' => $data->idempotencyKey(),
                'warehouse_id' => $data->warehouseId,
                'destination_warehouse_id' => $data->destinationWarehouseId,
                'status' => StockTransactionStatus::Posted,
                'posted_by' => $data->postedBy,
                'posted_at' => $postedAt,
                'metadata' => [
                    'operation' => $data->operation,
                    'line_count' => count($data->lines),
                    ...$data->metadata,
                ],
            ]);

            foreach ($data->lines as $line) {
                $this->postLine($transaction, $data, $line, $postedAt);
            }

            return $transaction->refresh()->load('items');
        });
    }

    /**
     * Reverse a posted transaction by posting an equal and opposite movement.
     */
    public function reverse(StockTransaction $transaction, User $actor, string $reason): StockTransaction
    {
        if (! $transaction->isPosted()) {
            throw new DomainException('Only posted stock transactions can be reversed.');
        }

        if (blank($reason)) {
            throw new DomainException('A reversal reason is required.');
        }

        if ($transaction->reversed_by_transaction_id !== null) {
            $existing = StockTransaction::query()->find($transaction->reversed_by_transaction_id);

            if ($existing !== null) {
                return $existing;
            }
        }

        $transaction->loadMissing('items');

        if ($transaction->items->isEmpty()) {
            throw new DomainException('This transaction has no lines to reverse.');
        }

        $lines = $transaction->items->map(fn (StockTransactionItem $item) => new StockPostingLineData(
            itemId: $item->item_id,
            unitOfMeasureId: $item->unit_of_measure_id,
            stockDimensionId: $item->stock_dimension_id,
            quantity: (string) $item->quantity,
            unitCost: (string) $item->unit_cost,
            batchId: $item->batch_id,
            serialNumberId: $item->serial_number_id,
            reference: (string) $item->id,
        ))->all();

        $reversal = $this->post(new StockPostingData(
            movementType: $this->reverseType($transaction->transaction_type),
            source: $transaction->source ?? $transaction,
            operation: 'reverse:'.$transaction->id,
            warehouseId: $transaction->warehouse_id,
            postedBy: $actor->id,
            lines: $lines,
            destinationWarehouseId: $transaction->destination_warehouse_id,
            metadata: [
                'reversal_of_transaction_id' => $transaction->id,
                'reversal_reason' => $reason,
            ],
        ));

        $transaction->forceFill([
            'status' => StockTransactionStatus::Reversed,
            'reversed_by_transaction_id' => $reversal->id,
            'reversal_reason' => $reason,
        ])->save();

        return $reversal;
    }

    public function availableQuantity(int $warehouseId, int $stockDimensionId, int $itemId, ?int $batchId = null): string
    {
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('stock_dimension_id', $stockDimensionId)
            ->where('item_id', $itemId)
            ->where('batch_key', $batchId ?? 0)
            ->first();

        if ($balance === null) {
            return Decimal::normalize('0');
        }

        return $balance->availableAfterReservation();
    }

    private function validate(StockPostingData $data): void
    {
        if ($data->lines === []) {
            throw new DomainException('A stock transaction must contain at least one line.');
        }

        if ($data->movementType->isInbound()) {
            foreach ($data->lines as $line) {
                if (! Decimal::isPositive($line->quantity)) {
                    throw new DomainException('Inbound stock quantities must be greater than zero.');
                }
            }

            return;
        }

        foreach ($data->lines as $line) {
            if (! Decimal::isPositive($line->quantity)) {
                throw new DomainException('Outbound stock quantities must be greater than zero.');
            }
        }
    }

    private function postLine(
        StockTransaction $transaction,
        StockPostingData $data,
        StockPostingLineData $line,
        Carbon $postedAt,
    ): void {
        $item = Item::query()->find($line->itemId);

        if ($item === null) {
            throw new DomainException('The stock line references an unknown item.');
        }

        $dimension = StockDimension::query()->find($line->stockDimensionId);

        if ($dimension === null) {
            throw new DomainException('The stock line references an unknown stock dimension.');
        }

        if (! $dimension->is_active) {
            throw new DomainException('The stock dimension is inactive.');
        }

        $quantity = Decimal::normalize($line->quantity, Decimal::QUANTITY_SCALE);

        if (! Decimal::isPositive($quantity)) {
            throw new DomainException('Stock quantities must be greater than zero.');
        }

        $reservationItem = $this->lockReservationItem($line, $data->warehouseId);

        $balance = $this->lockBalance(
            $data->warehouseId,
            $line->stockDimensionId,
            $line->itemId,
            $line->batchId,
        );

        $direction = $data->movementType->direction();

        if ($direction > 0) {
            [$unitCost, $value] = $this->applyInbound(
                $balance,
                $line,
                $item,
                $quantity,
                $postedAt,
            );
        } else {
            [$unitCost, $value] = $this->applyOutbound(
                $balance,
                $line,
                $item,
                $quantity,
                $transaction,
                $reservationItem !== null ? $quantity : '0',
            );
        }

        $transactionItem = StockTransactionItem::create([
            'stock_transaction_id' => $transaction->id,
            'item_id' => $line->itemId,
            'unit_of_measure_id' => $line->unitOfMeasureId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $value,
            'stock_dimension_id' => $line->stockDimensionId,
            'batch_id' => $line->batchId,
            'serial_number_id' => $line->serialNumberId,
            'direction' => $direction,
            'metadata' => $line->reference !== null ? ['reference' => $line->reference] : null,
        ]);

        if ($direction > 0 && $item->valuation_method === ValuationMethod::Fifo) {
            $this->createCostLayer(
                $transactionItem,
                $line,
                $quantity,
                $unitCost,
                $postedAt,
                $balance->warehouse_id,
            );
        }

        StockLedger::create([
            'stock_transaction_id' => $transaction->id,
            'stock_transaction_item_id' => $transactionItem->id,
            'warehouse_id' => $data->warehouseId,
            'stock_dimension_id' => $line->stockDimensionId,
            'item_id' => $line->itemId,
            'batch_id' => $line->batchId,
            'serial_number_id' => $line->serialNumberId,
            'movement_type' => $data->movementType,
            'quantity_in' => $direction > 0 ? $quantity : '0',
            'quantity_out' => $direction > 0 ? '0' : $quantity,
            'unit_cost' => $unitCost,
            'value_in' => $direction > 0 ? $value : '0',
            'value_out' => $direction > 0 ? '0' : $value,
            'balance_quantity_after' => $balance->on_hand_quantity,
            'balance_value_after' => $balance->total_value,
            'posted_at' => $postedAt,
            'posted_by' => $transaction->posted_by,
            'created_at' => now(),
        ]);

        if ($reservationItem !== null) {
            $this->recordReservationConsumption($reservationItem, $quantity);
        }
    }

    /**
     * @return array{0: string, 1: string} unit cost and total value
     */
    private function applyInbound(
        InventoryBalance $balance,
        StockPostingLineData $line,
        Item $item,
        string $quantity,
        Carbon $postedAt,
    ): array {
        $unitCost = $this->resolveInboundUnitCost($line, $item, $balance);
        $value = Decimal::multiply($quantity, $unitCost, Decimal::MONEY_SCALE);

        $newQuantity = Decimal::add($balance->on_hand_quantity, $quantity, Decimal::QUANTITY_SCALE);
        $newValue = Decimal::add($balance->total_value, $value, Decimal::MONEY_SCALE);
        $average = Decimal::isPositive($newQuantity)
            ? Decimal::divide($newValue, $newQuantity, Decimal::COST_SCALE)
            : '0';

        $balance->forceFill([
            'on_hand_quantity' => $newQuantity,
            'available_quantity' => Decimal::subtract($newQuantity, $balance->reserved_quantity, Decimal::QUANTITY_SCALE),
            'average_unit_cost' => $average,
            'total_value' => $newValue,
            'last_movement_at' => $postedAt,
            'version' => $balance->version + 1,
        ])->save();

        return [$unitCost, $value];
    }

    private function createCostLayer(
        StockTransactionItem $transactionItem,
        StockPostingLineData $line,
        string $quantity,
        string $unitCost,
        Carbon $postedAt,
        int $warehouseId,
    ): void {
        $sequence = (int) InventoryCostLayer::query()
            ->where('item_id', $line->itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('stock_dimension_id', $line->stockDimensionId)
            ->where('batch_id', $line->batchId)
            ->max('sequence');

        InventoryCostLayer::create([
            'item_id' => $line->itemId,
            'warehouse_id' => $warehouseId,
            'stock_dimension_id' => $line->stockDimensionId,
            'batch_id' => $line->batchId,
            'stock_transaction_item_id' => $transactionItem->id,
            'original_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'received_at' => $postedAt,
            'sequence' => $sequence + 1,
        ]);
    }

    /**
     * @return array{0: string, 1: string} unit cost and total value
     */
    private function applyOutbound(
        InventoryBalance $balance,
        StockPostingLineData $line,
        Item $item,
        string $quantity,
        StockTransaction $transaction,
        string $reservedConsumption = '0',
    ): array {
        $available = Decimal::add(
            $balance->availableAfterReservation(),
            $reservedConsumption,
            Decimal::QUANTITY_SCALE,
        );

        if (Decimal::compare($available, $quantity) < 0) {
            throw new DomainException(sprintf(
                'Insufficient available stock for item %d: requested %s, available %s.',
                $line->itemId,
                $quantity,
                $available,
            ));
        }

        if ($item->valuation_method === ValuationMethod::Fifo) {
            [$unitCost, $value] = $this->consumeFifoLayers($balance, $line, $quantity);
        } else {
            $unitCost = Decimal::isPositive($line->unitCost)
                ? Decimal::normalize($line->unitCost, Decimal::COST_SCALE)
                : Decimal::normalize($balance->average_unit_cost, Decimal::COST_SCALE);

            $value = Decimal::multiply($quantity, $unitCost, Decimal::MONEY_SCALE);
        }

        $newQuantity = Decimal::subtract($balance->on_hand_quantity, $quantity, Decimal::QUANTITY_SCALE);
        $newValue = Decimal::subtract($balance->total_value, $value, Decimal::MONEY_SCALE);
        $newReserved = Decimal::subtract($balance->reserved_quantity, $reservedConsumption, Decimal::QUANTITY_SCALE);

        if (Decimal::isNegative($newValue)) {
            $newValue = '0.00';
        }

        $average = Decimal::isPositive($newQuantity)
            ? Decimal::divide($newValue, $newQuantity, Decimal::COST_SCALE)
            : '0';

        $balance->forceFill([
            'on_hand_quantity' => $newQuantity,
            'reserved_quantity' => $newReserved,
            'available_quantity' => Decimal::subtract($newQuantity, $newReserved, Decimal::QUANTITY_SCALE),
            'average_unit_cost' => $average,
            'total_value' => $newValue,
            'last_movement_at' => now(),
            'version' => $balance->version + 1,
        ])->save();

        return [$unitCost, $value];
    }

    private function resolveInboundUnitCost(
        StockPostingLineData $line,
        Item $item,
        InventoryBalance $balance,
    ): string {
        if (Decimal::isPositive($line->unitCost)) {
            return Decimal::normalize($line->unitCost, Decimal::COST_SCALE);
        }

        if (Decimal::isPositive((string) $item->standard_cost)) {
            return Decimal::normalize($item->standard_cost, Decimal::COST_SCALE);
        }

        return Decimal::normalize($balance->average_unit_cost, Decimal::COST_SCALE);
    }

    /**
     * Consume FIFO layers and return the blended unit cost and money value.
     *
     * @return array{0: string, 1: string}
     */
    private function consumeFifoLayers(
        InventoryBalance $balance,
        StockPostingLineData $line,
        string $quantity,
    ): array {
        $remaining = $quantity;
        $value = '0';

        $layers = InventoryCostLayer::query()
            ->where('item_id', $line->itemId)
            ->where('warehouse_id', $balance->warehouse_id)
            ->where('stock_dimension_id', $line->stockDimensionId)
            ->where('batch_id', $line->batchId)
            ->remaining()
            ->fifo()
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if (! Decimal::isPositive($remaining)) {
                break;
            }

            $take = Decimal::compare($remaining, $layer->remaining_quantity) <= 0
                ? $remaining
                : Decimal::normalize($layer->remaining_quantity, Decimal::QUANTITY_SCALE);

            $value = Decimal::add(
                $value,
                Decimal::multiply($take, $layer->unit_cost, Decimal::MONEY_SCALE),
                Decimal::MONEY_SCALE,
            );

            $layer->forceFill([
                'remaining_quantity' => Decimal::subtract(
                    $layer->remaining_quantity,
                    $take,
                    Decimal::QUANTITY_SCALE,
                ),
            ])->save();

            $remaining = Decimal::subtract($remaining, $take, Decimal::QUANTITY_SCALE);
        }

        if (Decimal::isPositive($remaining)) {
            // Cost layers are normally complete for FIFO items. Fall back to the
            // balance average so an inconsistent layer set cannot break posting.
            $fallbackCost = Decimal::isPositive($line->unitCost)
                ? $line->unitCost
                : $balance->average_unit_cost;

            $value = Decimal::add(
                $value,
                Decimal::multiply($remaining, $fallbackCost, Decimal::MONEY_SCALE),
                Decimal::MONEY_SCALE,
            );
        }

        $unitCost = Decimal::divide($value, $quantity, Decimal::COST_SCALE);

        return [$unitCost, $value];
    }

    private function lockReservationItem(StockPostingLineData $line, int $warehouseId): ?StockReservationItem
    {
        if ($line->reservationItemId === null) {
            return null;
        }

        $item = StockReservationItem::query()->find($line->reservationItemId);

        if ($item === null) {
            throw new DomainException('The posting line references an unknown stock reservation line.');
        }

        $reservation = StockReservation::query()->lockForUpdate()->find($item->stock_reservation_id);

        if ($reservation === null) {
            throw new DomainException('The posting line references an unknown stock reservation.');
        }

        if (! $reservation->status->isOpen()) {
            throw new DomainException('The stock reservation is no longer open.');
        }

        if ($reservation->warehouse_id !== $warehouseId) {
            throw new DomainException('The stock reservation belongs to a different warehouse.');
        }

        $item = StockReservationItem::query()->lockForUpdate()->findOrFail($item->id);

        if ($item->item_id !== $line->itemId
            || $item->stock_dimension_id !== $line->stockDimensionId
            || $item->batch_id !== $line->batchId) {
            throw new DomainException('The posting line does not match the reserved stock line.');
        }

        $quantity = Decimal::normalize($line->quantity, Decimal::QUANTITY_SCALE);

        if (Decimal::compare($item->outstandingQuantity(), $quantity) < 0) {
            throw new DomainException(sprintf(
                'The reservation line only has %s outstanding; %s was requested.',
                $item->outstandingQuantity(),
                $quantity,
            ));
        }

        return $item;
    }

    private function recordReservationConsumption(StockReservationItem $item, string $quantity): void
    {
        $item->forceFill([
            'consumed_quantity' => Decimal::add(
                (string) $item->consumed_quantity,
                $quantity,
                Decimal::QUANTITY_SCALE,
            ),
        ])->save();

        $reservation = StockReservation::query()
            ->lockForUpdate()
            ->findOrFail($item->stock_reservation_id);

        $items = StockReservationItem::query()
            ->where('stock_reservation_id', $reservation->id)
            ->get();

        $outstanding = $items->reduce(
            fn (string $carry, StockReservationItem $line): string => Decimal::add(
                $carry,
                $line->outstandingQuantity(),
                Decimal::QUANTITY_SCALE,
            ),
            Decimal::normalize('0'),
        );

        $consumed = $items->reduce(
            fn (string $carry, StockReservationItem $line): string => Decimal::add(
                $carry,
                (string) $line->consumed_quantity,
                Decimal::QUANTITY_SCALE,
            ),
            Decimal::normalize('0'),
        );

        if (! Decimal::isPositive($outstanding)) {
            $reservation->forceFill([
                'status' => Decimal::isPositive($consumed)
                    ? StockReservationStatus::Consumed
                    : StockReservationStatus::Released,
                'closed_at' => now(),
            ])->save();

            return;
        }

        $reservation->forceFill(['status' => StockReservationStatus::PartiallyConsumed])->save();
    }

    private function lockBalance(
        int $warehouseId,
        int $stockDimensionId,
        int $itemId,
        ?int $batchId,
    ): InventoryBalance {
        $batchKey = $batchId ?? 0;

        $attributes = [
            'warehouse_id' => $warehouseId,
            'stock_dimension_id' => $stockDimensionId,
            'item_id' => $itemId,
            'batch_key' => $batchKey,
        ];

        $balance = InventoryBalance::query()->where($attributes)->lockForUpdate()->first();

        if ($balance !== null) {
            return $balance;
        }

        try {
            $balance = InventoryBalance::create([
                ...$attributes,
                'batch_id' => $batchId,
                'on_hand_quantity' => '0',
                'reserved_quantity' => '0',
                'available_quantity' => '0',
                'average_unit_cost' => '0',
                'total_value' => '0',
                'version' => 0,
            ]);
        } catch (QueryException $exception) {
            // A concurrent transaction inserted the same balance row first.
            $balance = InventoryBalance::query()->where($attributes)->lockForUpdate()->first();

            if ($balance === null) {
                throw $exception;
            }

            return $balance;
        }

        return InventoryBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
    }

    private function reverseType(StockMovementType $type): StockMovementType
    {
        return match ($type) {
            StockMovementType::Receipt,
            StockMovementType::TransferIn,
            StockMovementType::AdjustmentIn,
            StockMovementType::ReturnIn,
            StockMovementType::OpeningBalance,
            StockMovementType::ReversalIn => StockMovementType::ReversalOut,
            StockMovementType::Issue,
            StockMovementType::TransferOut,
            StockMovementType::AdjustmentOut,
            StockMovementType::ReturnOut,
            StockMovementType::ReversalOut => StockMovementType::ReversalIn,
        };
    }
}
