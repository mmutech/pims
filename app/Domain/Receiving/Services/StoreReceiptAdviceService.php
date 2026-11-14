<?php

namespace App\Domain\Receiving\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Models\StoreReceiptAdviceItem;
use App\DTOs\Receiving\StoreReceiptAdviceData;
use App\DTOs\Receiving\StoreReceiptAdviceItemData;
use App\Enums\DocumentType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceiptStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class StoreReceiptAdviceService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
    ) {}

    public function create(StoreReceiptAdviceData $data, User $actor): StoreReceiptAdvice
    {
        $order = $this->receivableOrder($data->purchaseOrderId);

        $this->validateItems($order, $data->items);

        return DB::transaction(function () use ($data, $actor, $order): StoreReceiptAdvice {
            $sra = StoreReceiptAdvice::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::StoreReceiptAdvice),
                'purchase_order_id' => $order->id,
                'warehouse_id' => $order->delivery_warehouse_id,
                'supplier_id' => $order->supplier_id,
                'expected_delivery_date' => $data->expectedDeliveryDate,
                'vehicle_number' => $data->vehicleNumber,
                'driver_name' => $data->driverName,
                'driver_phone' => $data->driverPhone,
                'delivery_note_number' => $data->deliveryNoteNumber,
                'status' => ReceiptStatus::Draft,
                'received_by' => $actor->id,
            ]);

            $this->syncItems($sra, $order, $data->items);

            return $sra->refresh()->load('items.item', 'items.unitOfMeasure');
        });
    }

    public function update(StoreReceiptAdvice $sra, StoreReceiptAdviceData $data, User $actor): StoreReceiptAdvice
    {
        if (! $sra->canBeEdited()) {
            throw new DomainException('Only draft receipt advices can be edited.');
        }

        $order = $this->receivableOrder($data->purchaseOrderId);
        $this->validateItems($order, $data->items);

        return DB::transaction(function () use ($sra, $data, $order): StoreReceiptAdvice {
            $sra->update([
                'purchase_order_id' => $order->id,
                'warehouse_id' => $order->delivery_warehouse_id,
                'supplier_id' => $order->supplier_id,
                'expected_delivery_date' => $data->expectedDeliveryDate,
                'vehicle_number' => $data->vehicleNumber,
                'driver_name' => $data->driverName,
                'driver_phone' => $data->driverPhone,
                'delivery_note_number' => $data->deliveryNoteNumber,
            ]);

            $sra->items()->delete();
            $this->syncItems($sra, $order, $data->items);

            return $sra->refresh()->load('items.item', 'items.unitOfMeasure');
        });
    }

    public function submit(StoreReceiptAdvice $sra, User $actor): StoreReceiptAdvice
    {
        if (! $sra->canBeSubmitted()) {
            throw new DomainException('This receipt advice cannot be submitted.');
        }

        $sra->loadMissing('items');

        if ($sra->items->isEmpty()) {
            throw new DomainException('A receipt advice must contain at least one line.');
        }

        $delivered = $sra->items->some(
            fn (StoreReceiptAdviceItem $item) => Decimal::isPositive($item->delivered_quantity),
        );

        if (! $delivered) {
            throw new DomainException('At least one line must record a delivered quantity.');
        }

        $sra->forceFill([
            'status' => ReceiptStatus::PendingVerification,
        ])->save();

        return $sra->refresh();
    }

    public function cancel(StoreReceiptAdvice $sra, User $actor, string $reason): StoreReceiptAdvice
    {
        if (! $sra->canBeCancelled()) {
            throw new DomainException('This receipt advice cannot be cancelled.');
        }

        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        $sra->forceFill([
            'status' => ReceiptStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        return $sra->refresh();
    }

    public function delete(StoreReceiptAdvice $sra): void
    {
        if (! $sra->canBeEdited()) {
            throw new DomainException('Only draft receipt advices can be deleted.');
        }

        $sra->delete();
    }

    private function receivableOrder(int $purchaseOrderId): PurchaseOrder
    {
        $order = PurchaseOrder::query()->with('items')->find($purchaseOrderId);

        if ($order === null) {
            throw new DomainException('The purchase order could not be found.');
        }

        if (! in_array($order->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true)) {
            throw new DomainException('Only approved or partially received purchase orders can accept deliveries.');
        }

        return $order;
    }

    /**
     * @param  list<StoreReceiptAdviceItemData>  $items
     */
    private function validateItems(PurchaseOrder $order, array $items): void
    {
        if ($items === []) {
            throw new DomainException('A receipt advice must contain at least one line.');
        }

        $seen = [];

        foreach ($items as $item) {
            $orderItem = $order->items->firstWhere('id', $item->purchaseOrderItemId);

            if (! $orderItem instanceof PurchaseOrderItem) {
                throw new DomainException('A receipt line references an item that is not on this purchase order.');
            }

            if (isset($seen[$orderItem->id])) {
                throw new DomainException('Each purchase order line may only appear once on a receipt advice.');
            }

            $seen[$orderItem->id] = true;

            $expected = Decimal::normalize($item->expectedQuantity);
            $delivered = Decimal::normalize($item->deliveredQuantity);

            if (! Decimal::isPositive($expected)) {
                throw new DomainException('Expected quantities must be greater than zero.');
            }

            if (Decimal::compare($expected, $orderItem->remainingQuantity()) > 0) {
                throw new DomainException(sprintf(
                    'Expected quantity for %s exceeds the outstanding order quantity.',
                    $orderItem->item?->name ?? 'the line',
                ));
            }

            if (Decimal::isNegative($delivered) || Decimal::compare($delivered, $expected) > 0) {
                throw new DomainException('Delivered quantity must be between zero and the expected quantity.');
            }
        }
    }

    /**
     * @param  list<StoreReceiptAdviceItemData>  $items
     */
    private function syncItems(StoreReceiptAdvice $sra, PurchaseOrder $order, array $items): void
    {
        $sort = 0;

        foreach ($items as $item) {
            /** @var PurchaseOrderItem $orderItem */
            $orderItem = $order->items->firstWhere('id', $item->purchaseOrderItemId);

            StoreReceiptAdviceItem::create([
                'sra_id' => $sra->id,
                'purchase_order_item_id' => $orderItem->id,
                'item_id' => $orderItem->item_id,
                'unit_of_measure_id' => $orderItem->unit_of_measure_id,
                'expected_quantity' => Decimal::normalize($item->expectedQuantity),
                'delivered_quantity' => Decimal::normalize($item->deliveredQuantity),
                'batch_number' => $item->batchNumber,
                'serial_numbers' => $item->serialNumbers !== [] ? $item->serialNumbers : null,
                'condition_notes' => $item->conditionNotes,
                'sort_order' => $sort++,
            ]);
        }
    }
}
