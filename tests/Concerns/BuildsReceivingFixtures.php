<?php

namespace Tests\Concerns;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseOrderItem;
use App\Domain\Receiving\Models\DeliveryVerification;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Services\DeliveryVerificationService;
use App\Domain\Receiving\Services\StoreReceiptAdviceService;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Receiving\DeliveryVerificationData;
use App\DTOs\Receiving\StoreReceiptAdviceData;
use App\Enums\ValuationMethod;
use App\Models\User;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;

trait BuildsReceivingFixtures
{
    protected function seedReceiving(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingsSeeder::class,
            DocumentSequenceSeeder::class,
        ]);
    }

    protected function receivingActor(string $role = 'Store Officer'): User
    {
        return User::factory()->withRole($role)->create();
    }

    /**
     * @return array{
     *     business_unit: BusinessUnit,
     *     warehouse: Warehouse,
     *     supplier: Supplier,
     *     item: Item,
     *     unit: UnitOfMeasure,
     *     order: PurchaseOrder,
     *     order_item: PurchaseOrderItem
     * }
     */
    protected function makeReceivingContext(
        ?Item $item = null,
        string $orderedQuantity = '10.0000',
        string $unitPrice = '1500.0000',
    ): array {
        $businessUnit = BusinessUnit::factory()->create();
        $warehouse = Warehouse::factory()->forBusinessUnit($businessUnit)->create();
        $supplier = Supplier::factory()->approved()->create();
        $unit = UnitOfMeasure::factory()->create();

        $item ??= Item::factory()->create([
            'base_unit_of_measure_id' => $unit->id,
            'requires_quality_inspection' => false,
            'valuation_method' => ValuationMethod::WeightedAverage,
            'standard_cost' => $unitPrice,
        ]);

        $order = PurchaseOrder::factory()->approved()->create([
            'supplier_id' => $supplier->id,
            'delivery_warehouse_id' => $warehouse->id,
        ]);

        $orderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'item_id' => $item->id,
            'unit_of_measure_id' => $unit->id,
            'ordered_quantity' => $orderedQuantity,
            'received_quantity' => '0.0000',
            'rejected_quantity' => '0.0000',
            'unit_price' => $unitPrice,
        ]);

        return [
            'business_unit' => $businessUnit,
            'warehouse' => $warehouse,
            'supplier' => $supplier,
            'item' => $item,
            'unit' => $unit,
            'order' => $order,
            'order_item' => $orderItem,
        ];
    }

    protected function makeReceiptAdvice(
        PurchaseOrderItem $orderItem,
        User $actor,
        string $expected = '5.0000',
        string $delivered = '5.0000',
        ?string $batchNumber = null,
        array $serialNumbers = [],
    ): StoreReceiptAdvice {
        return app(StoreReceiptAdviceService::class)->create(StoreReceiptAdviceData::fromArray([
            'purchase_order_id' => $orderItem->purchase_order_id,
            'received_by' => $actor->id,
            'items' => [[
                'purchase_order_item_id' => $orderItem->id,
                'expected_quantity' => $expected,
                'delivered_quantity' => $delivered,
                'batch_number' => $batchNumber,
                'serial_numbers' => $serialNumbers,
            ]],
        ]), $actor);
    }

    protected function submitReceiptAdvice(StoreReceiptAdvice $sra, User $actor): StoreReceiptAdvice
    {
        return app(StoreReceiptAdviceService::class)->submit($sra, $actor);
    }

    protected function verifyReceiptAdvice(
        StoreReceiptAdvice $sra,
        User $actor,
        ?string $verified = null,
        string $damaged = '0',
    ): DeliveryVerification {
        $sra->loadMissing('items');

        $items = $sra->items->map(fn ($line) => [
            'sra_item_id' => $line->id,
            'verified_quantity' => $verified ?? (string) $line->delivered_quantity,
            'damaged_quantity' => $damaged,
        ])->all();

        return app(DeliveryVerificationService::class)->verify(
            $sra,
            DeliveryVerificationData::fromArray([
                'sra_id' => $sra->id,
                'verified_by' => $actor->id,
                'items' => $items,
            ]),
            $actor,
        );
    }

    protected function onHand(Item $item, Warehouse $warehouse): string
    {
        $dimension = StockDimension::resolve($warehouse->id);

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('stock_dimension_id', $dimension->id)
            ->where('item_id', $item->id)
            ->where('batch_key', 0)
            ->first();

        return $balance?->on_hand_quantity ?? '0.0000';
    }
}
