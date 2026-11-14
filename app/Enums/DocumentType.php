<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DocumentType: string
{
    use EnumHelpers;

    case PurchaseRequisition = 'purchase_requisition';
    case RequestForQuotation = 'request_for_quotation';
    case SupplierQuotation = 'supplier_quotation';
    case QuotationEvaluation = 'quotation_evaluation';
    case PurchaseOrder = 'purchase_order';
    case StoreReceiptAdvice = 'store_receipt_advice';
    case DeliveryVerification = 'delivery_verification';
    case QualityInspection = 'quality_inspection';
    case GoodsReceivedNote = 'goods_received_note';
    case MaterialRequisition = 'material_requisition';
    case StockIssue = 'stock_issue';
    case StockTransfer = 'stock_transfer';
    case StockTransferDispatch = 'stock_transfer_dispatch';
    case StockTransferReceipt = 'stock_transfer_receipt';
    case StockAdjustment = 'stock_adjustment';
    case StockCount = 'stock_count';
    case StockReconciliation = 'stock_reconciliation';
    case MaterialReturn = 'material_return';
    case SupplierReturn = 'supplier_return';
    case StockTransaction = 'stock_transaction';
    case StockReservation = 'stock_reservation';

    public function prefix(): string
    {
        return match ($this) {
            self::PurchaseRequisition => 'PR',
            self::RequestForQuotation => 'RFQ',
            self::SupplierQuotation => 'QT',
            self::QuotationEvaluation => 'QE',
            self::PurchaseOrder => 'PO',
            self::StoreReceiptAdvice => 'SRA',
            self::DeliveryVerification => 'DV',
            self::QualityInspection => 'QI',
            self::GoodsReceivedNote => 'GRN',
            self::MaterialRequisition => 'MR',
            self::StockIssue => 'SIV',
            self::StockTransfer => 'TRF',
            self::StockTransferDispatch => 'TRD',
            self::StockTransferReceipt => 'TRR',
            self::StockAdjustment => 'ADJ',
            self::StockCount => 'CNT',
            self::StockReconciliation => 'REC',
            self::MaterialReturn => 'RET',
            self::SupplierReturn => 'SRT',
            self::StockTransaction => 'STX',
            self::StockReservation => 'RES',
        };
    }
}
