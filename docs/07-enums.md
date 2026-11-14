# 07 — PHP Enum Definitions

All enums are string-backed, live in `App\Enums`, and use `App\Enums\Concerns\EnumHelpers` (`label()`, `options()`, `values()`). Persisted values are short snake_case strings.

## 1. Identity and organization

```php
enum UserStatus: string {
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}

enum ProjectStatus: string {
    case Planned = 'planned';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
```

## 2. Warehouse

```php
enum WarehouseType: string {
    case Central = 'central';
    case Regional = 'regional';
    case BusinessUnit = 'business_unit';
    case Project = 'project';
    case Transit = 'transit';
    case Quarantine = 'quarantine';
}

enum WarehouseLocationType: string {
    case Storage = 'storage';
    case Receiving = 'receiving';
    case Quarantine = 'quarantine';
    case InTransit = 'in_transit';
    case Damaged = 'damaged';
    case Returns = 'returns';
}

enum WarehouseAccessLevel: string {
    case View = 'view';
    case Operate = 'operate';
    case Approve = 'approve';
    case Manage = 'manage';

    public function rank(): int; // view=1, operate=2, approve=3, manage=4
}
```

## 3. Catalog

```php
enum ItemTrackingType: string {
    case None = 'none';
    case Batch = 'batch';
    case Serial = 'serial';
    case BatchSerial = 'batch_serial';

    public function requiresBatch(): bool;
    public function requiresSerial(): bool;
}

enum ValuationMethod: string {
    case WeightedAverage = 'weighted_average';
    case Fifo = 'fifo';
}

enum UnitOfMeasureType: string {
    case Quantity = 'quantity';
    case Weight = 'weight';
    case Length = 'length';
    case Volume = 'volume';
    case Area = 'area';
    case Time = 'time';
}

enum BatchStatus: string {
    case Active = 'active';
    case Expired = 'expired';
    case Quarantined = 'quarantined';
    case Depleted = 'depleted';
}

enum SerialStatus: string {
    case Available = 'available';
    case Reserved = 'reserved';
    case Issued = 'issued';
    case InTransit = 'in_transit';
    case Returned = 'returned';
    case Damaged = 'damaged';
    case Scrapped = 'scrapped';
}
```

## 4. Supplier

```php
enum SupplierStatus: string {
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Blacklisted = 'blacklisted';
    case Inactive = 'inactive';

    public function canReceiveAwards(): bool; // Active only
}

enum SupplierAddressType: string {
    case Registered = 'registered';
    case Billing = 'billing';
    case Shipping = 'shipping';
    case Warehouse = 'warehouse';
}
```

## 5. Procurement and receiving

```php
enum PurchaseRequisitionStatus: string {
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
}

enum PurchaseOrderStatus: string {
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case PartiallyReceived = 'partially_received';
    case FullyReceived = 'fully_received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}

enum RequestForQuotationStatus: string {
    case Draft = 'draft';
    case Issued = 'issued';
    case Closed = 'closed';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';

    public function isOpen(): bool;              // Issued or Closed
    public function canReceiveQuotations(): bool;
}

enum RfqSupplierStatus: string {
    case Invited = 'invited';
    case Responded = 'responded';
    case Declined = 'declined';
    case NoResponse = 'no_response';
}

enum SupplierQuotationStatus: string {
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}

enum QuotationEvaluationStatus: string {
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
}

enum PurchaseOrderAmendmentStatus: string {
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
}

enum ReceiptStatus: string {
    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case PendingInspection = 'pending_inspection';
    case Inspected = 'inspected';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';
}

enum DeliveryVerificationStatus: string {
    case Pending = 'pending';
    case Verified = 'verified';
    case Discrepancy = 'discrepancy';

    public function isComplete(): bool; // Verified or Discrepancy
}

enum DeliveryCondition: string {
    case Good = 'good';
    case Damaged = 'damaged';
    case Short = 'short';
    case Over = 'over';

    public function isRejected(): bool; // Damaged only
}

enum QualityInspectionStatus: string {
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Passed = 'passed';
    case Failed = 'failed';
    case PartiallyPassed = 'partially_passed';
}

enum InspectionResult: string {
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case PartiallyPassed = 'partially_passed';
}

enum InspectionMethod: string {
    case Visual = 'visual';
    case Dimensional = 'dimensional';
    case Functional = 'functional';
    case Laboratory = 'laboratory';
    case Documentary = 'documentary';
}

enum InspectionFindingType: string {
    case Packaging = 'packaging';
    case Specification = 'specification';
    case Dimension = 'dimension';
    case Functionality = 'functionality';
    case Documentation = 'documentation';
    case Other = 'other';
}

enum InspectionFindingSeverity: string {
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';
}

enum GoodsReceivedNoteStatus: string {
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function isEditable(): bool; // Draft only
    public function isPosted(): bool;
}
```

## 6. Inventory

```php
enum StockMovementType: string {
    case Receipt = 'receipt';
    case Issue = 'issue';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';
    case OpeningBalance = 'opening_balance';
    case ReversalIn = 'reversal_in';
    case ReversalOut = 'reversal_out';

    public function direction(): int; // +1 in, -1 out
}

enum StockTransactionStatus: string {
    case Posted = 'posted';
    case Reversed = 'reversed';
}

enum StockDimensionType: string {
    case Warehouse = 'warehouse';
    case InTransit = 'in_transit';
    case Quarantine = 'quarantine';
    case Damaged = 'damaged';
    case Returns = 'returns';
    case Supplier = 'supplier';

    public function isAvailableForIssue(): bool; // Warehouse only
}

enum StockReservationStatus: string {
    case Active = 'active';
    case PartiallyConsumed = 'partially_consumed';
    case Consumed = 'consumed';
    case Released = 'released';
    case Expired = 'expired';
}
```

## 7. Distribution and control (Phase 6 and Phase 7 enums implemented)

Distribution and inventory-control enums are in `App\Enums`. Requisition, issue, transfer, return, adjustment, count, and variance statuses were documented in advance and are now implemented alongside the domain enums below.

```php
enum RequisitionStatus: string {
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Reserved = 'reserved';
    case PartiallyIssued = 'partially_issued';
    case Issued = 'issued';
    case Acknowledged = 'acknowledged';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}

enum IssueStatus: string {
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';
}

enum TransferStatus: string {
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case PartiallyDispatched = 'partially_dispatched';
    case InTransit = 'in_transit';
    case PartiallyReceived = 'partially_received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

enum ReturnStatus: string {
    case Draft = 'draft';
    case PendingInspection = 'pending_inspection';
    case Assessed = 'assessed';
    case Posted = 'posted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}

enum MaterialBackorderStatus: string {
    case Open = 'open';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function isOpen(): bool;
}

enum TransferDiscrepancyType: string {
    case Short = 'short';
    case Damaged = 'damaged';
    case Missing = 'missing';
    case Over = 'over';

    /** Short and missing leave stock in transit until resolved. */
    public function keepsStockInTransit(): bool;
}

enum TransferDiscrepancyResolution: string {
    case Pending = 'pending';
    case WriteOff = 'write_off';
    case ReturnToSource = 'return_to_source';
    case Claim = 'claim';
    case Replacement = 'replacement';

    public function isPending(): bool;
}

enum MaterialReturnType: string {
    case Project = 'project';
    case Department = 'department';
    case Supplier = 'supplier';

    /** Project and department returns must reference the original issue. */
    public function requiresOriginalIssue(): bool;
}

enum MaterialCondition: string {
    case Good = 'good';
    case Damaged = 'damaged';
    case Defective = 'defective';
    case Quarantined = 'quarantined';
    case Repairable = 'repairable';
    case DisposalPending = 'disposal_pending';

    /** Only good-condition returns may re-enter available stock. */
    public function isAvailableForStock(): bool;

    /** Damaged, defective, quarantined, repairable require a holding dimension. */
    public function requiresQuarantine(): bool;
}

enum ConditionDisposition: string {
    case ReturnToStock = 'return_to_stock';
    case Quarantine = 'quarantine';
    case Repair = 'repair';
    case Dispose = 'dispose';
    case ReturnToSupplier = 'return_to_supplier';

    public function addsStock(): bool;

    /** ReturnToStock → warehouse; Quarantine/Repair → quarantine; Dispose/ReturnToSupplier → none. */
    public function targetDimensionType(): ?StockDimensionType;
}

enum AdjustmentStatus: string {
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Cancelled = 'cancelled';

    public function canBeEdited(): bool;        // draft
    public function canBeSubmitted(): bool;     // draft
    public function canBePosted(): bool;        // draft | approved
    public function canBeReversed(): bool;      // posted
    public function canBeCancelled(): bool;     // draft | pending_approval | approved
    public function isFinal(): bool;            // posted | reversed | cancelled
}

enum AdjustmentType: string {
    case Increase = 'increase';
    case Decrease = 'decrease';

    public function direction(): int;                    // +1 | -1
    public function movementType(): StockMovementType;   // AdjustmentIn | AdjustmentOut
    public static function fromDelta(string $delta): self;
}

enum AdjustmentReason: string {
    case Damage = 'damage';
    case Theft = 'theft';
    case Obsolescence = 'obsolescence';
    case CountCorrection = 'count_correction';
    case Reclassification = 'reclassification';
    case OpeningBalance = 'opening_balance';
    case SystemCorrection = 'system_correction';

    public function label(): string;  // controlled reason-code labels
}

enum CountStatus: string {
    case Draft = 'draft';
    case Counting = 'counting';
    case Recount = 'recount';
    case VarianceReview = 'variance_review';
    case AdjustmentPending = 'adjustment_pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canBeStarted(): bool;    // draft
    public function canBeCounted(): bool;    // counting | recount | variance_review
    public function canBeAnalysed(): bool;   // counting | recount | variance_review
    public function canBeCancelled(): bool;  // not completed/cancelled
    public function isOpen(): bool;
}

enum CountType: string {
    case Full = 'full';
    case Cycle = 'cycle';
    case Blind = 'blind';

    public function isBlind(): bool;
    /** Blind counts must never expose system quantities to the counter. */
    public function hidesSystemQuantity(): bool;
}

enum VarianceType: string {
    case Gain = 'gain';
    case Loss = 'loss';
    case Misplacement = 'misplacement';
    case RecordingError = 'recording_error';

    public static function fromDelta(string $delta): self;
    public function isGain(): bool;
    public function direction(): int;  // +1 | -1
}

enum VarianceResolution: string {
    case Pending = 'pending';
    case Adjusted = 'adjusted';
    case Investigated = 'investigated';
    case Accepted = 'accepted';

    public function isOpen(): bool;   // pending | investigated
    public function isFinal(): bool;  // adjusted | accepted
}

enum ReconciliationStatus: string {
    case Draft = 'draft';
    case Approved = 'approved';
    case Posted = 'posted';

    public function canBeApproved(): bool;  // draft
    public function canBePosted(): bool;    // approved
}
```

### 7.1 Phase 7 document types

`DocumentType` adds `stock_adjustment` (prefix `ADJ`), `stock_count` (prefix `CNT`), and `stock_reconciliation` (prefix `REC`). The `stock_adjustment` document type is registered in `config/approvals.php`; reconciliation is approved directly by its service/policy rather than through a configured approval flow.

## 8. Approval workflow

```php
enum ApprovalStatus: string {
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}

enum ApprovalActionType: string {
    case Approve = 'approve';
    case Reject = 'reject';
    case Return = 'return';
    case Delegate = 'delegate';
    case Escalate = 'escalate';
    case Cancel = 'cancel';
}

enum ApprovalMode: string {
    case Sequential = 'sequential';
    case Parallel = 'parallel';
    case AnyOne = 'any_one';
    case All = 'all';
}
```

## 9. Documents

```php
enum DocumentType: string {
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
    case StockAdjustment = 'stock_adjustment';
    case StockCount = 'stock_count';
    case MaterialReturn = 'material_return';
    case SupplierReturn = 'supplier_return';
    case StockTransaction = 'stock_transaction';
    case StockReservation = 'stock_reservation';

    public function prefix(): string; // PR, RFQ, QT, QE, PO, SRA, DV, QI, GRN, MR, SIV, TRF, ADJ, CNT, RET, SRT, STX, RES
}
```

## 10. Helper trait

```php
namespace App\Enums\Concerns;

use Illuminate\Support\Str;

trait EnumHelpers
{
    public function label(): string
    {
        return Str::headline($this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

## 11. Persistence rules

- Enums are stored as `varchar(30)` (or `varchar(40)` for document types).
- Casts use `'status' => ProjectStatus::class`.
- Validation uses `Rule::enum(ProjectStatus::class)`.
- Adding a case is backward compatible; removing/renaming a case requires a data migration.
- Database `CHECK` constraints are generated from `values()` for stable enums in Phase 5+.

## 12. Reporting

Phase 8 introduces no new backed enums. Report identity is a stable string key (`inventory.valuation`, `procurement.orders`, `warehouse.throughput`, and so on) declared in `App\Domain\Reporting\Support\ReportCatalogue`. Export formats are constrained by the route (`xlsx|csv|pdf`) and by the controller's explicit allow-list rather than an enum. Snapshot dates are dates, not enum states; report rows always carry their current status label (for example `ApprovalStatus::label()`) so historical exports remain readable even if a status is renamed later.
