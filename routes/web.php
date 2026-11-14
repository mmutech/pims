<?php

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
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Receiving\Models\GoodsReceivedNote;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Models\SupplierReturn;
use App\Domain\Stocktaking\Models\StockAdjustment;
use App\Domain\Stocktaking\Models\StockCount;
use App\Domain\Stocktaking\Models\StockReconciliation;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\ApprovalFlow;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Http\Controllers\Reports\ReportExportController;
use App\Livewire\Actions\Logout;
use App\Livewire\Administration\Activity\Index as ActivityIndex;
use App\Livewire\Administration\Roles\Index as RolesIndex;
use App\Livewire\Administration\Sequences\Index as SequencesIndex;
use App\Livewire\Administration\Settings\Index as SettingsIndex;
use App\Livewire\Administration\Users\Index as UsersIndex;
use App\Livewire\Administration\Users\Show as UsersShow;
use App\Livewire\Approvals\History as ApprovalsHistory;
use App\Livewire\Approvals\Index as ApprovalsIndex;
use App\Livewire\Approvals\Show as ApprovalsShow;
use App\Livewire\Catalog\Categories\Index as CategoriesIndex;
use App\Livewire\Catalog\Items\Index as ItemsIndex;
use App\Livewire\Catalog\Items\Show as ItemsShow;
use App\Livewire\Catalog\Units\Index as UnitsIndex;
use App\Livewire\Dashboard;
use App\Livewire\Delegations\Index as DelegationsIndex;
use App\Livewire\Distribution\Issues\Form as DistributionIssuesForm;
use App\Livewire\Distribution\Issues\Index as DistributionIssuesIndex;
use App\Livewire\Distribution\Issues\Show as DistributionIssuesShow;
use App\Livewire\Distribution\ProjectConsumption\Index as ProjectConsumptionIndex;
use App\Livewire\Distribution\Requisitions\Form as DistributionRequisitionsForm;
use App\Livewire\Distribution\Requisitions\Index as DistributionRequisitionsIndex;
use App\Livewire\Distribution\Requisitions\Show as DistributionRequisitionsShow;
use App\Livewire\Distribution\Returns\Form as DistributionReturnsForm;
use App\Livewire\Distribution\Returns\Index as DistributionReturnsIndex;
use App\Livewire\Distribution\Returns\Show as DistributionReturnsShow;
use App\Livewire\Distribution\Transfers\Form as DistributionTransfersForm;
use App\Livewire\Distribution\Transfers\Index as DistributionTransfersIndex;
use App\Livewire\Distribution\Transfers\Show as DistributionTransfersShow;
use App\Livewire\Inventory\Balances\Index as InventoryBalancesIndex;
use App\Livewire\Inventory\Transactions\Index as InventoryTransactionsIndex;
use App\Livewire\Organization\BusinessUnits\Index as BusinessUnitsIndex;
use App\Livewire\Organization\CostCenters\Index as CostCentersIndex;
use App\Livewire\Organization\Departments\Index as DepartmentsIndex;
use App\Livewire\Organization\Projects\Index as ProjectsIndex;
use App\Livewire\Procurement\Evaluations\Show as EvaluationsShow;
use App\Livewire\Procurement\Orders\Form as OrdersForm;
use App\Livewire\Procurement\Orders\Index as OrdersIndex;
use App\Livewire\Procurement\Orders\Show as OrdersShow;
use App\Livewire\Procurement\Quotations\Form as QuotationsForm;
use App\Livewire\Procurement\Quotations\Index as QuotationsIndex;
use App\Livewire\Procurement\Quotations\Show as QuotationsShow;
use App\Livewire\Procurement\Requisitions\Form as RequisitionsForm;
use App\Livewire\Procurement\Requisitions\Index as RequisitionsIndex;
use App\Livewire\Procurement\Requisitions\Show as RequisitionsShow;
use App\Livewire\Procurement\Rfqs\Form as RfqsForm;
use App\Livewire\Procurement\Rfqs\Index as RfqsIndex;
use App\Livewire\Procurement\Rfqs\Show as RfqsShow;
use App\Livewire\Receiving\Grn\Index as GrnIndex;
use App\Livewire\Receiving\Grn\Show as GrnShow;
use App\Livewire\Receiving\Returns\Form as ReturnsForm;
use App\Livewire\Receiving\Returns\Index as ReturnsIndex;
use App\Livewire\Receiving\Returns\Show as ReturnsShow;
use App\Livewire\Receiving\Sra\Form as SraForm;
use App\Livewire\Receiving\Sra\Index as SraIndex;
use App\Livewire\Receiving\Sra\Show as SraShow;
use App\Livewire\Reports\Audit as ReportsAudit;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\Inventory as ReportsInventory;
use App\Livewire\Reports\Procurement as ReportsProcurement;
use App\Livewire\Reports\Projects as ReportsProjects;
use App\Livewire\Reports\Warehouses as ReportsWarehouses;
use App\Livewire\Stocktaking\Adjustments\Form as StocktakingAdjustmentsForm;
use App\Livewire\Stocktaking\Adjustments\Index as StocktakingAdjustmentsIndex;
use App\Livewire\Stocktaking\Adjustments\Show as StocktakingAdjustmentsShow;
use App\Livewire\Stocktaking\Counts\Form as StocktakingCountsForm;
use App\Livewire\Stocktaking\Counts\Index as StocktakingCountsIndex;
use App\Livewire\Stocktaking\Counts\Show as StocktakingCountsShow;
use App\Livewire\Stocktaking\Reconciliations\Index as StocktakingReconciliationsIndex;
use App\Livewire\Suppliers\Index as SuppliersIndex;
use App\Livewire\Suppliers\Show as SuppliersShow;
use App\Livewire\Warehouses\Index as WarehousesIndex;
use App\Livewire\Warehouses\Show as WarehousesShow;
use App\Livewire\Workflows\Form as WorkflowsForm;
use App\Livewire\Workflows\Index as WorkflowsIndex;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

// Route::view('/', 'welcome');

Route::post('logout', function (Logout $logout) {
    $logout();

    return redirect('/');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', Dashboard::class)
        ->middleware('can:'.Permissions::DASHBOARD_VIEW)
        ->name('dashboard');

    Route::view('profile', 'profile')
        ->name('profile');

    Route::prefix('organization')->name('organization.')->group(function (): void {
        Route::get('business-units', BusinessUnitsIndex::class)
            ->middleware('can:viewAny,'.BusinessUnit::class)
            ->name('business-units.index');

        Route::get('departments', DepartmentsIndex::class)
            ->middleware('can:viewAny,'.Department::class)
            ->name('departments.index');

        Route::get('cost-centers', CostCentersIndex::class)
            ->middleware('can:viewAny,'.CostCenter::class)
            ->name('cost-centers.index');

        Route::get('projects', ProjectsIndex::class)
            ->middleware('can:viewAny,'.Project::class)
            ->name('projects.index');
    });

    Route::get('warehouses', WarehousesIndex::class)
        ->middleware('can:viewAny,'.Warehouse::class)
        ->name('warehouses.index');

    Route::get('warehouses/{warehouse}', WarehousesShow::class)
        ->middleware('can:view,warehouse')
        ->name('warehouses.show');

    Route::prefix('catalog')->name('catalog.')->group(function (): void {
        Route::get('items', ItemsIndex::class)
            ->middleware('can:viewAny,'.Item::class)
            ->name('items.index');

        Route::get('items/{item}', ItemsShow::class)
            ->middleware('can:view,item')
            ->name('items.show');

        Route::get('categories', CategoriesIndex::class)
            ->middleware('can:viewAny,'.ItemCategory::class)
            ->name('categories.index');

        Route::get('units', UnitsIndex::class)
            ->middleware('can:viewAny,'.UnitOfMeasure::class)
            ->name('units.index');
    });

    Route::get('suppliers', SuppliersIndex::class)
        ->middleware('can:viewAny,'.Supplier::class)
        ->name('suppliers.index');

    Route::get('suppliers/{supplier}', SuppliersShow::class)
        ->middleware('can:view,supplier')
        ->name('suppliers.show');

    Route::prefix('procurement')->name('procurement.')->group(function (): void {
        Route::prefix('requisitions')->name('requisitions.')->group(function (): void {
            Route::get('/', RequisitionsIndex::class)
                ->middleware('can:viewAny,'.PurchaseRequisition::class)
                ->name('index');

            Route::get('create', RequisitionsForm::class)
                ->middleware('can:create,'.PurchaseRequisition::class)
                ->name('create');

            Route::get('{requisition}/edit', RequisitionsForm::class)
                ->middleware('can:update,requisition')
                ->name('edit');

            Route::get('{requisition}', RequisitionsShow::class)
                ->middleware('can:view,requisition')
                ->name('show');
        });

        Route::prefix('rfqs')->name('rfqs.')->group(function (): void {
            Route::get('/', RfqsIndex::class)
                ->middleware('can:viewAny,'.RequestForQuotation::class)
                ->name('index');

            Route::get('create', RfqsForm::class)
                ->middleware('can:create,'.RequestForQuotation::class)
                ->name('create');

            Route::get('{rfq}/edit', RfqsForm::class)
                ->middleware('can:update,rfq')
                ->name('edit');

            Route::get('{rfq}', RfqsShow::class)
                ->middleware('can:view,rfq')
                ->name('show');
        });

        Route::prefix('quotations')->name('quotations.')->group(function (): void {
            Route::get('/', QuotationsIndex::class)
                ->middleware('can:viewAny,'.SupplierQuotation::class)
                ->name('index');

            Route::get('create', QuotationsForm::class)
                ->middleware('can:create,'.SupplierQuotation::class)
                ->name('create');

            Route::get('{quotation}/edit', QuotationsForm::class)
                ->middleware('can:update,quotation')
                ->name('edit');

            Route::get('{quotation}', QuotationsShow::class)
                ->middleware('can:view,quotation')
                ->name('show');
        });

        Route::get('evaluations/{evaluation}', EvaluationsShow::class)
            ->middleware('can:view,evaluation')
            ->name('evaluations.show');

        Route::prefix('orders')->name('orders.')->group(function (): void {
            Route::get('/', OrdersIndex::class)
                ->middleware('can:viewAny,'.PurchaseOrder::class)
                ->name('index');

            Route::get('create', OrdersForm::class)
                ->middleware('can:create,'.PurchaseOrder::class)
                ->name('create');

            Route::get('{order}/edit', OrdersForm::class)
                ->middleware('can:update,order')
                ->name('edit');

            Route::get('{order}', OrdersShow::class)
                ->middleware('can:view,order')
                ->name('show');
        });
    });

    Route::prefix('receiving')->name('receiving.')->group(function (): void {
        Route::prefix('sra')->name('sra.')->group(function (): void {
            Route::get('/', SraIndex::class)
                ->middleware('can:viewAny,'.StoreReceiptAdvice::class)
                ->name('index');

            Route::get('create', SraForm::class)
                ->middleware('can:create,'.StoreReceiptAdvice::class)
                ->name('create');

            Route::get('{sra}/edit', SraForm::class)
                ->middleware('can:update,sra')
                ->name('edit');

            Route::get('{sra}', SraShow::class)
                ->middleware('can:view,sra')
                ->name('show');
        });

        Route::prefix('grn')->name('grn.')->group(function (): void {
            Route::get('/', GrnIndex::class)
                ->middleware('can:viewAny,'.GoodsReceivedNote::class)
                ->name('index');

            Route::get('{grn}', GrnShow::class)
                ->middleware('can:view,grn')
                ->name('show');
        });

        Route::prefix('returns')->name('returns.')->group(function (): void {
            Route::get('/', ReturnsIndex::class)
                ->middleware('can:viewAny,'.SupplierReturn::class)
                ->name('index');

            Route::get('create', ReturnsForm::class)
                ->middleware('can:create,'.SupplierReturn::class)
                ->name('create');

            Route::get('{supplierReturn}/edit', ReturnsForm::class)
                ->middleware('can:update,supplierReturn')
                ->name('edit');

            Route::get('{supplierReturn}', ReturnsShow::class)
                ->middleware('can:view,supplierReturn')
                ->name('show');
        });
    });

    Route::prefix('inventory')->name('inventory.')->group(function (): void {
        Route::get('balances', InventoryBalancesIndex::class)
            ->middleware('can:viewAny,'.InventoryBalance::class)
            ->name('balances.index');

        Route::get('transactions', InventoryTransactionsIndex::class)
            ->middleware('can:viewAny,'.StockTransaction::class)
            ->name('transactions.index');
    });

    Route::prefix('distribution')->name('distribution.')->group(function (): void {
        Route::prefix('requisitions')->name('requisitions.')->group(function (): void {
            Route::get('/', DistributionRequisitionsIndex::class)
                ->middleware('can:viewAny,'.MaterialRequisition::class)
                ->name('index');

            Route::get('create', DistributionRequisitionsForm::class)
                ->middleware('can:create,'.MaterialRequisition::class)
                ->name('create');

            Route::get('{requisition}/edit', DistributionRequisitionsForm::class)
                ->middleware('can:update,requisition')
                ->name('edit');

            Route::get('{requisition}', DistributionRequisitionsShow::class)
                ->middleware('can:view,requisition')
                ->name('show');
        });

        Route::prefix('issues')->name('issues.')->group(function (): void {
            Route::get('/', DistributionIssuesIndex::class)
                ->middleware('can:viewAny,'.StockIssue::class)
                ->name('index');

            Route::get('create', DistributionIssuesForm::class)
                ->middleware('can:create,'.StockIssue::class)
                ->name('create');

            Route::get('{issue}', DistributionIssuesShow::class)
                ->middleware('can:view,issue')
                ->name('show');
        });

        Route::prefix('transfers')->name('transfers.')->group(function (): void {
            Route::get('/', DistributionTransfersIndex::class)
                ->middleware('can:viewAny,'.StockTransfer::class)
                ->name('index');

            Route::get('create', DistributionTransfersForm::class)
                ->middleware('can:create,'.StockTransfer::class)
                ->name('create');

            Route::get('{transfer}/edit', DistributionTransfersForm::class)
                ->middleware('can:update,transfer')
                ->name('edit');

            Route::get('{transfer}', DistributionTransfersShow::class)
                ->middleware('can:view,transfer')
                ->name('show');
        });

        Route::prefix('returns')->name('returns.')->group(function (): void {
            Route::get('/', DistributionReturnsIndex::class)
                ->middleware('can:viewAny,'.MaterialReturn::class)
                ->name('index');

            Route::get('create', DistributionReturnsForm::class)
                ->middleware('can:create,'.MaterialReturn::class)
                ->name('create');

            Route::get('{return}/edit', DistributionReturnsForm::class)
                ->middleware('can:update,return')
                ->name('edit');

            Route::get('{materialReturn}', DistributionReturnsShow::class)
                ->middleware('can:view,materialReturn')
                ->name('show');
        });

        Route::get('project-consumption', ProjectConsumptionIndex::class)
            ->middleware('can:'.Permissions::DISTRIBUTION_PROJECT_CONSUMPTION_VIEW)
            ->name('project-consumption.index');
    });

    Route::prefix('stocktaking')->name('stocktaking.')->group(function (): void {
        Route::prefix('adjustments')->name('adjustments.')->group(function (): void {
            Route::get('/', StocktakingAdjustmentsIndex::class)
                ->middleware('can:viewAny,'.StockAdjustment::class)
                ->name('index');

            Route::get('create', StocktakingAdjustmentsForm::class)
                ->middleware('can:create,'.StockAdjustment::class)
                ->name('create');

            Route::get('{adjustment}/edit', StocktakingAdjustmentsForm::class)
                ->middleware('can:update,adjustment')
                ->name('edit');

            Route::get('{adjustment}', StocktakingAdjustmentsShow::class)
                ->middleware('can:view,adjustment')
                ->name('show');
        });

        Route::prefix('counts')->name('counts.')->group(function (): void {
            Route::get('/', StocktakingCountsIndex::class)
                ->middleware('can:viewAny,'.StockCount::class)
                ->name('index');

            Route::get('create', StocktakingCountsForm::class)
                ->middleware('can:create,'.StockCount::class)
                ->name('create');

            Route::get('{count}/edit', StocktakingCountsForm::class)
                ->middleware('can:update,count')
                ->name('edit');

            Route::get('{count}', StocktakingCountsShow::class)
                ->middleware('can:view,count')
                ->name('show');
        });

        Route::get('reconciliations', StocktakingReconciliationsIndex::class)
            ->middleware('can:viewAny,'.StockReconciliation::class)
            ->name('reconciliations.index');
    });

    Route::get('approvals', ApprovalsIndex::class)
        ->middleware('can:viewAny,'.ApprovalRequest::class)
        ->name('approvals.index');

    Route::get('approvals/history', ApprovalsHistory::class)
        ->middleware('can:viewAny,'.ApprovalRequest::class)
        ->name('approvals.history');

    Route::get('approvals/{approval}', ApprovalsShow::class)
        ->middleware('can:view,approval')
        ->name('approvals.show');

    Route::get('delegations', DelegationsIndex::class)
        ->middleware('can:viewAny,'.ApprovalDelegation::class)
        ->name('delegations.index');

    Route::prefix('workflows')->name('workflows.')->group(function (): void {
        Route::get('/', WorkflowsIndex::class)
            ->middleware('can:viewAny,'.ApprovalFlow::class)
            ->name('index');

        Route::get('create', WorkflowsForm::class)
            ->middleware('can:create,'.ApprovalFlow::class)
            ->name('create');

        Route::get('{flow}/edit', WorkflowsForm::class)
            ->middleware('can:update,flow')
            ->name('edit');
    });

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/', ReportsIndex::class)->name('index');
        Route::get('inventory', ReportsInventory::class)->name('inventory');
        Route::get('procurement', ReportsProcurement::class)->name('procurement');
        Route::get('warehouses', ReportsWarehouses::class)->name('warehouses');
        Route::get('projects', ReportsProjects::class)->name('projects');
        Route::get('audit', ReportsAudit::class)->name('audit');
        Route::get('export/{report}/{format}', ReportExportController::class)
            ->where('report', '[a-z_.]+')
            ->where('format', 'xlsx|csv|pdf')
            ->name('export');
    });

    Route::prefix('administration')->name('administration.')->group(function (): void {
        Route::get('users', UsersIndex::class)
            ->middleware('can:viewAny,'.User::class)
            ->name('users.index');

        Route::get('users/{user}', UsersShow::class)
            ->middleware('can:view,user')
            ->name('users.show');

        Route::get('roles', RolesIndex::class)
            ->middleware('can:viewAny,'.Role::class)
            ->name('roles.index');

        Route::get('sequences', SequencesIndex::class)
            ->middleware('can:viewAny,'.DocumentSequence::class)
            ->name('sequences.index');

        Route::get('settings', SettingsIndex::class)
            ->middleware('can:viewAny,'.SystemSetting::class)
            ->name('settings.index');

        Route::get('activity', ActivityIndex::class)
            ->middleware('can:viewAny,'.Activity::class)
            ->name('activity.index');
    });
});

require __DIR__.'/auth.php';
