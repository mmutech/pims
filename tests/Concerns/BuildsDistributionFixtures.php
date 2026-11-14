<?php

namespace Tests\Concerns;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Models\MaterialRequisitionItem;
use App\Domain\Distribution\Models\MaterialReturn;
use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Distribution\Models\StockTransferDispatch;
use App\Domain\Distribution\Models\StockTransferReceipt;
use App\Domain\Distribution\Services\MaterialRequisitionService;
use App\Domain\Distribution\Services\MaterialReturnService;
use App\Domain\Distribution\Services\StockIssueService;
use App\Domain\Distribution\Services\StockTransferService;
use App\Domain\Inventory\Models\StockDimension;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\StockReservationItem;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Domain\Warehouse\Models\UserWarehouseAccess;
use App\Domain\Warehouse\Models\Warehouse;
use App\DTOs\Distribution\MaterialRequisitionData;
use App\DTOs\Distribution\MaterialReturnData;
use App\DTOs\Distribution\StockIssueData;
use App\DTOs\Distribution\StockTransferData;
use App\DTOs\Distribution\StockTransferDispatchData;
use App\DTOs\Distribution\StockTransferDispatchLineData;
use App\DTOs\Distribution\StockTransferReceiptData;
use App\DTOs\Distribution\StockTransferReceiptLineData;
use App\Enums\ItemTrackingType;
use App\Enums\MaterialCondition;
use App\Enums\MaterialReturnType;
use App\Enums\ValuationMethod;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use App\Support\Decimal;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;

trait BuildsDistributionFixtures
{
    use BuildsInventoryFixtures;
    use BuildsProcurementFixtures;

    protected function seedDistribution(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingsSeeder::class,
            DocumentSequenceSeeder::class,
        ]);

        $this->makeProcurementFlow('material_requisition', 'Warehouse Manager');
        $this->makeProcurementFlow('stock_transfer', 'Warehouse Manager');
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeDistributionContext(
        ?Item $item = null,
        string $stock = '100.0000',
        string $unitCost = '100.0000',
    ): array {
        $this->seedDistribution();

        $businessUnit = BusinessUnit::factory()->create();
        $department = Department::factory()->create();
        $warehouse = Warehouse::factory()->forBusinessUnit($businessUnit)->create();
        $destinationWarehouse = Warehouse::factory()->forBusinessUnit($businessUnit)->create();

        $unit = UnitOfMeasure::factory()->create();

        $item ??= Item::factory()->create([
            'base_unit_of_measure_id' => $unit->id,
            'tracking_type' => ItemTrackingType::None,
            'valuation_method' => ValuationMethod::WeightedAverage,
            'standard_cost' => $unitCost,
            'requires_quality_inspection' => false,
        ]);

        $requester = User::factory()
            ->withRole('Department Requester')
            ->create(['department_id' => $department->id]);

        $storeOfficer = User::factory()
            ->withRole('Store Officer')
            ->create();

        $warehouseManager = User::factory()
            ->withRole('Warehouse Manager')
            ->create();

        $destinationOfficer = User::factory()
            ->withRole('Store Officer')
            ->create();

        $qualityInspector = User::factory()
            ->withRole('Quality Inspector')
            ->create();

        $this->grantWarehouseAccess($storeOfficer, $warehouse);
        $this->grantWarehouseAccess($warehouseManager, $warehouse);
        $this->grantWarehouseAccess($warehouseManager, $destinationWarehouse);
        $this->grantWarehouseAccess($destinationOfficer, $destinationWarehouse);
        $this->grantWarehouseAccess($qualityInspector, $warehouse);

        $context = [
            'business_unit' => $businessUnit,
            'department' => $department,
            'warehouse' => $warehouse,
            'destination_warehouse' => $destinationWarehouse,
            'item' => $item,
            'unit' => $unit,
            'requester' => $requester,
            'store_officer' => $storeOfficer,
            'warehouse_manager' => $warehouseManager,
            'destination_officer' => $destinationOfficer,
            'quality_inspector' => $qualityInspector,
            'dimension' => StockDimension::resolve($warehouse->id),
            'destination_dimension' => StockDimension::resolve($destinationWarehouse->id),
            'actor' => $storeOfficer,
        ];

        if (Decimal::isPositive($stock)) {
            $this->receiveStock($context, $stock, $unitCost);
        }

        return $context;
    }

    protected function grantWarehouseAccess(
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
     * @param  array<string, mixed>  $overrides
     */
    protected function makeMaterialRequisition(
        array $context,
        string $quantity = '20.0000',
        array $overrides = [],
    ): MaterialRequisition {
        return app(MaterialRequisitionService::class)->create(
            MaterialRequisitionData::fromArray([
                'requesting_department_id' => $context['department']->id,
                'requested_by' => $context['requester']->id,
                'destination_warehouse_id' => $context['warehouse']->id,
                'purpose' => 'Maintenance materials for the district feeder network.',
                'items' => [[
                    'item_id' => $context['item']->id,
                    'unit_of_measure_id' => $context['unit']->id,
                    'requested_quantity' => $quantity,
                ]],
                ...$overrides,
            ]),
            $context['requester'],
        );
    }

    protected function submitAndApproveRequisition(
        MaterialRequisition $requisition,
        array $context,
        ?User $approver = null,
    ): MaterialRequisition {
        $service = app(MaterialRequisitionService::class);

        $service->submit($requisition, $context['requester']);
        $this->approveDocument($requisition, $approver ?? $context['warehouse_manager']);

        return $requisition->refresh()->load('items');
    }

    protected function reserveAndIssueRequisition(
        MaterialRequisition $requisition,
        array $context,
        ?string $quantity = null,
        ?User $recipient = null,
    ): StockIssue {
        $issue = $this->draftRequisitionIssue($requisition, $context, $quantity, $recipient);

        return app(StockIssueService::class)->post($issue, $context['store_officer']);
    }

    protected function draftRequisitionIssue(
        MaterialRequisition $requisition,
        array $context,
        ?string $quantity = null,
        ?User $recipient = null,
    ): StockIssue {
        $service = app(MaterialRequisitionService::class);
        $service->reserve($requisition, $context['store_officer']);

        $requisition = $requisition->refresh()->load('items');
        /** @var MaterialRequisitionItem $item */
        $item = $requisition->items->first();

        $reservation = StockReservation::query()
            ->where('source_type', $requisition->getMorphClass())
            ->where('source_id', $requisition->id)
            ->open()
            ->firstOrFail();

        $reservationItem = StockReservationItem::query()
            ->where('stock_reservation_id', $reservation->id)
            ->where('item_id', $item->item_id)
            ->firstOrFail();

        $quantity ??= (string) $item->reserved_quantity;

        return app(StockIssueService::class)->create(StockIssueData::fromArray([
            'material_requisition_id' => $requisition->id,
            'warehouse_id' => $context['warehouse']->id,
            'issued_to' => ($recipient ?? $context['requester'])->id,
            'issued_by' => $context['store_officer']->id,
            'items' => [[
                'material_requisition_item_id' => $item->id,
                'stock_reservation_item_id' => $reservationItem->id,
                'quantity' => $quantity,
            ]],
        ]), $context['store_officer']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function postedRequisitionIssue(array $context, string $quantity = '10.0000'): StockIssue
    {
        $requisition = $this->submitAndApproveRequisition(
            $this->makeMaterialRequisition($context, $quantity),
            $context,
        );

        return $this->reserveAndIssueRequisition($requisition, $context);
    }

    protected function makeDistributionUser(
        string $role,
        array $context,
        ?Warehouse $warehouse = null,
        WarehouseAccessLevel $level = WarehouseAccessLevel::Operate,
    ): User {
        $user = User::factory()
            ->withRole($role)
            ->create();

        if ($warehouse !== null) {
            $this->grantWarehouseAccess($user, $warehouse, $level);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeStockTransfer(
        array $context,
        string $quantity = '20.0000',
        array $overrides = [],
    ): StockTransfer {
        return app(StockTransferService::class)->create(
            StockTransferData::fromArray([
                'source_warehouse_id' => $context['warehouse']->id,
                'destination_warehouse_id' => $context['destination_warehouse']->id,
                'requested_by' => $context['store_officer']->id,
                'reason' => 'Rebalance stock to the district store.',
                'items' => [[
                    'item_id' => $context['item']->id,
                    'unit_of_measure_id' => $context['unit']->id,
                    'requested_quantity' => $quantity,
                ]],
                ...$overrides,
            ]),
            $context['store_officer'],
        );
    }

    protected function submitAndApproveTransfer(
        StockTransfer $transfer,
        array $context,
        ?User $approver = null,
    ): StockTransfer {
        $service = app(StockTransferService::class);

        $service->submit($transfer, $context['store_officer']);
        $this->approveDocument($transfer, $approver ?? $context['warehouse_manager']);

        return $transfer->refresh()->load('items');
    }

    protected function dispatchTransfer(
        StockTransfer $transfer,
        array $context,
        ?string $quantity = null,
    ): StockTransferDispatch {
        $transfer->refresh()->load('items');

        $lines = $transfer->items->map(fn ($item) => new StockTransferDispatchLineData(
            stockTransferItemId: $item->id,
            quantity: $quantity ?? (string) $item->approved_quantity,
        ))->all();

        return app(StockTransferService::class)->dispatch(new StockTransferDispatchData(
            stockTransferId: $transfer->id,
            dispatchedBy: $context['store_officer']->id,
            lines: $lines,
        ), $context['store_officer']);
    }

    protected function receiveTransfer(
        StockTransferDispatch $dispatch,
        array $context,
        array $lines,
        ?User $receiver = null,
    ): StockTransferReceipt {
        $lineData = array_map(fn (array $line) => new StockTransferReceiptLineData(
            dispatchItemId: (int) $line['dispatch_item_id'],
            receivedQuantity: (string) ($line['received_quantity'] ?? '0'),
            damagedQuantity: (string) ($line['damaged_quantity'] ?? '0'),
            missingQuantity: (string) ($line['missing_quantity'] ?? '0'),
        ), $lines);

        $receiver ??= $context['destination_officer'];

        return app(StockTransferService::class)->receive(new StockTransferReceiptData(
            dispatchId: $dispatch->id,
            receivedBy: $receiver->id,
            lines: $lineData,
        ), $receiver);
    }

    protected function makeProject(array $context): Project
    {
        return Project::factory()
            ->active()
            ->create(['department_id' => $context['department']->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeMaterialReturn(
        StockIssue $issue,
        array $context,
        string $quantity,
        MaterialCondition $condition = MaterialCondition::Good,
        array $overrides = [],
        ?User $actor = null,
    ): MaterialReturn {
        $actor ??= $context['requester'];

        return app(MaterialReturnService::class)->create(
            MaterialReturnData::fromArray([
                'return_type' => MaterialReturnType::Project->value,
                'original_issue_id' => $issue->id,
                'warehouse_id' => $context['warehouse']->id,
                'returned_by' => $context['requester']->id,
                'reason' => 'Surplus material returned from site.',
                'items' => [[
                    'item_id' => $context['item']->id,
                    'unit_of_measure_id' => $context['unit']->id,
                    'returned_quantity' => $quantity,
                    'condition' => $condition->value,
                ]],
                ...$overrides,
            ]),
            $actor,
        );
    }
}
