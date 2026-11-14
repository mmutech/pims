<?php

namespace App\Domain\Receiving\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBatch;
use App\Domain\Catalog\Models\ItemSerialNumber;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use App\Domain\Receiving\Models\DeliveryVerificationItem;
use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Domain\Receiving\Models\GoodsReceivedNoteItem;
use App\Domain\Receiving\Models\QualityInspectionItem;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Models\StoreReceiptAdviceItem;
use App\DTOs\Inventory\StockPostingData;
use App\DTOs\Inventory\StockPostingLineData;
use App\Enums\BatchStatus;
use App\Enums\DocumentType;
use App\Enums\GoodsReceivedNoteStatus;
use App\Enums\ItemTrackingType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceiptStatus;
use App\Enums\SerialStatus;
use App\Enums\StockMovementType;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class GoodsReceivedNoteService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly InventoryPostingService $posting,
    ) {}

    /**
     * Build a draft GRN from verified and inspected quantities.
     */
    public function createFromSra(StoreReceiptAdvice $sra, User $actor): GoodsReceivedNote
    {
        $existing = $sra->goodsReceivedNotes()
            ->where('status', GoodsReceivedNoteStatus::Draft->value)
            ->with('items')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $sra->canBeReceived()) {
            throw new DomainException('Delivery must be verified before goods can be received.');
        }

        $sra->loadMissing('items.item', 'verification.items', 'inspections.items');

        $verification = $sra->verification;

        if ($verification === null) {
            throw new DomainException('A delivery verification is required before goods can be received.');
        }

        $inspection = $sra->completedInspection();

        if ($sra->requiresQualityInspection() && $inspection === null) {
            throw new DomainException('Quality inspection must be completed before goods can be received.');
        }

        $verificationItems = $verification->items->keyBy('sra_item_id');
        $inspectionItems = $inspection?->items->keyBy('sra_item_id') ?? collect();

        $lines = [];
        $sort = 0;

        foreach ($sra->items as $sraItem) {
            /** @var DeliveryVerificationItem|null $verified */
            $verified = $verificationItems->get($sraItem->id);

            $verifiedQuantity = $verified?->verified_quantity ?? $sraItem->delivered_quantity;
            $damagedQuantity = $verified?->damaged_quantity ?? '0';

            /** @var QualityInspectionItem|null $inspectionItem */
            $inspectionItem = $inspectionItems->get($sraItem->id);

            if ($sraItem->item?->requires_quality_inspection) {
                if ($inspectionItem === null) {
                    if (Decimal::isPositive($verifiedQuantity)) {
                        throw new DomainException('Every inspected item must have a completed inspection result.');
                    }

                    continue;
                }

                $accepted = Decimal::normalize($inspectionItem->accepted_quantity);
                $rejected = Decimal::normalize($inspectionItem->rejected_quantity);
            } else {
                $accepted = Decimal::subtract($verifiedQuantity, $damagedQuantity, Decimal::QUANTITY_SCALE);
                $rejected = Decimal::normalize($damagedQuantity);

                if (Decimal::isNegative($accepted)) {
                    $accepted = Decimal::normalize('0');
                }
            }

            if (! Decimal::isPositive($accepted) && ! Decimal::isPositive($rejected)) {
                continue;
            }

            $batchId = $this->resolveBatch($sraItem, $sra->supplier_id);
            $this->assertSerialCoverage($sraItem, $accepted);

            $lines[] = [
                'purchase_order_item_id' => $sraItem->purchase_order_item_id,
                'item_id' => $sraItem->item_id,
                'unit_of_measure_id' => $sraItem->unit_of_measure_id,
                'accepted_quantity' => $accepted,
                'rejected_quantity' => $rejected,
                'unit_cost' => Decimal::normalize($this->unitCost($sraItem), Decimal::COST_SCALE),
                'batch_id' => $batchId,
                'sort_order' => $sort++,
            ];
        }

        if ($lines === []) {
            throw new DomainException('There are no accepted or rejected quantities to receive.');
        }

        return DB::transaction(function () use ($sra, $actor, $inspection, $lines): GoodsReceivedNote {
            $grn = GoodsReceivedNote::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::GoodsReceivedNote),
                'purchase_order_id' => $sra->purchase_order_id,
                'sra_id' => $sra->id,
                'quality_inspection_id' => $inspection?->id,
                'warehouse_id' => $sra->warehouse_id,
                'supplier_id' => $sra->supplier_id,
                'received_date' => now()->toDateString(),
                'status' => GoodsReceivedNoteStatus::Draft,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                GoodsReceivedNoteItem::create(['grn_id' => $grn->id, ...$line]);
            }

            return $grn->refresh()->load('items.item', 'items.unitOfMeasure');
        });
    }

    /**
     * Post accepted quantities to stock. Rejected quantities never enter stock.
     */
    public function post(GoodsReceivedNote $grn, User $actor): GoodsReceivedNote
    {
        if ($grn->isPosted()) {
            return $grn;
        }

        if (! $grn->canBePosted()) {
            throw new DomainException('Only draft goods received notes can be posted.');
        }

        $grn->loadMissing(
            'items.item',
            'items.purchaseOrderItem',
            'sra.items.item',
            'purchaseOrder.items',
            'qualityInspection.items',
        );

        $sra = $grn->sra;
        $order = $grn->purchaseOrder;

        if ($sra === null || $order === null) {
            throw new DomainException('The goods received note is missing its source documents.');
        }

        if ($this->inspectionRequired($sra) && $grn->qualityInspection === null) {
            throw new DomainException('A completed quality inspection is required before posting.');
        }

        if ($grn->qualityInspection !== null && ! $grn->qualityInspection->isComplete()) {
            throw new DomainException('The linked quality inspection is not complete.');
        }

        $alreadyPosted = GoodsReceivedNote::query()
            ->where('sra_id', $sra->id)
            ->where('status', GoodsReceivedNoteStatus::Posted->value)
            ->whereKeyNot($grn->id)
            ->exists();

        if ($alreadyPosted) {
            throw new DomainException('This receipt advice has already been posted.');
        }

        if (! $grn->items->contains(fn (GoodsReceivedNoteItem $item) => Decimal::isPositive($item->accepted_quantity))) {
            throw new DomainException('A goods received note must accept at least one unit before posting.');
        }

        foreach ($grn->items as $item) {
            $sraItem = $sra->items->firstWhere('purchase_order_item_id', $item->purchase_order_item_id);

            if ($sraItem === null) {
                throw new DomainException('A goods received note line has no matching receipt advice line.');
            }

            $this->assertSerialCoverage($sraItem, $item->accepted_quantity);
        }

        return DB::transaction(function () use ($grn, $actor, $sra, $order): GoodsReceivedNote {
            $dimension = StockDimension::resolve($grn->warehouse_id);

            $lines = $grn->items
                ->filter(fn (GoodsReceivedNoteItem $item) => Decimal::isPositive($item->accepted_quantity))
                ->map(fn (GoodsReceivedNoteItem $item) => new StockPostingLineData(
                    itemId: $item->item_id,
                    unitOfMeasureId: $item->unit_of_measure_id,
                    stockDimensionId: $dimension->id,
                    quantity: (string) $item->accepted_quantity,
                    unitCost: (string) $item->unit_cost,
                    batchId: $item->batch_id,
                    reference: (string) $item->id,
                ))
                ->values()
                ->all();

            $transaction = $this->posting->post(new StockPostingData(
                movementType: StockMovementType::Receipt,
                source: $grn,
                operation: 'grn_post',
                warehouseId: $grn->warehouse_id,
                postedBy: $actor->id,
                lines: $lines,
                metadata: [
                    'grn_id' => $grn->id,
                    'sra_id' => $sra->id,
                    'purchase_order_id' => $order->id,
                ],
            ));

            $this->linkTransactionItems($grn, $transaction);
            $this->applyToPurchaseOrder($grn, $order);
            $this->activateBatchesAndSerials($grn, $sra);

            $grn->forceFill([
                'status' => GoodsReceivedNoteStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor->id,
                'stock_transaction_id' => $transaction->id,
            ])->save();

            $sra->forceFill(['status' => ReceiptStatus::Posted])->save();

            return $grn->refresh()->load('items.item', 'stockTransaction');
        });
    }

    public function cancel(GoodsReceivedNote $grn, User $actor): GoodsReceivedNote
    {
        if (! $grn->canBeCancelled()) {
            throw new DomainException('Only draft goods received notes can be cancelled.');
        }

        $grn->forceFill(['status' => GoodsReceivedNoteStatus::Cancelled])->save();

        return $grn->refresh();
    }

    public function reverse(GoodsReceivedNote $grn, User $actor, string $reason): GoodsReceivedNote
    {
        if ($grn->status === GoodsReceivedNoteStatus::Reversed) {
            return $grn;
        }

        if (! $grn->canBeReversed() || $grn->stockTransaction === null) {
            throw new DomainException('Only posted goods received notes with a stock transaction can be reversed.');
        }

        $grn->loadMissing('items', 'sra.items');

        return DB::transaction(function () use ($grn, $actor, $reason): GoodsReceivedNote {
            $this->posting->reverse($grn->stockTransaction, $actor, $reason);

            foreach ($grn->items as $item) {
                /** @var PurchaseOrderItem|null $orderItem */
                $orderItem = PurchaseOrderItem::query()->lockForUpdate()->find($item->purchase_order_item_id);

                if ($orderItem === null) {
                    continue;
                }

                $received = Decimal::subtract($orderItem->received_quantity, $item->accepted_quantity);
                $rejected = Decimal::subtract($orderItem->rejected_quantity, $item->rejected_quantity);

                $orderItem->forceFill([
                    'received_quantity' => Decimal::isNegative($received) ? '0' : $received,
                    'rejected_quantity' => Decimal::isNegative($rejected) ? '0' : $rejected,
                ])->save();
            }

            $this->refreshPurchaseOrderStatus($grn->purchaseOrder);

            $grn->forceFill(['status' => GoodsReceivedNoteStatus::Reversed])->save();

            $grn->sra->forceFill([
                'status' => $this->inspectionRequired($grn->sra)
                    ? ReceiptStatus::Inspected
                    : ReceiptStatus::Verified,
            ])->save();

            $this->releaseSerials($grn);

            return $grn->refresh();
        });
    }

    private function inspectionRequired(StoreReceiptAdvice $sra): bool
    {
        return $sra->items()->with('item')->get()->contains(
            fn (StoreReceiptAdviceItem $item) => $item->item?->requires_quality_inspection
                && Decimal::isPositive($item->delivered_quantity),
        );
    }

    private function resolveBatch(StoreReceiptAdviceItem $sraItem, int $supplierId): ?int
    {
        /** @var Item|null $item */
        $item = $sraItem->item;

        if ($item === null || ! $item->tracking_type->requiresBatch()) {
            return null;
        }

        if (blank($sraItem->batch_number)) {
            throw new DomainException(sprintf('%s is batch tracked and requires a batch number.', $item->name));
        }

        $batch = ItemBatch::query()->firstOrCreate(
            ['item_id' => $item->id, 'batch_number' => $sraItem->batch_number],
            ['supplier_id' => $supplierId, 'status' => BatchStatus::Active],
        );

        return $batch->id;
    }

    private function unitCost(StoreReceiptAdviceItem $sraItem): string
    {
        $orderItem = $sraItem->purchaseOrderItem;

        if ($orderItem !== null && Decimal::isPositive((string) $orderItem->unit_price)) {
            return (string) $orderItem->unit_price;
        }

        return (string) ($sraItem->item?->standard_cost ?? '0');
    }

    private function assertSerialCoverage(StoreReceiptAdviceItem $sraItem, string $accepted): void
    {
        if ($sraItem->item?->tracking_type !== ItemTrackingType::Serial
            && $sraItem->item?->tracking_type !== ItemTrackingType::BatchSerial) {
            return;
        }

        if (! Decimal::isPositive($accepted)) {
            return;
        }

        $serials = $sraItem->serial_numbers ?? [];

        if (count($serials) < (int) $accepted) {
            throw new DomainException(sprintf(
                '%s is serial tracked: %d serial number(s) supplied for %s accepted unit(s).',
                $sraItem->item?->name ?? 'Item',
                count($serials),
                $accepted,
            ));
        }
    }

    private function linkTransactionItems(GoodsReceivedNote $grn, StockTransaction $transaction): void
    {
        $byReference = $transaction->items->keyBy(
            fn ($item) => (string) ($item->metadata['reference'] ?? ''),
        );

        foreach ($grn->items as $item) {
            $transactionItem = $byReference->get((string) $item->id);

            if ($transactionItem === null) {
                continue;
            }

            $item->forceFill(['stock_transaction_item_id' => $transactionItem->id])->save();
        }
    }

    private function applyToPurchaseOrder(GoodsReceivedNote $grn, PurchaseOrder $order): void
    {
        foreach ($grn->items as $item) {
            /** @var PurchaseOrderItem|null $orderItem */
            $orderItem = $order->items->firstWhere('id', $item->purchase_order_item_id);

            if ($orderItem === null) {
                $orderItem = PurchaseOrderItem::query()->lockForUpdate()->find($item->purchase_order_item_id);
            }

            if ($orderItem === null) {
                throw new DomainException('A goods received note line references an unknown purchase order line.');
            }

            $orderItem->forceFill([
                'received_quantity' => Decimal::add(
                    $orderItem->received_quantity,
                    $item->accepted_quantity,
                    Decimal::QUANTITY_SCALE,
                ),
                'rejected_quantity' => Decimal::add(
                    $orderItem->rejected_quantity,
                    $item->rejected_quantity,
                    Decimal::QUANTITY_SCALE,
                ),
            ])->save();
        }

        $this->refreshPurchaseOrderStatus($order);
    }

    private function refreshPurchaseOrderStatus(?PurchaseOrder $order): void
    {
        if ($order === null) {
            return;
        }

        if (! in_array($order->status, [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::FullyReceived,
        ], true)) {
            return;
        }

        $items = $order->items()->get();

        $fullyReceived = $items->isNotEmpty() && $items->every(
            fn (PurchaseOrderItem $item) => Decimal::compare(
                $item->received_quantity,
                $item->ordered_quantity,
            ) >= 0,
        );

        $anyReceived = $items->contains(
            fn (PurchaseOrderItem $item) => Decimal::isPositive($item->received_quantity),
        );

        $order->forceFill([
            'status' => $fullyReceived
                ? PurchaseOrderStatus::FullyReceived
                : ($anyReceived ? PurchaseOrderStatus::PartiallyReceived : PurchaseOrderStatus::Approved),
        ])->save();
    }

    private function activateBatchesAndSerials(GoodsReceivedNote $grn, StoreReceiptAdvice $sra): void
    {
        foreach ($grn->items as $item) {
            if (! Decimal::isPositive($item->accepted_quantity)) {
                continue;
            }

            if ($item->batch_id !== null) {
                ItemBatch::query()
                    ->whereKey($item->batch_id)
                    ->update(['status' => BatchStatus::Active->value]);
            }

            $sraItem = $sra->items->firstWhere('purchase_order_item_id', $item->purchase_order_item_id);

            if ($sraItem === null || $sraItem->item?->tracking_type->requiresSerial() !== true) {
                continue;
            }

            $serials = array_slice($sraItem->serial_numbers ?? [], 0, (int) $item->accepted_quantity);

            foreach ($serials as $serial) {
                ItemSerialNumber::query()->updateOrCreate(
                    ['item_id' => $item->item_id, 'serial_number' => $serial],
                    [
                        'batch_id' => $item->batch_id,
                        'status' => SerialStatus::Available,
                        'current_warehouse_id' => $grn->warehouse_id,
                    ],
                );
            }
        }
    }

    private function releaseSerials(GoodsReceivedNote $grn): void
    {
        $grn->loadMissing('items', 'sra.items');

        foreach ($grn->items as $item) {
            $sraItem = $grn->sra->items->firstWhere('purchase_order_item_id', $item->purchase_order_item_id);

            if ($sraItem === null || $sraItem->item?->tracking_type->requiresSerial() !== true) {
                continue;
            }

            ItemSerialNumber::query()
                ->where('item_id', $item->item_id)
                ->whereIn('serial_number', $sraItem->serial_numbers ?? [])
                ->update([
                    'status' => SerialStatus::Returned->value,
                    'current_warehouse_id' => null,
                ]);
        }
    }
}
