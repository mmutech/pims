<?php

namespace App\Providers;

use App\Domain\Administration\Models\DocumentSequence;
use App\Domain\Administration\Models\SystemSetting;
use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemCategory;
use App\Domain\Catalog\Models\UnitOfMeasure;
use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Models\MaterialReturn;
use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockTransaction;
use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Project;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Domain\Receiving\Models\QualityInspection;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Models\SupplierReturn;
use App\Domain\Stocktaking\Models\StockAdjustment;
use App\Domain\Stocktaking\Models\StockCount;
use App\Domain\Stocktaking\Models\StockReconciliation;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseBin;
use App\Domain\Warehouse\Models\WarehouseLocation;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\ApprovalFlow;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Services\ApprovalDocumentRegistry;
use App\Domain\Workflow\Services\ApprovalEngineService;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ApprovalDelegationPolicy;
use App\Policies\ApprovalFlowPolicy;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\BusinessUnitPolicy;
use App\Policies\CostCenterPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DocumentSequencePolicy;
use App\Policies\GoodsReceivedNotePolicy;
use App\Policies\InventoryBalancePolicy;
use App\Policies\ItemCategoryPolicy;
use App\Policies\ItemPolicy;
use App\Policies\MaterialRequisitionPolicy;
use App\Policies\MaterialReturnPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequisitionPolicy;
use App\Policies\QualityInspectionPolicy;
use App\Policies\QuotationEvaluationPolicy;
use App\Policies\RequestForQuotationPolicy;
use App\Policies\RolePolicy;
use App\Policies\StockAdjustmentPolicy;
use App\Policies\StockCountPolicy;
use App\Policies\StockIssuePolicy;
use App\Policies\StockReconciliationPolicy;
use App\Policies\StockTransactionPolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\StoreReceiptAdvicePolicy;
use App\Policies\SupplierPolicy;
use App\Policies\SupplierQuotationPolicy;
use App\Policies\SupplierReturnPolicy;
use App\Policies\SystemSettingPolicy;
use App\Policies\UnitOfMeasurePolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehouseBinPolicy;
use App\Policies\WarehouseLocationPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApprovalDocumentRegistry::class);
        $this->app->bind(ApprovalEngine::class, ApprovalEngineService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory',
        );

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });

        Gate::policy(BusinessUnit::class, BusinessUnitPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(CostCenter::class, CostCenterPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(WarehouseLocation::class, WarehouseLocationPolicy::class);
        Gate::policy(WarehouseBin::class, WarehouseBinPolicy::class);
        Gate::policy(ItemCategory::class, ItemCategoryPolicy::class);
        Gate::policy(UnitOfMeasure::class, UnitOfMeasurePolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseRequisition::class, PurchaseRequisitionPolicy::class);
        Gate::policy(RequestForQuotation::class, RequestForQuotationPolicy::class);
        Gate::policy(SupplierQuotation::class, SupplierQuotationPolicy::class);
        Gate::policy(QuotationEvaluation::class, QuotationEvaluationPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(StoreReceiptAdvice::class, StoreReceiptAdvicePolicy::class);
        Gate::policy(QualityInspection::class, QualityInspectionPolicy::class);
        Gate::policy(GoodsReceivedNote::class, GoodsReceivedNotePolicy::class);
        Gate::policy(SupplierReturn::class, SupplierReturnPolicy::class);
        Gate::policy(InventoryBalance::class, InventoryBalancePolicy::class);
        Gate::policy(StockTransaction::class, StockTransactionPolicy::class);
        Gate::policy(MaterialRequisition::class, MaterialRequisitionPolicy::class);
        Gate::policy(StockIssue::class, StockIssuePolicy::class);
        Gate::policy(StockTransfer::class, StockTransferPolicy::class);
        Gate::policy(MaterialReturn::class, MaterialReturnPolicy::class);
        Gate::policy(StockAdjustment::class, StockAdjustmentPolicy::class);
        Gate::policy(StockCount::class, StockCountPolicy::class);
        Gate::policy(StockReconciliation::class, StockReconciliationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);
        Gate::policy(DocumentSequence::class, DocumentSequencePolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(ApprovalFlow::class, ApprovalFlowPolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
        Gate::policy(ApprovalDelegation::class, ApprovalDelegationPolicy::class);
    }
}
