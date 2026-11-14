<?php

use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Models\StockTransfer;
use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Procurement\Models\PurchaseOrderAmendment;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\QuotationEvaluation;
use App\Domain\Stocktaking\Models\StockAdjustment;

return [
    /*
    |--------------------------------------------------------------------------
    | Approvable documents
    |--------------------------------------------------------------------------
    |
    | Map logical document types to the Eloquent model that implements
    | App\Domain\Workflow\Contracts\Approvable. Modules register their models
    | here as they are introduced in later phases.
    |
    */
    'documents' => [
        'purchase_requisition' => PurchaseRequisition::class,
        'purchase_order' => PurchaseOrder::class,
        'quotation_evaluation' => QuotationEvaluation::class,
        'purchase_order_amendment' => PurchaseOrderAmendment::class,
        'material_requisition' => MaterialRequisition::class,
        'stock_transfer' => StockTransfer::class,
        'stock_adjustment' => StockAdjustment::class,
    ],
];
