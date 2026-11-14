<?php

namespace App\Domain\Distribution\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Catalog\Models\Item;
use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Distribution\Models\StockTransferDiscrepancy;
use App\Domain\Distribution\Models\StockTransferDispatch;
use App\Domain\Distribution\Models\StockTransferDispatchItem;
use App\Domain\Distribution\Models\StockTransferItem;
use App\Domain\Distribution\Models\StockTransferReceipt;
use App\Domain\Distribution\Models\StockTransferReceiptItem;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\DTOs\Distribution\StockTransferData;
use App\DTOs\Distribution\StockTransferDispatchData;
use App\DTOs\Distribution\StockTransferReceiptData;
use App\DTOs\Inventory\StockPostingData;
use App\DTOs\Inventory\StockPostingLineData;
use App\Enums\DocumentType;
use App\Enums\StockDimensionType;
use App\Enums\StockMovementType;
use App\Enums\TransferDiscrepancyResolution;
use App\Enums\TransferDiscrepancyType;
use App\Enums\TransferStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Inter-warehouse transfers with explicit in-transit ownership, partial
 * dispatches/receipts and discrepancy reconciliation.
 */
class StockTransferService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly ApprovalEngine $approvals,
        private readonly InventoryPostingService $posting,
    ) {}

    public function create(StockTransferData $data, User $actor): StockTransfer
    {
        $this->validateTransfer($data);

        return DB::transaction(function () use ($data, $actor): StockTransfer {
            $transfer = StockTransfer::create([
                ...$data->toArray(),
                'document_number' => $this->sequences->nextNumber(DocumentType::StockTransfer),
                'status' => TransferStatus::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncItems($transfer, $data);

            return $transfer->refresh()->load('items');
        });
    }

    public function update(StockTransfer $transfer, StockTransferData $data, User $actor): StockTransfer
    {
        if ($transfer->status !== TransferStatus::Draft) {
            throw new DomainException('Only draft transfers can be edited.');
        }

        $this->validateTransfer($data);

        return DB::transaction(function () use ($transfer, $data, $actor): StockTransfer {
            $transfer->update([
                ...$data->toArray(),
                'updated_by' => $actor->id,
            ]);

            $transfer->items()->delete();
            $this->syncItems($transfer, $data);

            return $transfer->refresh()->load('items');
        });
    }

    public function submit(StockTransfer $transfer, User $actor): StockTransfer
    {
        if (! $transfer->canBeSubmitted()) {
            throw new DomainException('This transfer cannot be submitted.');
        }

        if (! $transfer->items()->exists()) {
            throw new DomainException('A stock transfer must contain at least one item.');
        }

        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            foreach ($transfer->items()->get() as $item) {
                $item->forceFill(['approved_quantity' => $item->requested_quantity])->save();
            }

            $transfer->forceFill([
                'status' => TransferStatus::PendingApproval,
                'updated_by' => $actor->id,
            ])->save();

            $this->approvals->submit($transfer, $actor);

            return $transfer->refresh()->load('items');
        });
    }

    public function dispatch(StockTransferDispatchData $data, User $actor): StockTransferDispatch
    {
        return DB::transaction(function () use ($data, $actor): StockTransferDispatch {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($data->stockTransferId);

            if (! $transfer->canBeDispatched()) {
                throw new DomainException('This transfer is not ready for dispatch.');
            }

            if ($data->lines === []) {
                throw new DomainException('A dispatch must contain at least one line.');
            }

            $dispatch = StockTransferDispatch::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::StockTransferDispatch),
                'stock_transfer_id' => $transfer->id,
                'source_warehouse_id' => $transfer->source_warehouse_id,
                'dispatched_by' => $data->dispatchedBy,
                'dispatched_at' => $data->dispatchedAt !== null ? Carbon::parse($data->dispatchedAt) : now(),
                'vehicle_number' => $data->vehicleNumber,
                'driver_name' => $data->driverName,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $actor->id,
            ]);

            $outboundLines = [];
            $lineContext = [];
            $dispatched = [];

            foreach ($data->lines as $line) {
                $item = $transfer->items()->lockForUpdate()->find($line->stockTransferItemId);

                if ($item === null) {
                    throw new DomainException('The dispatch line references an unknown transfer line.');
                }

                $quantity = Decimal::normalize($line->quantity, Decimal::QUANTITY_SCALE);
                $remaining = Decimal::subtract(
                    $item->approved_quantity,
                    $item->dispatched_quantity,
                    Decimal::QUANTITY_SCALE,
                );

                if (! Decimal::isPositive($quantity)) {
                    throw new DomainException('Dispatched quantities must be greater than zero.');
                }

                if (Decimal::compare($quantity, $remaining) > 0) {
                    throw new DomainException(sprintf(
                        'Only %s remains approved for dispatch on this line.',
                        $remaining,
                    ));
                }

                $catalogItem = Item::query()->findOrFail($item->item_id);

                if ($catalogItem->tracking_type->requiresBatch() && $line->batchId === null) {
                    throw new DomainException('A batch must be selected for batch-tracked items.');
                }

                $balance = $this->sourceBalance($transfer->source_warehouse_id, $item->item_id, $line->batchId);

                $outboundLines[] = new StockPostingLineData(
                    itemId: $item->item_id,
                    unitOfMeasureId: $item->unit_of_measure_id,
                    stockDimensionId: $balance->stock_dimension_id,
                    quantity: $quantity,
                    batchId: $line->batchId,
                    reference: 'stock_transfer_item:'.$item->id,
                );

                $lineContext[] = [
                    'transfer_item' => $item,
                    'quantity' => $quantity,
                    'batch_id' => $line->batchId,
                    'serial_numbers' => $line->serialNumbers,
                ];
                $dispatched[$item->id] = Decimal::add(
                    $dispatched[$item->id] ?? '0',
                    $quantity,
                    Decimal::QUANTITY_SCALE,
                );
            }

            $outbound = $this->posting->post(new StockPostingData(
                movementType: StockMovementType::TransferOut,
                source: $dispatch,
                operation: 'dispatch-out',
                warehouseId: $transfer->source_warehouse_id,
                destinationWarehouseId: $transfer->destination_warehouse_id,
                postedBy: $actor->id,
                lines: $outboundLines,
                metadata: ['stock_transfer_id' => $transfer->id],
            ));

            $outbound->loadMissing('items');

            $transitDimension = StockDimension::resolve(
                $transfer->source_warehouse_id,
                type: StockDimensionType::InTransit,
            );

            $transitLines = [];

            foreach ($lineContext as $index => $context) {
                $transactionItem = $outbound->items->get($index);

                $transitLines[] = new StockPostingLineData(
                    itemId: $context['transfer_item']->item_id,
                    unitOfMeasureId: $context['transfer_item']->unit_of_measure_id,
                    stockDimensionId: $transitDimension->id,
                    quantity: $context['quantity'],
                    unitCost: $transactionItem?->unit_cost ?? '0',
                    batchId: $context['batch_id'],
                    reference: 'stock_transfer_item:'.$context['transfer_item']->id,
                );

                StockTransferDispatchItem::create([
                    'dispatch_id' => $dispatch->id,
                    'stock_transfer_item_id' => $context['transfer_item']->id,
                    'item_id' => $context['transfer_item']->item_id,
                    'quantity' => $context['quantity'],
                    'batch_id' => $context['batch_id'],
                    'serial_numbers' => $context['serial_numbers'] !== [] ? $context['serial_numbers'] : null,
                    'stock_dimension_id' => $outboundLines[$index]->stockDimensionId,
                    'stock_transaction_item_id' => $transactionItem?->id,
                    'received_quantity' => '0',
                ]);

                $context['transfer_item']->forceFill([
                    'dispatched_quantity' => Decimal::add(
                        $context['transfer_item']->dispatched_quantity,
                        $context['quantity'],
                        Decimal::QUANTITY_SCALE,
                    ),
                ])->save();
            }

            $transit = $this->posting->post(new StockPostingData(
                movementType: StockMovementType::TransferIn,
                source: $dispatch,
                operation: 'dispatch-in-transit',
                warehouseId: $transfer->source_warehouse_id,
                destinationWarehouseId: $transfer->destination_warehouse_id,
                postedBy: $actor->id,
                lines: $transitLines,
                metadata: [
                    'stock_transfer_id' => $transfer->id,
                    'transit_dimension_id' => $transitDimension->id,
                ],
            ));

            $dispatch->forceFill(['stock_transaction_id' => $outbound->id])->save();

            $allDispatched = $transfer->items()->get()->every(
                fn (StockTransferItem $item) => Decimal::compare(
                    $item->dispatched_quantity,
                    $item->approved_quantity,
                ) >= 0,
            );

            $transfer->forceFill([
                'status' => $allDispatched ? TransferStatus::InTransit : TransferStatus::PartiallyDispatched,
                'updated_by' => $actor->id,
            ])->save();

            activity('distribution')
                ->causedBy($actor)
                ->performedOn($dispatch)
                ->withProperties(['transit_transaction_id' => $transit->id])
                ->log('stock transfer dispatched');

            return $dispatch->refresh()->load('items');
        });
    }

    public function receive(StockTransferReceiptData $data, User $actor): StockTransferReceipt
    {
        return DB::transaction(function () use ($data, $actor): StockTransferReceipt {
            $dispatch = StockTransferDispatch::query()->lockForUpdate()->findOrFail($data->dispatchId);
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($dispatch->stock_transfer_id);

            if ($dispatch->status !== 'posted') {
                throw new DomainException('Only posted dispatches can be received.');
            }

            if ($data->lines === []) {
                throw new DomainException('A receipt must contain at least one line.');
            }

            $receipt = StockTransferReceipt::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::StockTransferReceipt),
                'stock_transfer_id' => $transfer->id,
                'dispatch_id' => $dispatch->id,
                'destination_warehouse_id' => $transfer->destination_warehouse_id,
                'received_by' => $data->receivedBy,
                'received_at' => $data->receivedAt !== null ? Carbon::parse($data->receivedAt) : now(),
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $actor->id,
                'notes' => $data->notes,
            ]);

            $goodLines = [];
            $damagedLines = [];
            $transitLines = [];
            $contexts = [];

            foreach ($data->lines as $line) {
                $dispatchItem = StockTransferDispatchItem::query()
                    ->where('dispatch_id', $dispatch->id)
                    ->lockForUpdate()
                    ->find($line->dispatchItemId);

                if ($dispatchItem === null) {
                    throw new DomainException('The receipt line references an unknown dispatch line.');
                }

                $received = Decimal::normalize($line->receivedQuantity, Decimal::QUANTITY_SCALE);
                $damaged = Decimal::normalize($line->damagedQuantity, Decimal::QUANTITY_SCALE);
                $missing = Decimal::normalize($line->missingQuantity, Decimal::QUANTITY_SCALE);

                if (Decimal::isNegative($received) || Decimal::isNegative($damaged) || Decimal::isNegative($missing)) {
                    throw new DomainException('Receipt quantities cannot be negative.');
                }

                $accounted = Decimal::add(
                    Decimal::add($received, $damaged, Decimal::QUANTITY_SCALE),
                    $missing,
                    Decimal::QUANTITY_SCALE,
                );

                if (! Decimal::isPositive($accounted)) {
                    continue;
                }

                $remaining = Decimal::subtract(
                    $dispatchItem->quantity,
                    $dispatchItem->received_quantity,
                    Decimal::QUANTITY_SCALE,
                );

                if (Decimal::compare($accounted, $remaining) > 0) {
                    throw new DomainException(sprintf(
                        'Receipt quantities exceed the %s still outstanding on this dispatch line.',
                        $remaining,
                    ));
                }

                $unitCost = (string) ($dispatchItem->transactionItem?->unit_cost ?? '0');
                $transferItem = StockTransferItem::query()->findOrFail($dispatchItem->stock_transfer_item_id);

                $contexts[] = [
                    'dispatch_item' => $dispatchItem,
                    'transfer_item' => $transferItem,
                    'received' => $received,
                    'damaged' => $damaged,
                    'missing' => $missing,
                    'unit_cost' => $unitCost,
                ];

                if (Decimal::isPositive($received)) {
                    $goodLines[] = new StockPostingLineData(
                        itemId: $dispatchItem->item_id,
                        unitOfMeasureId: $transferItem->unit_of_measure_id,
                        stockDimensionId: StockDimension::resolve($transfer->destination_warehouse_id)->id,
                        quantity: $received,
                        unitCost: $unitCost,
                        batchId: $dispatchItem->batch_id,
                        reference: 'dispatch_item:'.$dispatchItem->id,
                    );
                }

                if (Decimal::isPositive($damaged)) {
                    $damagedLines[] = new StockPostingLineData(
                        itemId: $dispatchItem->item_id,
                        unitOfMeasureId: $transferItem->unit_of_measure_id,
                        stockDimensionId: StockDimension::resolve(
                            $transfer->destination_warehouse_id,
                            type: StockDimensionType::Quarantine,
                        )->id,
                        quantity: $damaged,
                        unitCost: $unitCost,
                        batchId: $dispatchItem->batch_id,
                        reference: 'dispatch_item:'.$dispatchItem->id,
                    );
                }

                $transitQuantity = Decimal::add($received, $damaged, Decimal::QUANTITY_SCALE);

                if (Decimal::isPositive($transitQuantity)) {
                    $transitLines[] = new StockPostingLineData(
                        itemId: $dispatchItem->item_id,
                        unitOfMeasureId: $transferItem->unit_of_measure_id,
                        stockDimensionId: StockDimension::resolve(
                            $transfer->source_warehouse_id,
                            type: StockDimensionType::InTransit,
                        )->id,
                        quantity: $transitQuantity,
                        unitCost: $unitCost,
                        batchId: $dispatchItem->batch_id,
                        reference: 'dispatch_item:'.$dispatchItem->id,
                    );
                }
            }

            if ($contexts === []) {
                throw new DomainException('A receipt must record at least one quantity.');
            }

            $goodTransaction = $goodLines !== [] ? $this->posting->post(new StockPostingData(
                movementType: StockMovementType::TransferIn,
                source: $receipt,
                operation: 'receipt-good',
                warehouseId: $transfer->destination_warehouse_id,
                postedBy: $actor->id,
                lines: $goodLines,
                metadata: ['stock_transfer_id' => $transfer->id, 'dispatch_id' => $dispatch->id],
            )) : null;

            $damagedTransaction = $damagedLines !== [] ? $this->posting->post(new StockPostingData(
                movementType: StockMovementType::TransferIn,
                source: $receipt,
                operation: 'receipt-damaged',
                warehouseId: $transfer->destination_warehouse_id,
                postedBy: $actor->id,
                lines: $damagedLines,
                metadata: ['stock_transfer_id' => $transfer->id, 'dispatch_id' => $dispatch->id],
            )) : null;

            $transitTransaction = $transitLines !== [] ? $this->posting->post(new StockPostingData(
                movementType: StockMovementType::TransferOut,
                source: $receipt,
                operation: 'receipt-transit-out',
                warehouseId: $transfer->source_warehouse_id,
                destinationWarehouseId: $transfer->destination_warehouse_id,
                postedBy: $actor->id,
                lines: $transitLines,
                metadata: ['stock_transfer_id' => $transfer->id, 'dispatch_id' => $dispatch->id],
            )) : null;

            $goodTransaction?->loadMissing('items');
            $damagedTransaction?->loadMissing('items');

            $goodIndex = 0;
            $damagedIndex = 0;

            foreach ($contexts as $context) {
                /** @var StockTransferDispatchItem $dispatchItem */
                $dispatchItem = $context['dispatch_item'];
                /** @var StockTransferItem $transferItem */
                $transferItem = $context['transfer_item'];

                $received = $context['received'];
                $damaged = $context['damaged'];
                $missing = $context['missing'];

                $goodItem = null;

                if (Decimal::isPositive($received)) {
                    $goodItem = $goodTransaction?->items->get($goodIndex);
                    $goodIndex++;
                }

                $damagedItem = null;

                if (Decimal::isPositive($damaged)) {
                    $damagedItem = $damagedTransaction?->items->get($damagedIndex);
                    $damagedIndex++;
                }

                StockTransferReceiptItem::create([
                    'receipt_id' => $receipt->id,
                    'dispatch_item_id' => $dispatchItem->id,
                    'received_quantity' => $received,
                    'damaged_quantity' => $damaged,
                    'missing_quantity' => $missing,
                    'batch_id' => $dispatchItem->batch_id,
                    'stock_dimension_id' => $damagedItem?->stock_dimension_id ?? $goodItem?->stock_dimension_id,
                    'stock_transaction_item_id' => $goodItem?->id ?? $damagedItem?->id,
                ]);

                $accounted = Decimal::add(
                    Decimal::add($received, $damaged, Decimal::QUANTITY_SCALE),
                    $missing,
                    Decimal::QUANTITY_SCALE,
                );

                $dispatchItem->forceFill([
                    'received_quantity' => Decimal::add(
                        $dispatchItem->received_quantity,
                        $accounted,
                        Decimal::QUANTITY_SCALE,
                    ),
                ])->save();

                $newReceived = Decimal::add(
                    $transferItem->received_quantity,
                    $received,
                    Decimal::QUANTITY_SCALE,
                );

                $transferItem->forceFill([
                    'received_quantity' => $newReceived,
                    'variance_quantity' => Decimal::subtract(
                        $transferItem->dispatched_quantity,
                        $newReceived,
                        Decimal::QUANTITY_SCALE,
                    ),
                ])->save();

                if (Decimal::isPositive($damaged)) {
                    StockTransferDiscrepancy::create([
                        'stock_transfer_id' => $transfer->id,
                        'dispatch_item_id' => $dispatchItem->id,
                        'variance_type' => TransferDiscrepancyType::Damaged,
                        'quantity' => $damaged,
                        'resolution' => TransferDiscrepancyResolution::Pending,
                        'notes' => 'Damaged quantity received into quarantine.',
                    ]);
                }

                if (Decimal::isPositive($missing)) {
                    StockTransferDiscrepancy::create([
                        'stock_transfer_id' => $transfer->id,
                        'dispatch_item_id' => $dispatchItem->id,
                        'variance_type' => TransferDiscrepancyType::Missing,
                        'quantity' => $missing,
                        'resolution' => TransferDiscrepancyResolution::Pending,
                        'notes' => 'Missing quantity remains in transit pending reconciliation.',
                    ]);
                }
            }

            $receipt->forceFill([
                'stock_transaction_id' => $transitTransaction?->id
                    ?? $goodTransaction?->id
                    ?? $damagedTransaction?->id,
            ])->save();

            $this->refreshTransferStatus($transfer);

            return $receipt->refresh()->load('items');
        });
    }

    public function resolveDiscrepancy(
        StockTransferDiscrepancy $discrepancy,
        TransferDiscrepancyResolution $resolution,
        User $actor,
        ?string $notes = null,
    ): StockTransferDiscrepancy {
        if ($resolution === TransferDiscrepancyResolution::Pending) {
            throw new DomainException('A terminal discrepancy resolution is required.');
        }

        return DB::transaction(function () use ($discrepancy, $resolution, $actor, $notes): StockTransferDiscrepancy {
            $discrepancy = StockTransferDiscrepancy::query()->lockForUpdate()->findOrFail($discrepancy->id);

            if (! $discrepancy->resolution->isPending()) {
                throw new DomainException('This discrepancy has already been resolved.');
            }

            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($discrepancy->stock_transfer_id);
            $dispatchItem = StockTransferDispatchItem::query()->findOrFail($discrepancy->dispatch_item_id);

            if ($discrepancy->variance_type !== TransferDiscrepancyType::Damaged) {
                $transitDimension = StockDimension::resolve(
                    $transfer->source_warehouse_id,
                    type: StockDimensionType::InTransit,
                );
                $transferItem = StockTransferItem::query()->findOrFail($dispatchItem->stock_transfer_item_id);
                $unitCost = (string) ($dispatchItem->transactionItem?->unit_cost ?? '0');

                $quantity = Decimal::normalize($discrepancy->quantity, Decimal::QUANTITY_SCALE);

                if ($resolution === TransferDiscrepancyResolution::ReturnToSource) {
                    $this->posting->post(new StockPostingData(
                        movementType: StockMovementType::TransferOut,
                        source: $discrepancy,
                        operation: 'resolve-return-out',
                        warehouseId: $transfer->source_warehouse_id,
                        postedBy: $actor->id,
                        lines: [
                            new StockPostingLineData(
                                itemId: $dispatchItem->item_id,
                                unitOfMeasureId: $transferItem->unit_of_measure_id,
                                stockDimensionId: $transitDimension->id,
                                quantity: $quantity,
                                unitCost: $unitCost,
                                batchId: $dispatchItem->batch_id,
                            ),
                        ],
                    ));

                    $this->posting->post(new StockPostingData(
                        movementType: StockMovementType::TransferIn,
                        source: $discrepancy,
                        operation: 'resolve-return-in',
                        warehouseId: $transfer->source_warehouse_id,
                        postedBy: $actor->id,
                        lines: [
                            new StockPostingLineData(
                                itemId: $dispatchItem->item_id,
                                unitOfMeasureId: $transferItem->unit_of_measure_id,
                                stockDimensionId: StockDimension::resolve($transfer->source_warehouse_id)->id,
                                quantity: $quantity,
                                unitCost: $unitCost,
                                batchId: $dispatchItem->batch_id,
                            ),
                        ],
                    ));
                } else {
                    $this->posting->post(new StockPostingData(
                        movementType: StockMovementType::AdjustmentOut,
                        source: $discrepancy,
                        operation: 'resolve-write-off',
                        warehouseId: $transfer->source_warehouse_id,
                        postedBy: $actor->id,
                        lines: [
                            new StockPostingLineData(
                                itemId: $dispatchItem->item_id,
                                unitOfMeasureId: $transferItem->unit_of_measure_id,
                                stockDimensionId: $transitDimension->id,
                                quantity: $quantity,
                                unitCost: $unitCost,
                                batchId: $dispatchItem->batch_id,
                            ),
                        ],
                    ));
                }
            }

            $discrepancy->forceFill([
                'resolution' => $resolution,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'notes' => $notes !== null && $notes !== '' ? $notes : $discrepancy->notes,
            ])->save();

            $this->refreshTransferStatus($transfer);

            return $discrepancy->refresh();
        });
    }

    public function cancel(StockTransfer $transfer, User $actor, string $reason): StockTransfer
    {
        if (! $transfer->canBeCancelled()) {
            throw new DomainException('This transfer cannot be cancelled.');
        }

        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($transfer, $actor, $reason): StockTransfer {
            $request = ApprovalRequest::query()
                ->where('document_type', $transfer->approvalDocumentType())
                ->where('document_id', $transfer->id)
                ->where('status', 'pending')
                ->first();

            if ($request !== null) {
                $this->approvals->cancel($request, $actor, $reason);
            }

            $transfer->forceFill([
                'status' => TransferStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ])->save();

            return $transfer->refresh();
        });
    }

    public function delete(StockTransfer $transfer): void
    {
        if ($transfer->status !== TransferStatus::Draft) {
            throw new DomainException('Only draft transfers can be deleted.');
        }

        $transfer->delete();
    }

    private function refreshTransferStatus(StockTransfer $transfer): void
    {
        $transfer->refresh()->load('items');
        $dispatches = $transfer->dispatches()->with('items')->get();

        if ($dispatches->isEmpty()) {
            return;
        }

        $allAccounted = $dispatches->every(
            fn (StockTransferDispatch $dispatch) => $dispatch->items->every(
                fn (StockTransferDispatchItem $item) => Decimal::compare(
                    $item->received_quantity,
                    $item->quantity,
                ) >= 0,
            ),
        );

        $allDispatched = $transfer->items->every(
            fn (StockTransferItem $item) => Decimal::compare(
                $item->dispatched_quantity,
                $item->approved_quantity,
            ) >= 0,
        );

        if ($allAccounted && $allDispatched && ! $transfer->hasOpenDiscrepancies()) {
            $transfer->forceFill([
                'status' => TransferStatus::Completed,
                'completed_at' => now(),
            ])->save();

            return;
        }

        $transfer->forceFill(['status' => TransferStatus::PartiallyReceived])->save();
    }

    private function sourceBalance(int $warehouseId, int $itemId, ?int $batchId): InventoryBalance
    {
        $query = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->where('available_quantity', '>', 0)
            ->whereHas('dimension', fn ($query) => $query
                ->where('type', StockDimensionType::Warehouse->value)
                ->where('is_active', true));

        if ($batchId !== null) {
            $query->where('batch_key', $batchId);
        }

        $balance = $query->orderBy('batch_key')->orderBy('id')->first();

        if ($balance === null) {
            throw new DomainException('There is no available stock for the item in the source warehouse.');
        }

        return $balance;
    }

    private function syncItems(StockTransfer $transfer, StockTransferData $data): void
    {
        foreach ($data->items as $index => $item) {
            StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                ...$item->toArray(),
                'approved_quantity' => '0',
                'dispatched_quantity' => '0',
                'received_quantity' => '0',
                'variance_quantity' => '0',
                'sort_order' => $index,
            ]);
        }
    }

    private function validateTransfer(StockTransferData $data): void
    {
        if ($data->sourceWarehouseId === $data->destinationWarehouseId) {
            throw new DomainException('The source and destination warehouses must differ.');
        }

        if ($data->items === []) {
            throw new DomainException('A stock transfer must contain at least one item.');
        }

        if (blank(trim($data->reason))) {
            throw new DomainException('A transfer reason is required.');
        }

        $seen = [];

        foreach ($data->items as $item) {
            if (Decimal::compare($item->requestedQuantity, '0') <= 0) {
                throw new DomainException('Requested quantities must be greater than zero.');
            }

            if (isset($seen[$item->itemId])) {
                throw new DomainException('Duplicate item lines are not allowed. Combine quantities instead.');
            }

            $seen[$item->itemId] = true;
        }
    }
}
