<?php

namespace Tests\Concerns;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\Domain\Inventory\Services\StockReservationService;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Inventory\StockPostingData;
use App\DTOs\Inventory\StockPostingLineData;
use App\DTOs\Inventory\StockReservationData;
use App\DTOs\Inventory\StockReservationLineData;
use App\Enums\ItemTrackingType;
use App\Enums\StockMovementType;
use App\Enums\ValuationMethod;
use App\Models\User;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait BuildsInventoryFixtures
{
    protected function seedInventory(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingsSeeder::class,
            DocumentSequenceSeeder::class,
        ]);
    }

    /**
     * @return array{
     *     business_unit: BusinessUnit,
     *     warehouse: Warehouse,
     *     item: Item,
     *     unit: UnitOfMeasure,
     *     dimension: StockDimension,
     *     actor: User
     * }
     */
    protected function makeInventoryContext(
        ?Item $item = null,
        string $standardCost = '100.0000',
    ): array {
        $businessUnit = BusinessUnit::factory()->create();
        $warehouse = Warehouse::factory()->forBusinessUnit($businessUnit)->create();
        $unit = UnitOfMeasure::factory()->create();

        $item ??= Item::factory()->create([
            'base_unit_of_measure_id' => $unit->id,
            'tracking_type' => ItemTrackingType::None,
            'valuation_method' => ValuationMethod::WeightedAverage,
            'standard_cost' => $standardCost,
            'requires_quality_inspection' => false,
        ]);

        $actor = User::factory()
            ->withRole('Store Officer')
            ->create();

        return [
            'business_unit' => $businessUnit,
            'warehouse' => $warehouse,
            'item' => $item,
            'unit' => $unit,
            'dimension' => StockDimension::resolve($warehouse->id),
            'actor' => $actor,
        ];
    }

    /**
     * Seed stock through the central posting service (opening balance).
     */
    protected function receiveStock(
        array $context,
        string $quantity = '10.0000',
        string $unitCost = '100.0000',
        ?int $batchId = null,
    ): StockTransaction {
        return app(InventoryPostingService::class)->post(new StockPostingData(
            movementType: StockMovementType::OpeningBalance,
            source: $context['item'],
            operation: 'opening:'.Str::uuid(),
            warehouseId: $context['warehouse']->id,
            postedBy: $context['actor']->id,
            lines: [
                new StockPostingLineData(
                    itemId: $context['item']->id,
                    unitOfMeasureId: $context['unit']->id,
                    stockDimensionId: $context['dimension']->id,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    batchId: $batchId,
                ),
            ],
        ));
    }

    /**
     * @param  array{source?: Model, expires_at?: ?string, item_id?: int, batch_id?: ?int, dimension_id?: ?int}  $options
     */
    protected function reserveStock(array $context, string $quantity, array $options = []): StockReservation
    {
        return app(StockReservationService::class)->reserve(new StockReservationData(
            source: $options['source'] ?? $context['item'],
            warehouseId: $context['warehouse']->id,
            lines: [
                new StockReservationLineData(
                    itemId: $options['item_id'] ?? $context['item']->id,
                    stockDimensionId: $options['dimension_id'] ?? $context['dimension']->id,
                    quantity: $quantity,
                    batchId: $options['batch_id'] ?? null,
                ),
            ],
            createdBy: $context['actor']->id,
            expiresAt: $options['expires_at'] ?? null,
        ));
    }

    /**
     * Post an outbound movement, optionally consuming a reservation line.
     */
    protected function issueStock(
        array $context,
        string $quantity,
        ?int $reservationItemId = null,
    ): StockTransaction {
        return app(InventoryPostingService::class)->post(new StockPostingData(
            movementType: StockMovementType::Issue,
            source: $context['item'],
            operation: 'issue:'.Str::uuid(),
            warehouseId: $context['warehouse']->id,
            postedBy: $context['actor']->id,
            lines: [
                new StockPostingLineData(
                    itemId: $context['item']->id,
                    unitOfMeasureId: $context['unit']->id,
                    stockDimensionId: $context['dimension']->id,
                    quantity: $quantity,
                    reservationItemId: $reservationItemId,
                ),
            ],
        ));
    }
}
