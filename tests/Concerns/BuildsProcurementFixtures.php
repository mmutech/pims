<?php

namespace Tests\Concerns;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\Department;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Procurement\Services\PurchaseOrderService;
use App\Domain\Procurement\Services\PurchaseRequisitionService;
use App\Domain\Procurement\Services\QuotationEvaluationService;
use App\Domain\Procurement\Services\RequestForQuotationService;
use App\Domain\Procurement\Services\SupplierQuotationService;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\UserWarehouseAccess;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalFlow;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Services\ApprovalFlowService;
use App\DTOs\Procurement\PurchaseRequisitionData;
use App\DTOs\Procurement\QuotationData;
use App\DTOs\Procurement\RfqData;
use App\Enums\ApprovalActionType;
use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalMode;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

trait BuildsProcurementFixtures
{
    protected function seedProcurement(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingsSeeder::class,
            DocumentSequenceSeeder::class,
        ]);
    }

    protected function procurementApprover(?Warehouse $warehouse = null): User
    {
        $approver = User::factory()->withRole('Procurement Manager')->create();

        if ($warehouse !== null) {
            UserWarehouseAccess::query()->create([
                'user_id' => $approver->id,
                'warehouse_id' => $warehouse->id,
                'access_level' => WarehouseAccessLevel::View,
                'is_primary' => false,
                'granted_by' => $approver->id,
                'granted_at' => now(),
            ]);
        }

        return $approver;
    }

    /**
     * @param  list<array<string, mixed>>  $stages
     */
    protected function makeProcurementFlow(string $documentType, string $role = 'Procurement Manager', array $stages = []): ApprovalFlow
    {
        $actor = User::factory()->withRole('System Administrator')->create();

        $stageDefinitions = $stages !== [] ? $stages : [[
            'name' => 'Review',
            'approval_mode' => ApprovalMode::AnyOne->value,
            'is_final' => true,
            'prevent_self_approval' => true,
            'assignments' => [[
                'assignee_type' => ApprovalAssigneeType::Role->value,
                'role_id' => Role::findByName($role)->id,
            ]],
        ]];

        return app(ApprovalFlowService::class)->create([
            'name' => str($documentType)->headline().' approval',
            'document_type' => $documentType,
            'is_active' => true,
            'stages' => $stageDefinitions,
        ], $actor);
    }

    /**
     * @return array{0: Item, 1: UnitOfMeasure}
     */
    protected function makeCatalogItem(): array
    {
        $unit = UnitOfMeasure::factory()->create();
        $item = Item::factory()->create(['base_unit_of_measure_id' => $unit->id]);

        return [$item, $unit];
    }

    protected function makeDepartment(): Department
    {
        return Department::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeRequisitionData(
        BusinessUnit $businessUnit,
        Department $department,
        User $requester,
        Item $item,
        UnitOfMeasure $unit,
        array $overrides = [],
    ): PurchaseRequisitionData {
        return PurchaseRequisitionData::fromArray([
            'requesting_department_id' => $department->id,
            'business_unit_id' => $businessUnit->id,
            'requested_by' => $requester->id,
            'justification' => 'Replacement distribution equipment for the district office.',
            'items' => [[
                'item_id' => $item->id,
                'unit_of_measure_id' => $unit->id,
                'requested_quantity' => '10',
                'estimated_unit_price' => '1500.00',
            ]],
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeRequisition(
        User $actor,
        BusinessUnit $businessUnit,
        Department $department,
        Item $item,
        UnitOfMeasure $unit,
        array $overrides = [],
    ): PurchaseRequisition {
        return app(PurchaseRequisitionService::class)->create(
            $this->makeRequisitionData($businessUnit, $department, $actor, $item, $unit, $overrides),
            $actor,
        );
    }

    /**
     * @param  list<Supplier>  $suppliers
     * @param  array<string, mixed>  $overrides
     */
    protected function makeIssuedRfq(
        PurchaseRequisition $requisition,
        User $actor,
        array $suppliers,
        array $overrides = [],
    ): RequestForQuotation {
        $rfq = app(RequestForQuotationService::class)->createFromRequisition($requisition, RfqData::fromArray([
            'business_unit_id' => $requisition->business_unit_id,
            'title' => 'Transformer supply',
            'closing_date' => now()->addDays(10)->toDateString(),
            'supplier_ids' => array_map(fn (Supplier $supplier) => $supplier->id, $suppliers),
            ...$overrides,
        ]), $actor);

        return app(RequestForQuotationService::class)->issue($rfq, $actor);
    }

    protected function makeQuotation(
        RequestForQuotation $rfq,
        Supplier $supplier,
        User $actor,
        string $unitPrice = '1250.00',
    ): SupplierQuotation {
        $items = $rfq->items()->get()->map(fn ($item) => [
            'rfq_item_id' => $item->id,
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => '7.5',
        ])->all();

        $quotation = app(SupplierQuotationService::class)->create(QuotationData::fromArray([
            'request_for_quotation_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'currency' => 'NGN',
            'items' => $items,
        ]), $actor);

        return app(SupplierQuotationService::class)->submit($quotation, $actor);
    }

    protected function makeEvaluation(
        RequestForQuotation $rfq,
        SupplierQuotation $recommended,
        User $actor,
        ?string $comments = 'Best overall value.',
    ): QuotationEvaluation {
        return app(QuotationEvaluationService::class)->create(
            $rfq,
            $recommended->id,
            $comments,
            [],
            $actor,
        );
    }

    protected function makeOrderFromQuotation(
        SupplierQuotation $quotation,
        Warehouse $warehouse,
        User $actor,
    ): PurchaseOrder {
        return app(PurchaseOrderService::class)->createFromQuotation(
            $quotation,
            $warehouse->id,
            $actor,
        );
    }

    protected function approvalRequestFor(Model $document): ?ApprovalRequest
    {
        /** @var object{approvalDocumentType: callable} $document */
        return ApprovalRequest::query()
            ->where('document_type', $document->approvalDocumentType())
            ->where('document_id', $document->getKey())
            ->latest('id')
            ->first();
    }

    protected function approveDocument(Model $document, User $approver): ApprovalRequest
    {
        $request = $this->approvalRequestFor($document);

        expect($request)->not->toBeNull();

        return app(ApprovalEngine::class)->act($request, $approver, ApprovalActionType::Approve);
    }
}
