<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\StockReservationItem;
use App\DTOs\Inventory\StockReservationData;
use App\Enums\DocumentType;
use App\Enums\StockDimensionType;
use App\Enums\StockReservationStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reserves available warehouse stock for a source document without moving it.
 *
 * Invariants:
 *  - a reservation only targets active warehouse dimensions;
 *  - reserved quantities never exceed on-hand stock;
 *  - available = on-hand - reserved is recalculated on every change;
 *  - consumption happens through InventoryPostingService so the ledger remains
 *    the single source of truth for physical movements.
 */
class StockReservationService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
    ) {}

    public function reserve(StockReservationData $data): StockReservation
    {
        $this->validate($data);

        return DB::transaction(function () use ($data): StockReservation {
            $existing = StockReservation::query()
                ->where('source_type', $data->source->getMorphClass())
                ->where('source_id', $data->source->getKey())
                ->open()
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new DomainException(
                    'An open stock reservation already exists for this document. Release it before reserving again.'
                );
            }

            $reservation = StockReservation::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::StockReservation),
                'source_type' => $data->source->getMorphClass(),
                'source_id' => $data->source->getKey(),
                'warehouse_id' => $data->warehouseId,
                'status' => StockReservationStatus::Active,
                'expires_at' => $data->expiresAt !== null ? Carbon::parse($data->expiresAt) : null,
                'created_by' => $data->createdBy,
            ]);

            foreach ($data->lines as $line) {
                $balance = $this->lockBalance(
                    $data->warehouseId,
                    $line->stockDimensionId,
                    $line->itemId,
                    $line->batchId,
                );

                $quantity = Decimal::normalize($line->quantity, Decimal::QUANTITY_SCALE);
                $available = $balance->availableAfterReservation();

                if (Decimal::compare($available, $quantity) < 0) {
                    throw new DomainException(sprintf(
                        'Insufficient available stock for item %d: requested %s, available %s.',
                        $line->itemId,
                        $quantity,
                        $available,
                    ));
                }

                StockReservationItem::create([
                    'stock_reservation_id' => $reservation->id,
                    'item_id' => $line->itemId,
                    'stock_dimension_id' => $line->stockDimensionId,
                    'batch_id' => $line->batchId,
                    'quantity' => $quantity,
                ]);

                $this->applyReservedDelta($balance, $quantity);
            }

            return $reservation->refresh()->load('items');
        });
    }

    /**
     * Release outstanding quantities. An empty map releases every open line.
     *
     * @param  array<int, string>  $quantities  reservation item id => quantity
     */
    public function release(StockReservation $reservation, string $reason, array $quantities = [], ?User $actor = null): StockReservation
    {
        if (blank($reason)) {
            throw new DomainException('A reservation release reason is required.');
        }

        return $this->close($reservation, $reason, $quantities, StockReservationStatus::Released, $actor);
    }

    /**
     * Release every expired open reservation.
     *
     * @return int number of reservations expired
     */
    public function expire(?Carbon $at = null): int
    {
        $at ??= now();
        $expired = 0;

        StockReservation::query()
            ->open()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $at)
            ->orderBy('id')
            ->chunkById(100, function ($reservations) use (&$expired): void {
                foreach ($reservations as $reservation) {
                    $this->close(
                        $reservation,
                        'Reservation expired.',
                        [],
                        StockReservationStatus::Expired,
                        null,
                    );

                    $expired++;
                }
            });

        return $expired;
    }

    /**
     * @param  array<int, string>  $quantities
     */
    private function close(
        StockReservation $reservation,
        string $reason,
        array $quantities,
        StockReservationStatus $terminalStatus,
        ?User $actor,
    ): StockReservation {
        return DB::transaction(function () use ($reservation, $reason, $quantities, $terminalStatus, $actor): StockReservation {
            $reservation = StockReservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if (! $reservation->status->isOpen()) {
                throw new DomainException('Only open stock reservations can be released or expired.');
            }

            $items = StockReservationItem::query()
                ->where('stock_reservation_id', $reservation->id)
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $outstanding = $item->outstandingQuantity();

                if (! Decimal::isPositive($outstanding)) {
                    continue;
                }

                $quantity = $quantities === []
                    ? $outstanding
                    : Decimal::normalize((string) ($quantities[$item->id] ?? '0'), Decimal::QUANTITY_SCALE);

                if (! Decimal::isPositive($quantity)) {
                    continue;
                }

                if (Decimal::compare($quantity, $outstanding) > 0) {
                    throw new DomainException(sprintf(
                        'Cannot release %s for reservation line %d; only %s is outstanding.',
                        $quantity,
                        $item->id,
                        $outstanding,
                    ));
                }

                $balance = $this->lockBalance(
                    $reservation->warehouse_id,
                    $item->stock_dimension_id,
                    $item->item_id,
                    $item->batch_id,
                );

                $this->applyReservedDelta($balance, Decimal::multiply($quantity, '-1', Decimal::QUANTITY_SCALE));

                $item->forceFill([
                    'released_quantity' => Decimal::add(
                        (string) $item->released_quantity,
                        $quantity,
                        Decimal::QUANTITY_SCALE,
                    ),
                ])->save();
            }

            $items = StockReservationItem::query()
                ->where('stock_reservation_id', $reservation->id)
                ->get();

            $outstanding = $items->reduce(
                fn (string $carry, StockReservationItem $item): string => Decimal::add(
                    $carry,
                    $item->outstandingQuantity(),
                    Decimal::QUANTITY_SCALE,
                ),
                Decimal::normalize('0'),
            );

            $consumed = $items->reduce(
                fn (string $carry, StockReservationItem $item): string => Decimal::add(
                    $carry,
                    (string) $item->consumed_quantity,
                    Decimal::QUANTITY_SCALE,
                ),
                Decimal::normalize('0'),
            );

            if (! Decimal::isPositive($outstanding)) {
                $reservation->forceFill([
                    'status' => $terminalStatus === StockReservationStatus::Expired
                        ? StockReservationStatus::Expired
                        : (Decimal::isPositive($consumed) ? StockReservationStatus::Consumed : StockReservationStatus::Released),
                    'release_reason' => $reason,
                    'closed_at' => now(),
                ])->save();
            } elseif (Decimal::isPositive($consumed)) {
                $reservation->forceFill(['status' => StockReservationStatus::PartiallyConsumed])->save();
            }

            if ($actor !== null) {
                activity('inventory')
                    ->causedBy($actor)
                    ->performedOn($reservation)
                    ->withProperties([
                        'reason' => $reason,
                        'status' => $reservation->status->value,
                    ])
                    ->log('stock reservation updated');
            }

            return $reservation->refresh()->load('items');
        });
    }

    private function applyReservedDelta(InventoryBalance $balance, string $delta): void
    {
        $reserved = Decimal::add((string) $balance->reserved_quantity, $delta, Decimal::QUANTITY_SCALE);

        if (Decimal::isNegative($reserved)) {
            throw new DomainException('Reserved quantity cannot become negative.');
        }

        if (Decimal::compare($reserved, (string) $balance->on_hand_quantity) > 0) {
            throw new DomainException('Reserved quantity cannot exceed on-hand stock.');
        }

        $balance->forceFill([
            'reserved_quantity' => $reserved,
            'available_quantity' => Decimal::subtract(
                (string) $balance->on_hand_quantity,
                $reserved,
                Decimal::QUANTITY_SCALE,
            ),
            'version' => $balance->version + 1,
        ])->save();
    }

    private function lockBalance(
        int $warehouseId,
        int $stockDimensionId,
        int $itemId,
        ?int $batchId,
    ): InventoryBalance {
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('stock_dimension_id', $stockDimensionId)
            ->where('item_id', $itemId)
            ->where('batch_key', $batchId ?? 0)
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            throw new DomainException('There is no stock balance for the requested warehouse stock line.');
        }

        return $balance;
    }

    private function validate(StockReservationData $data): void
    {
        if ($data->lines === []) {
            throw new DomainException('A stock reservation must contain at least one line.');
        }

        foreach ($data->lines as $line) {
            if (! Decimal::isPositive($line->quantity)) {
                throw new DomainException('Reserved quantities must be greater than zero.');
            }

            $dimension = StockDimension::query()->find($line->stockDimensionId);

            if ($dimension === null || ! $dimension->is_active) {
                throw new DomainException('The reservation references an unknown or inactive stock dimension.');
            }

            if ($dimension->warehouse_id !== $data->warehouseId) {
                throw new DomainException('The stock dimension does not belong to the reservation warehouse.');
            }

            if ($dimension->type !== StockDimensionType::Warehouse) {
                throw new DomainException('Only warehouse stock can be reserved.');
            }

            if (Item::query()->whereKey($line->itemId)->doesntExist()) {
                throw new DomainException('The reservation references an unknown item.');
            }
        }
    }
}
