<?php

namespace Tests\Concerns;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Stocktaking\Models\StockAdjustment;
use App\Domain\Stocktaking\Models\StockCount;
use App\Domain\Stocktaking\Services\StockAdjustmentService;
use App\Domain\Stocktaking\Services\StockCountService;
use App\Domain\Warehouse\Models\UserWarehouseAccess;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Stocktaking\StockAdjustmentData;
use App\DTOs\Stocktaking\StockAdjustmentItemData;
use App\DTOs\Stocktaking\StockCountData;
use App\DTOs\Stocktaking\StockCountEntryData;
use App\DTOs\Stocktaking\StockCountScopeData;
use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Enums\CountType;
use App\Enums\ItemTrackingType;
use App\Enums\ValuationMethod;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Decimal;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Str;

trait BuildsStocktakingFixtures
{
    use BuildsInventoryFixtures;
    use BuildsProcurementFixtures;

    protected function seedStocktaking(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingsSeeder::class,
            DocumentSequenceSeeder::class,
        ]);

        $this->makeProcurementFlow('stock_adjustment', 'Warehouse Manager');
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeStocktakingContext(
        ?Item $item = null,
        string $stock = '100.0000',
        string $unitCost = '100.0000',
    ): array {
        $this->seedStocktaking();

        $businessUnit = BusinessUnit::factory()->create();
        $warehouse = Warehouse::factory()->forBusinessUnit($businessUnit)->create();
        $unit = UnitOfMeasure::factory()->create();

        $item ??= Item::factory()->create([
            'base_unit_of_measure_id' => $unit->id,
            'tracking_type' => ItemTrackingType::None,
            'valuation_method' => ValuationMethod::WeightedAverage,
            'standard_cost' => $unitCost,
            'requires_quality_inspection' => false,
        ]);

        $storeOfficer = User::factory()
            ->withRole('Store Officer')
            ->create();

        $warehouseManager = User::factory()
            ->withRole('Warehouse Manager')
            ->create();

        $controller = User::factory()
            ->withRole('Warehouse Manager')
            ->create();

        $counter = User::factory()
            ->withRole('Quality Inspector')
            ->create();

        foreach ([$storeOfficer, $warehouseManager, $controller, $counter] as $user) {
            $this->grantStocktakingWarehouseAccess($user, $warehouse);
        }

        $context = [
            'business_unit' => $businessUnit,
            'warehouse' => $warehouse,
            'item' => $item,
            'unit' => $unit,
            'store_officer' => $storeOfficer,
            'warehouse_manager' => $warehouseManager,
            'controller' => $controller,
            'counter' => $counter,
            'dimension' => StockDimension::resolve($warehouse->id),
            'actor' => $storeOfficer,
        ];

        if (Decimal::isPositive($stock)) {
            $this->receiveStock($context, $stock, $unitCost);
        }

        return $context;
    }

    protected function grantStocktakingWarehouseAccess(
        User $user,
        Warehouse $warehouse,
        WarehouseAccessLevel $level = WarehouseAccessLevel::Operate,
    ): UserWarehouseAccess {
        return UserWarehouseAccess::query()->create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'access_level' => $level,
            'is_primary' => false,
            'granted_by' => $user->id,
            'granted_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function makeStockAdjustment(
        array $context,
        string $signedQuantity = '5.0000',
        ?User $actor = null,
        bool $autoSubmit = false,
        ?AdjustmentReason $reason = null,
        ?AdjustmentType $type = null,
    ): StockAdjustment {
        $type ??= AdjustmentType::fromDelta($signedQuantity);

        return app(StockAdjustmentService::class)->create(new StockAdjustmentData(
            warehouseId: $context['warehouse']->id,
            adjustmentType: $type,
            reasonCode: $reason ?? AdjustmentReason::CountCorrection,
            reason: 'Stocktaking fixture adjustment.',
            items: [
                new StockAdjustmentItemData(
                    itemId: $context['item']->id,
                    unitOfMeasureId: $context['unit']->id,
                    stockDimensionId: $context['dimension']->id,
                    quantityDelta: $signedQuantity,
                    unitCost: '100.0000',
                ),
            ],
            autoSubmit: $autoSubmit,
        ), $actor ?? $context['store_officer']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function makeStockCount(
        array $context,
        CountType $type = CountType::Cycle,
        ?User $actor = null,
        string $toleranceQuantity = '0.0000',
        string $toleranceValue = '0.00',
    ): StockCount {
        return app(StockCountService::class)->create(new StockCountData(
            warehouseId: $context['warehouse']->id,
            countType: $type,
            scopeDescription: 'Fixture cycle count for the main store.',
            scopes: [new StockCountScopeData],
            toleranceQuantity: $toleranceQuantity,
            toleranceValue: $toleranceValue,
        ), $actor ?? $context['store_officer']);
    }

    /**
     * Create, start and count a single-line stock count.
     *
     * @param  array<string, mixed>  $context
     */
    protected function countedStockCount(
        array $context,
        string $countedQuantity = '98.0000',
        CountType $type = CountType::Cycle,
    ): StockCount {
        $service = app(StockCountService::class);
        $count = $service->start($this->makeStockCount($context, $type), $context['store_officer']);
        $item = $count->items()->firstOrFail();

        $service->recordCount($count, [
            new StockCountEntryData(
                stockCountItemId: $item->id,
                countedQuantity: $countedQuantity,
                notes: 'Fixture physical count.',
            ),
        ], $context['counter']);

        return $count->refresh()->load('items', 'variances');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function inventoryBalanceFor(array $context): ?InventoryBalance
    {
        return InventoryBalance::query()
            ->where('warehouse_id', $context['warehouse']->id)
            ->where('stock_dimension_id', $context['dimension']->id)
            ->where('item_id', $context['item']->id)
            ->where('batch_key', 0)
            ->first();
    }

    protected function uniqueOperation(string $prefix): string
    {
        return $prefix.':'.Str::uuid();
    }
}
