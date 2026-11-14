<?php

namespace App\Domain\Receiving\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemSerialNumber;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Domain\Receiving\Models\SupplierReturn;
use App\Domain\Receiving\Models\SupplierReturnItem;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Inventory\StockPostingData;
use App\DTOs\Inventory\StockPostingLineData;
use App\DTOs\Receiving\SupplierReturnData;
use App\DTOs\Receiving\SupplierReturnItemData;
use App\Enums\DocumentType;
use App\Enums\ReturnStatus;
use App\Enums\SerialStatus;
use App\Enums\StockMovementType;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class SupplierReturnService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly InventoryPostingService $posting,
    ) {}

    public function create(SupplierReturnData $data, User $actor): SupplierReturn
    {
        $this->validateHeader($data);
        $this->validateItems($data->items);

        return DB::transaction(function () use ($data, $actor): SupplierReturn {
            $return = SupplierReturn::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::SupplierReturn),
                'supplier_id' => $data->supplierId,
                'grn_id' => $data->grnId,
                'warehouse_id' => $data->warehouseId,
                'reason' => $data->reason,
                'status' => ReturnStatus::Draft,
                'return_date' => $data->returnDate ?? now()->toDateString(),
                'credit_note_number' => $data->creditNoteNumber,
                'created_by' => $actor->id,
            ]);

            $this->syncItems($return, $data->items);

            return $return->refresh()->load('items.item', 'items.unitOfMeasure');
        });
    }

    public function update(SupplierReturn $return, SupplierReturnData $data, User $actor): SupplierReturn
    {
        if (! $return->canBeEdited()) {
            throw new DomainException('Only draft supplier returns can be edited.');
        }

        $this->validateHeader($data);
        $this->validateItems($data->items);

        return DB::transaction(function () use ($return, $data): SupplierReturn {
            $return->update([
                'supplier_id' => $data->supplierId,
                'grn_id' => $data->grnId,
                'warehouse_id' => $data->warehouseId,
                'reason' => $data->reason,
                'return_date' => $data->returnDate ?? $return->return_date?->toDateString(),
                'credit_note_number' => $data->creditNoteNumber,
            ]);

            $return->items()->delete();
            $this->syncItems($return, $data->items);

            return $return->refresh()->load('items.item', 'items.unitOfMeasure');
        });
    }

    /**
     * Post the return to stock as an outbound movement.
     *
     * The posting service owns availability checks, so attempting to return more
     * than is on hand fails before any ledger row is written.
     */
    public function post(SupplierReturn $return, User $actor): SupplierReturn
    {
        if ($return->status === ReturnStatus::Posted) {
            return $return;
        }

        if (! $return->canBePosted()) {
            throw new DomainException('Only draft or assessed supplier returns can be posted.');
        }

        $return->loadMissing('items.item', 'grn');

        if ($return->items->isEmpty()) {
            throw new DomainException('A supplier return must contain at least one line.');
        }

        $dimension = StockDimension::resolve($return->warehouse_id);
        $lines = [];
        $lineMap = [];

        foreach ($return->items as $item) {
            $item->loadMissing('item');
            $quantity = Decimal::normalize($item->quantity);

            if (! Decimal::isPositive($quantity)) {
                throw new DomainException('Supplier return quantities must be greater than zero.');
            }

            $batchId = $item->batch_id;

            if ($item->item?->tracking_type->requiresBatch() && $batchId === null) {
                throw new DomainException(sprintf(
                    '%s is batch tracked and requires a batch on the return.',
                    $item->item?->name ?? 'Item',
                ));
            }

            $serials = array_values(array_filter($item->serial_numbers ?? []));

            if ($item->item?->tracking_type->requiresSerial()) {
                $this->assertSerialAvailability($return, $item, $quantity, $serials);

                foreach ($serials as $serial) {
                    $serialRecord = $this->serialRecord($item->item_id, $serial);

                    $lines[] = new StockPostingLineData(
                        itemId: $item->item_id,
                        unitOfMeasureId: $item->unit_of_measure_id,
                        stockDimensionId: $dimension->id,
                        quantity: '1',
                        unitCost: (string) $item->unit_cost,
                        batchId: $batchId ?? $serialRecord?->batch_id,
                        serialNumberId: $serialRecord?->id,
                        reference: (string) $item->id,
                    );
                }

                $lineMap[$item->id] = (string) $item->id;

                continue;
            }

            $lines[] = new StockPostingLineData(
                itemId: $item->item_id,
                unitOfMeasureId: $item->unit_of_measure_id,
                stockDimensionId: $dimension->id,
                quantity: $quantity,
                unitCost: (string) $item->unit_cost,
                batchId: $batchId,
                reference: (string) $item->id,
            );

            $lineMap[$item->id] = (string) $item->id;
        }

        if ($lines === []) {
            throw new DomainException('A supplier return must contain at least one line.');
        }

        return DB::transaction(function () use ($return, $actor, $lines): SupplierReturn {
            $transaction = $this->posting->post(new StockPostingData(
                movementType: StockMovementType::ReturnOut,
                source: $return,
                operation: 'supplier_return_post',
                warehouseId: $return->warehouse_id,
                postedBy: $actor->id,
                lines: $lines,
                metadata: [
                    'supplier_return_id' => $return->id,
                    'supplier_id' => $return->supplier_id,
                    'grn_id' => $return->grn_id,
                ],
            ));

            $this->linkTransactionItems($return, $transaction);
            $this->releaseSerials($return);

            $return->forceFill([
                'status' => ReturnStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor->id,
            ])->save();

            return $return->refresh()->load('items.item', 'items.unitOfMeasure');
        });
    }

    public function cancel(SupplierReturn $return, User $actor): SupplierReturn
    {
        if (! $return->canBeCancelled()) {
            throw new DomainException('Only draft supplier returns can be cancelled.');
        }

        $return->forceFill(['status' => ReturnStatus::Cancelled])->save();

        return $return->refresh();
    }

    public function delete(SupplierReturn $return): void
    {
        if (! $return->canBeEdited()) {
            throw new DomainException('Only draft supplier returns can be deleted.');
        }

        $return->delete();
    }

    private function validateHeader(SupplierReturnData $data): void
    {
        if (blank($data->reason)) {
            throw new DomainException('A return reason is required.');
        }

        if (Supplier::query()->whereKey($data->supplierId)->doesntExist()) {
            throw new DomainException('The supplier could not be found.');
        }

        if (Warehouse::query()->whereKey($data->warehouseId)->doesntExist()) {
            throw new DomainException('The warehouse could not be found.');
        }

        if ($data->grnId !== null) {
            $grn = GoodsReceivedNote::query()->find($data->grnId);

            if ($grn === null) {
                throw new DomainException('The goods received note could not be found.');
            }

            if ((int) $grn->supplier_id !== $data->supplierId) {
                throw new DomainException('The return supplier does not match the goods received note.');
            }

            if ((int) $grn->warehouse_id !== $data->warehouseId) {
                throw new DomainException('The return warehouse does not match the goods received note.');
            }
        }
    }

    /**
     * @param  list<SupplierReturnItemData>  $items
     */
    private function validateItems(array $items): void
    {
        if ($items === []) {
            throw new DomainException('A supplier return must contain at least one line.');
        }

        foreach ($items as $item) {
            if (! Decimal::isPositive($item->quantity)) {
                throw new DomainException('Supplier return quantities must be greater than zero.');
            }

            if (Decimal::isNegative($item->unitCost)) {
                throw new DomainException('Unit costs cannot be negative.');
            }

            if (Item::query()->whereKey($item->itemId)->doesntExist()) {
                throw new DomainException('A supplier return line references an unknown item.');
            }
        }
    }

    /**
     * @param  list<SupplierReturnItemData>  $items
     */
    private function syncItems(SupplierReturn $return, array $items): void
    {
        $sort = 0;

        foreach ($items as $item) {
            SupplierReturnItem::create([
                'supplier_return_id' => $return->id,
                'item_id' => $item->itemId,
                'unit_of_measure_id' => $item->unitOfMeasureId,
                'quantity' => Decimal::normalize($item->quantity),
                'unit_cost' => Decimal::normalize($item->unitCost, Decimal::COST_SCALE),
                'batch_id' => $item->batchId,
                'serial_numbers' => $item->serialNumbers !== [] ? $item->serialNumbers : null,
                'condition' => $item->condition,
                'sort_order' => $sort++,
            ]);
        }
    }

    /**
     * @param  list<string>  $serials
     */
    private function assertSerialAvailability(
        SupplierReturn $return,
        SupplierReturnItem $item,
        string $quantity,
        array $serials,
    ): void {
        if (count($serials) !== (int) Decimal::normalize($quantity, 0)) {
            throw new DomainException(sprintf(
                '%s is serial tracked: %d serial number(s) supplied for a return quantity of %s.',
                $item->item?->name ?? 'Item',
                count($serials),
                $quantity,
            ));
        }

        $available = ItemSerialNumber::query()
            ->where('item_id', $item->item_id)
            ->where('current_warehouse_id', $return->warehouse_id)
            ->where('status', SerialStatus::Available->value)
            ->whereIn('serial_number', $serials)
            ->count();

        if ($available !== count($serials)) {
            throw new DomainException('One or more serial numbers are not available in this warehouse.');
        }
    }

    private function serialRecord(int $itemId, string $serial): ?ItemSerialNumber
    {
        return ItemSerialNumber::query()
            ->where('item_id', $itemId)
            ->where('serial_number', $serial)
            ->first();
    }

    private function linkTransactionItems(SupplierReturn $return, StockTransaction $transaction): void
    {
        $byReference = $transaction->items->keyBy(
            fn ($item) => (string) ($item->metadata['reference'] ?? ''),
        );

        foreach ($return->items as $item) {
            $transactionItem = $byReference->get((string) $item->id);

            if ($transactionItem === null) {
                continue;
            }

            $item->forceFill(['stock_transaction_item_id' => $transactionItem->id])->save();
        }
    }

    private function releaseSerials(SupplierReturn $return): void
    {
        $return->loadMissing('items.item');

        foreach ($return->items as $item) {
            if ($item->item?->tracking_type->requiresSerial() !== true) {
                continue;
            }

            ItemSerialNumber::query()
                ->where('item_id', $item->item_id)
                ->whereIn('serial_number', $item->serial_numbers ?? [])
                ->update([
                    'status' => SerialStatus::Returned->value,
                    'current_warehouse_id' => null,
                ]);
        }
    }
}
