# 06 — Eloquent Model Relationship Specification

## 1. Conventions

- Domain models live under `App\Domain\<Domain>\Models`.
- All models use `HasFactory` where factories exist, `SoftDeletes` for master data, and `LogsActivity` for audited aggregates.
- Casts: `decimal:4` for quantities, `decimal:2` for money, `decimal:6` for unit costs/conversions, `date`/`datetime`, and the relevant PHP enum.
- `$fillable` lists only mass-assignable fields; document numbers, statuses, and audit actors are set by services.
- Relationship methods are typed and return `BelongsTo`, `HasMany`, `BelongsToMany`, `HasManyThrough`, or `MorphMany`.
- Scopes: `scopeActive()`, `scopeSearch($term)`, `scopeVisibleTo(User $user)` where applicable.

## 2. Phase 1 models

### Organization

| Model | Relationships | Casts / traits |
|-------|---------------|----------------|
| `BusinessUnit` | `hasMany(Warehouse)` | SoftDeletes, LogsActivity |
| `Department` | `hasMany(CostCenter)`, `hasMany(Project)`, `hasMany(User)` | SoftDeletes, LogsActivity |
| `CostCenter` | `belongsTo(Department)`, `hasMany(Project)`, `hasMany(User)` | SoftDeletes, LogsActivity |
| `Project` | `belongsTo(Department)`, `belongsTo(CostCenter)`, `belongsTo(User,'manager_id')`, `hasMany(ProjectMember)`, `belongsToMany(User,'project_members')->withPivot('role','joined_at','left_at','is_active')` | `status` → `ProjectStatus`, `start_date`/`end_date` date, `budget_amount` decimal:2, SoftDeletes, LogsActivity |
| `ProjectMember` | `belongsTo(Project)`, `belongsTo(User)` | `joined_at`/`left_at` date, `is_active` bool |

`User` adds:

| Relationship | Definition |
|--------------|------------|
| `department()` | `belongsTo(Department)` |
| `costCenter()` | `belongsTo(CostCenter)` |
| `projectMemberships()` | `hasMany(ProjectMember)` |
| `projects()` | `belongsToMany(Project,'project_members')->withPivot(...)` |
| `warehouseAccess()` | `hasMany(UserWarehouseAccess)` |
| `warehouses()` | `belongsToMany(Warehouse,'user_warehouse_access')->withPivot('access_level','is_primary','granted_at','revoked_at')` |

User helpers:

```php
public function isSuperAdmin(): bool;                 // hasRole('Super Administrator')
public function hasGlobalAccess(): bool;              // permission system.access.all
public function hasWarehouseAccess(Warehouse $w, WarehouseAccessLevel $min = View): bool;
public function canAccessProject(Project $p): bool;   // global, manager, or active member
```

### Warehouse

| Model | Relationships | Casts / traits |
|-------|---------------|----------------|
| `Warehouse` | `belongsTo(BusinessUnit)`, `belongsTo(User,'manager_id')`, `hasMany(WarehouseLocation)`, `hasManyThrough(WarehouseBin,WarehouseLocation)`, `hasMany(UserWarehouseAccess)`, `belongsToMany(User,'user_warehouse_access')` | `type` → `WarehouseType`, `is_active` bool, SoftDeletes, LogsActivity |
| `WarehouseLocation` | `belongsTo(Warehouse)`, `hasMany(WarehouseBin)` | `type` → `WarehouseLocationType`, SoftDeletes |
| `WarehouseBin` | `belongsTo(WarehouseLocation,'warehouse_location_id')` | `max_capacity` decimal:4, SoftDeletes |
| `UserWarehouseAccess` | `belongsTo(User)`, `belongsTo(Warehouse)`, `belongsTo(User,'granted_by')` | `access_level` → `WarehouseAccessLevel`, `granted_at`/`revoked_at` datetime |

### Catalog

| Model | Relationships | Casts / traits |
|-------|---------------|----------------|
| `ItemCategory` | `belongsTo(ItemCategory,'parent_id')`, `hasMany(ItemCategory,'parent_id')`, `hasMany(Item)` | booleans, SoftDeletes, LogsActivity |
| `UnitOfMeasure` | `hasMany(Item,'base_unit_of_measure_id')`, `hasMany(ItemUnit)` | `type` → `UnitOfMeasureType`, SoftDeletes |
| `Item` | `belongsTo(ItemCategory)`, `belongsTo(UnitOfMeasure,'base_unit_of_measure_id')`, `hasMany(ItemUnit)`, `hasMany(ItemSpecification)`, `hasMany(ItemBatch)`, `hasMany(ItemSerialNumber)` | `tracking_type` → `ItemTrackingType`, `valuation_method` → `ValuationMethod`, decimals, SoftDeletes, LogsActivity |
| `ItemUnit` | `belongsTo(Item)`, `belongsTo(UnitOfMeasure)` | `conversion_factor` decimal:6 |
| `ItemSpecification` | `belongsTo(Item)`, `belongsTo(UnitOfMeasure)` | |
| `ItemBatch` | `belongsTo(Item)`, `belongsTo(Supplier)`, `hasMany(ItemSerialNumber)` | `status` → `BatchStatus`, dates |
| `ItemSerialNumber` | `belongsTo(Item)`, `belongsTo(ItemBatch,'batch_id')`, `belongsTo(Warehouse,'current_warehouse_id')` | `status` → `SerialStatus` |

### Supplier

| Model | Relationships | Casts / traits |
|-------|---------------|----------------|
| `Supplier` | `hasMany(SupplierContact)`, `hasMany(SupplierAddress)`, `hasMany(SupplierDocument)`, `hasMany(SupplierEvaluation)`, `belongsTo(User,'approved_by')`, `hasMany(ItemBatch)` | `status` → `SupplierStatus`, `is_approved` bool, `approved_at` datetime, `rating` decimal:2, SoftDeletes, LogsActivity |
| `SupplierContact` | `belongsTo(Supplier)` | `is_primary` bool |
| `SupplierAddress` | `belongsTo(Supplier)` | `type` → `SupplierAddressType` |
| `SupplierDocument` | `belongsTo(Supplier)`, `belongsTo(User,'uploaded_by')` | `expires_at` date |
| `SupplierEvaluation` | `belongsTo(Supplier)`, `belongsTo(User,'evaluator_id')` | scores decimal:2, `evaluation_date` date |

### Support

| Model | Relationships | Casts |
|-------|---------------|-------|
| `DocumentSequence` | none | `year` int, `next_number` int, `padding` int |
| `SystemSetting` | none | `is_encrypted` bool; `value` encrypted cast when flagged (service-level) |

### Procurement (Phase 3, implemented)

| Model | Relationships | Casts / traits |
|-------|---------------|----------------|
| `PurchaseRequisition` | `belongsTo` Department, BusinessUnit, CostCenter, Project, User (`requested_by`), Supplier (`suggested_supplier_id`); `hasMany(PurchaseRequisitionItem)`; `hasMany(RequestForQuotation)`; `hasMany(PurchaseOrder)` | `status` → `PurchaseRequisitionStatus`, amounts decimal:2, dates, `ApprovalRequest` via `Approvable`, SoftDeletes, LogsActivity |
| `PurchaseRequisitionItem` | `belongsTo(PurchaseRequisition)`, `belongsTo(Item)`, `belongsTo(UnitOfMeasure)`, `hasMany(RfqItem)` | quantities/prices decimal:4, `estimated_line_total` decimal:2; `remainingQuantity()` |
| `RequestForQuotation` | `belongsTo(PurchaseRequisition)`, `belongsTo(BusinessUnit)`, `belongsTo(User,'created_by')`; `hasMany(RfqItem)`, `hasMany(RfqSupplier)`, `hasMany(SupplierQuotation)`, `hasMany(QuotationEvaluation)`; `scopeVisibleTo`, `scopeSearch` | `status` → `RequestForQuotationStatus`, dates, SoftDeletes, LogsActivity |
| `RfqItem` | `belongsTo(RequestForQuotation)`, `belongsTo(PurchaseRequisitionItem)`, `belongsTo(Item)`, `belongsTo(UnitOfMeasure)` | `quantity` decimal:4 |
| `RfqSupplier` | `belongsTo(RequestForQuotation)`, `belongsTo(Supplier)` | `status` → `RfqSupplierStatus`, timestamps |
| `SupplierQuotation` | `belongsTo(RequestForQuotation)`, `belongsTo(Supplier)`, `belongsTo(User,'created_by')`; `hasMany(SupplierQuotationItem)`; `hasOne(QuotationEvaluation,'recommended_quotation_id')`; visibility/search scopes | `status` → `SupplierQuotationStatus`, amounts decimal:2, SoftDeletes, LogsActivity |
| `SupplierQuotationItem` | `belongsTo(SupplierQuotation)`, `belongsTo(RfqItem)`, `belongsTo(Item)`, `belongsTo(UnitOfMeasure)` | quantities/prices decimal:4, line totals decimal:2 |
| `QuotationEvaluation` | `belongsTo(RequestForQuotation)`, `belongsTo(User,'evaluated_by')`, `belongsTo(SupplierQuotation,'recommended_quotation_id')`; `hasMany(QuotationEvaluationScore)` | `status` → `QuotationEvaluationStatus`, `ApprovalRequest` via `Approvable`, LogsActivity |
| `QuotationEvaluationScore` | `belongsTo(QuotationEvaluation)`, `belongsTo(SupplierQuotation)` | weight/score/weighted_score decimal:2 |
| `PurchaseOrder` | `belongsTo(Supplier)`, `belongsTo(Warehouse,'delivery_warehouse_id')`, `belongsTo(PurchaseRequisition)`, `belongsTo(SupplierQuotation)`; `hasMany(PurchaseOrderItem)`, `hasMany(PurchaseOrderAmendment)`; visibility/search scopes | `status` → `PurchaseOrderStatus`, amounts decimal:2, `version` int, SoftDeletes, LogsActivity, `ApprovalRequest` via `Approvable` |
| `PurchaseOrderItem` | `belongsTo(PurchaseOrder)`, `belongsTo(Item)`, `belongsTo(UnitOfMeasure)`, `belongsTo(CostCenter)`, `belongsTo(Project)`, `belongsTo(PurchaseRequisitionItem)` | quantities/prices decimal:4, `line_total` decimal:2 |
| `PurchaseOrderAmendment` | `belongsTo(PurchaseOrder)`, `belongsTo(User,'requested_by')`, `hasMany(PurchaseOrderAmendmentItem)` | `status` → `PurchaseOrderAmendmentStatus`, `ApprovalRequest` via `Approvable`, LogsActivity |
| `PurchaseOrderAmendmentItem` | `belongsTo(PurchaseOrderAmendment)`, `belongsTo(PurchaseOrderItem)` | original/amended quantities and prices decimal:4, line totals decimal:2 |

## 2A. Phase 4/5 models (implemented)

### Receiving (`App\Domain\Receiving\Models`)

| Model | Table | Key relationships | Casts / traits |
|-------|-------|-------------------|----------------|
| `StoreReceiptAdvice` | `store_receipt_advices` | belongsTo PurchaseOrder, Warehouse, Supplier, receiver(User); hasMany items, inspections, goodsReceivedNotes; hasOne verification | `status` ReceiptStatus, `verified_at`/`cancelled_at` datetime, SoftDeletes, RecordsActivity; explicit `$table` |
| `StoreReceiptAdviceItem` | `store_receipt_advice_items` | belongsTo SRA (`sra_id`), PurchaseOrderItem, Item, UnitOfMeasure | quantities `decimal:4`, `serial_numbers` array |
| `DeliveryVerification` | `delivery_verifications` | belongsTo SRA, Warehouse, verifiedBy(User); hasMany items | `status` DeliveryVerificationStatus, `verified_at` datetime |
| `DeliveryVerificationItem` | `delivery_verification_items` | belongsTo verification, SRA item; quantities verified/damaged/short | `condition` DeliveryCondition, quantities `decimal:4` |
| `QualityInspection` | `quality_inspections` | belongsTo SRA, DeliveryVerification, Warehouse, inspector(User); hasMany items | `status` QualityInspectionStatus, `result` InspectionResult, `method` InspectionMethod |
| `QualityInspectionItem` | `quality_inspection_items` | belongsTo QualityInspection, SRA item; hasMany findings; inspected/accepted/rejected/quarantined quantities | quantities `decimal:4` |
| `QualityInspectionFinding` | `quality_inspection_findings` | belongsTo QualityInspectionItem, creator(User) | `finding_type` InspectionFindingType, `severity` InspectionFindingSeverity |
| `GoodsReceivedNote` | `goods_received_notes` | belongsTo SRA, PurchaseOrder, QualityInspection, Warehouse, Supplier, poster/creator(User), stockTransaction, reversalOf(self); hasMany items | `status` GoodsReceivedNoteStatus, `posted_at` datetime |
| `GoodsReceivedNoteItem` | `goods_received_note_items` | belongsTo GRN, SRA item, PurchaseOrderItem, Item, UnitOfMeasure, batch, warehouseBin, stockTransactionItem | accepted/rejected/ordered quantities `decimal:4` |
| `SupplierReturn` | `supplier_returns` | belongsTo Supplier, GRN, Warehouse, poster/creator(User); hasMany items | `status` ReturnStatus, `posted_at` datetime |
| `SupplierReturnItem` | `supplier_return_items` | belongsTo SupplierReturn, Item, UnitOfMeasure, batch, stock transaction item | quantity/unit cost `decimal:4` |

### Inventory (`App\Domain\Inventory\Models`)

| Model | Table | Key relationships | Casts / traits |
|-------|-------|-------------------|----------------|
| `StockDimension` | `stock_dimensions` | belongsTo Warehouse, WarehouseLocation, WarehouseBin; hasMany balances, ledger entries; `resolve()` static factory | `type` StockDimensionType, booleans |
| `StockTransaction` | `stock_transactions` | belongsTo Warehouse; hasMany items, ledger entries; morphTo source; belongsTo reversedBy | `transaction_type` StockMovementType, `status` StockTransactionStatus, `posted_at` datetime |
| `StockTransactionItem` | `stock_transaction_items` | belongsTo StockTransaction, Item, UnitOfMeasure, StockDimension, batch, serial | quantity/unit cost `decimal:4` |
| `StockLedger` | `stock_ledger` | belongsTo StockTransaction, StockTransactionItem, Warehouse, StockDimension, Item, batch, serial | `quantity_in`/`quantity_out` `decimal:4`; no timestamps; **append-only**; explicit `$table` |
| `InventoryBalance` | `inventory_balances` | belongsTo Warehouse, StockDimension, Item, batch | on-hand/reserved quantities `decimal:4`; non-null `batch_key` |
| `InventoryCostLayer` | `inventory_cost_layers` | belongsTo Warehouse, Item, StockDimension, batch, transactionItem; FIFO layers | remaining/original quantity, unit cost `decimal:6` |
| `StockReservation` | `stock_reservations` | morphTo source; belongsTo Warehouse, creator(User); hasMany items; `scopeOpen` | `status` StockReservationStatus, `expires_at`/`closed_at` datetime |
| `StockReservationItem` | `stock_reservation_items` | belongsTo StockReservation, Item, StockDimension, batch | quantity/consumed/released `decimal:4` |
| `InventoryValuationSnapshot` | `inventory_valuation_snapshots` | belongsTo Warehouse, Item, creator(User) | quantity/value `decimal`, snapshot date; unique per date/warehouse/item |

## 2B. Phase 6 distribution models (implemented)

### Distribution (`App\Domain\Distribution\Models`)

| Model | Table | Key relationships | Casts / traits |
|-------|-------|-------------------|----------------|
| `MaterialRequisition` | `material_requisitions` | belongsTo Department (`requesting_department_id`), CostCenter, Project, requester(User `requested_by`), destination Warehouse, approver(User `approved_by`); hasMany items, issues, approvalRequests; `scopeVisibleTo`, `scopeSearch`; `ApprovalRequest` via `Approvable` | `status` RequisitionStatus, `priority` string (`low|normal|high|urgent`), dates/datetimes, SoftDeletes, LogsActivity |
| `MaterialRequisitionItem` | `material_requisition_items` | belongsTo MaterialRequisition, Item, UnitOfMeasure; hasMany backorders, issueItems; `unreservedQuantity()` | requested/approved/reserved/issued/outstanding `decimal:4` |
| `MaterialBackorder` | `material_backorders` | belongsTo MaterialRequisitionItem (`requisitionItem`), Warehouse; `scopeOpen`; `outstandingQuantity()` | `status` MaterialBackorderStatus, `outstanding_quantity` `decimal:4`, `expected_date` date |
| `StockIssue` | `stock_issues` | belongsTo MaterialRequisition (`requisition`), Warehouse, recipient/issuer(User), poster(User), `transaction` (StockTransaction); hasMany items; hasOne acknowledgement; `scopeVisibleTo`, `scopeSearch`; state helpers `canBeEdited`, `canBePosted`, `canBeReversed`, `canBeCancelled`, `isAcknowledged` | `status` IssueStatus, `posted_at` datetime |
| `StockIssueItem` | `stock_issue_items` | belongsTo StockIssue (`issue`), MaterialRequisitionItem (`requisitionItem`), Item, UnitOfMeasure, StockReservationItem (`reservationItem`), StockDimension (`dimension`), ItemBatch (`batch`), StockTransactionItem (`transactionItem`) | `issued_quantity` decimal:4, `unit_cost` decimal:6, `total_cost` decimal:2 |
| `IssueAcknowledgement` | `issue_acknowledgements` | belongsTo StockIssue, acknowledgedBy(User) | `acknowledged_at` datetime |
| `StockTransfer` | `stock_transfers` | belongsTo source/destination Warehouse, requester(User `requested_by`), approvedBy(User); hasMany items, dispatches, receipts, discrepancies, approvalRequests; `scopeVisibleTo`, `scopeSearch`; `ApprovalRequest` via `Approvable` | `status` TransferStatus, dates/datetimes, SoftDeletes, LogsActivity |
| `StockTransferItem` | `stock_transfer_items` | belongsTo StockTransfer, Item, UnitOfMeasure | requested/approved/dispatched/received/variance `decimal:4` |
| `StockTransferDispatch` | `stock_transfer_dispatches` | belongsTo StockTransfer (`transfer`), source Warehouse, dispatchedBy(User), `transaction` (StockTransaction); hasMany items, receipts | `status`, `dispatched_at`/`posted_at` datetime |
| `StockTransferDispatchItem` | `stock_transfer_dispatch_items` | belongsTo StockTransferDispatch (`dispatch`), StockTransferItem (`transferItem`), Item, StockDimension (`dimension`), StockTransactionItem (`transactionItem`) | `quantity`/`received_quantity` `decimal:4`, `serial_numbers` array |
| `StockTransferReceipt` | `stock_transfer_receipts` | belongsTo StockTransfer (`transfer`), StockTransferDispatch (`dispatch`), destination Warehouse, receivedBy(User), `transaction` (StockTransaction); hasMany items | `status`, `received_at`/`posted_at` datetime |
| `StockTransferReceiptItem` | `stock_transfer_receipt_items` | belongsTo StockTransferReceipt (`receipt`), StockTransferDispatchItem (`dispatchItem`), StockDimension (`dimension`), StockTransactionItem (`transactionItem`) | received/damaged/missing `decimal:4` |
| `StockTransferDiscrepancy` | `stock_transfer_discrepancies` | belongsTo StockTransfer, StockTransferDispatchItem (`dispatch_item_id`), resolvedBy(User); `scopePending` | `variance_type` TransferDiscrepancyType, `resolution` TransferDiscrepancyResolution, `quantity` decimal:4, `resolved_at` datetime |
| `MaterialReturn` | `material_returns` | belongsTo original StockIssue (`originalIssue`), Supplier, Warehouse, returnedBy(User), receiver(User `received_by`), `transaction` (StockTransaction); hasMany items; `scopeVisibleTo`, `scopeSearch`; state helpers `canBeEdited`, `canBeAssessed`, `canBePosted`, `canBeCancelled` | `return_type` MaterialReturnType, `status` ReturnStatus, `posted_at` datetime, SoftDeletes |
| `MaterialReturnItem` | `material_return_items` | belongsTo MaterialReturn (`materialReturn`), Item, UnitOfMeasure, ItemBatch (`batch`), StockDimension (`dimension`), StockTransactionItem (`transactionItem`); hasMany assessments; `latestAssessment()` | returned/accepted/rejected `decimal:4`, `condition` MaterialCondition, `unit_cost` decimal:6 |
| `MaterialReturnAssessment` | `material_return_assessments` | belongsTo MaterialReturnItem, assessor(User) | `condition` MaterialCondition, `disposition` ConditionDisposition, quantities decimal:4, `assessed_at` datetime |

Models use `HasFactory` with factories resolved by `AppServiceProvider::guessFactoryNamesUsing()`. Distribution documents are not soft-deleted once posted; `MaterialRequisition`, `StockTransfer`, and `MaterialReturn` use `SoftDeletes` for master-document lifecycle while posted rows remain immutable in practice.

## 2C. Phase 7 stocktaking models (implemented)

### Stocktaking (`App\Domain\Stocktaking\Models`)

| Model | Table | Key relationships | Casts / traits |
|-------|-------|-------------------|----------------|
| `StockAdjustment` | `stock_adjustments` | belongsTo Warehouse, StockCount, creator(User `created_by`), approvedBy, postedBy, `reversalOf` (self `reversal_of_id`), `transaction` (StockTransaction `stock_transaction_id`), approvalRequest; hasMany items, `reversals` (self); `scopeSearch`, `scopeVisibleTo`; implements `Approvable` (`approvalDocumentType()` = `stock_adjustment`) | `adjustment_type` AdjustmentType, `reason_code` AdjustmentReason, `status` AdjustmentStatus, `total_value` decimal:2, datetimes, SoftDeletes. `requiresApproval()` / `totalValueExceedsThreshold()` compare against `config('inventory.adjustment_approval_threshold')` |
| `StockAdjustmentItem` | `stock_adjustment_items` | belongsTo StockAdjustment, Item, UnitOfMeasure, StockDimension, batch, serial, StockCountItem (`stock_count_item_id`), StockTransactionItem (`stock_transaction_item_id`) | `quantity_delta`/`unit_cost`/`value_delta` decimals |
| `StockCount` | `stock_counts` | belongsTo Warehouse, creator(User `created_by`), `approver` (User `approved_by`), approvalRequest; hasMany scopes, items, variances, adjustments, reconciliations; `scopeSearch`, `scopeVisibleTo`; state helpers `canBeStarted`, `canBeCounted`, `canBeAnalysed`, `canBeCompleted`, `canBeCancelled`, `isBlind`, `hidesSystemQuantity`, `hasOpenVariances`, `totalVarianceValue` | `count_type` CountType, `status` CountStatus, tolerances/snapshot decimals, datetime casts, SoftDeletes |
| `StockCountScope` | `stock_count_scopes` | belongsTo StockCount, `location` (WarehouseLocation), `category` (ItemCategory), Item | `include_zero_balances` bool |
| `StockCountItem` | `stock_count_items` | belongsTo StockCount, Item, StockDimension, batch, serial, `countedBy` (User); hasMany recounts | system/counted/variance quantities `decimal:4`, `system_unit_cost` decimal:6, `system_value` decimal:2, datetimes, `is_recount` bool |
| `StockCountRecount` | `stock_count_recounts` | belongsTo StockCountItem, `countedBy` (User) | `counted_quantity` decimal:4, `counted_at` datetime, `recount_number` integer |
| `StockCountVariance` | `stock_count_variances` | belongsTo StockCount, StockCountItem, `adjustment` (StockAdjustment `stock_adjustment_id`), `investigatedBy` (User) | `variance_type` VarianceType, `resolution` VarianceResolution, quantity decimal:4, value decimal:2, tolerance bool, `isOpen()`/`isFinal()` helpers |
| `StockReconciliation` | `stock_reconciliations` | belongsTo StockCount, Warehouse, `preparedBy` (User `prepared_by`), `approvedBy` (User), approvalRequest; `scopeVisibleTo` | `status` ReconciliationStatus, snapshot/movement/adjustment/final/system/difference quantities `decimal:4`, datetime casts |

Phase 7 models do not use `HasFactory`; tests build fixtures through `tests/Concerns/BuildsStocktakingFixtures.php` and the domain services. The models use `SoftDeletes` only on the editable documents (`StockAdjustment`, `StockCount`); variance, recount, line, and reconciliation rows are retained historically.

## 2D. Phase 8 reporting models (implemented)

### Reporting (`App\Domain\Reporting\Models`)

| Model | Table | Key relationships | Casts / traits |
|-------|-------|-------------------|----------------|
| `ReportSnapshot` | `report_snapshots` | belongsTo `generator` (User `generated_by`) | `snapshot_date` date, `row_count` integer, `total_quantity` decimal:4, `total_value` decimal:2, `payload` array |

Reporting is intentionally read-mostly: `ReportSnapshot` is the only new Eloquent model. Report definitions, permissions, service bindings, columns and summary methods live in `App\Domain\Reporting\Support\ReportCatalogue`; data-scope rules live in `ReportScope`; row normalisation shared by screens, files and snapshots lives in `ReportRows`. The services under `App\Domain\Reporting\Services` aggregate existing tables and never write to inventory, procurement, warehouse, project or audit tables.

The reporting-only newcomers therefore add **one table** (`report_snapshots`) and **one model**. Daily capture is driven by `App\Jobs\Reporting\GenerateReportSnapshots`, scheduled at 23:45.

## 3. Future models (contract summary)

| Domain | Model | Key relationships |
|--------|-------|-------------------|
| Receiving | `StoreReceiptAdvice` | belongsTo PurchaseOrder/Warehouse/Supplier; hasMany items, verifications, inspections, GRNs |
| Quality | `QualityInspection` | belongsTo SRA; hasMany items; items haveMany findings |
| Inventory | `StockTransaction` | belongsTo Warehouse; hasMany items; items hasMany ledger entries; morphTo source |
| Inventory | `StockLedger` | belongsTo transaction item, warehouse, dimension, item, batch, serial; **no updates** |
| Inventory | `InventoryBalance` | belongsTo warehouse, dimension, item, batch |
| Inventory | `StockReservation` | morphTo source; belongsTo warehouse; hasMany items |
| Stocktaking | `StockCount` | Implemented in Phase 7 — belongsTo Warehouse; hasMany scopes, items, variances, adjustments, reconciliations (see 2C) |
| Workflow | `ApprovalFlow` | hasMany stages; stages haveMany conditions, assignments; hasMany requests |
| Workflow | `ApprovalRequest` | belongsTo flow; hasMany request stages, actions; morphTo document |

## 4. Policy map

| Model | Policy | Key abilities |
|-------|--------|---------------|
| BusinessUnit | `BusinessUnitPolicy` | viewAny, view, create, update, delete |
| Department | `DepartmentPolicy` | viewAny, view, create, update, delete |
| CostCenter | `CostCenterPolicy` | viewAny, view, create, update, delete |
| Project | `ProjectPolicy` | viewAny, view, create, update, delete + manager/membership scope |
| Warehouse | `WarehousePolicy` | viewAny, view, create, update, delete, manageAccess |
| WarehouseLocation | `WarehouseLocationPolicy` | CRUD scoped to warehouse |
| WarehouseBin | `WarehouseBinPolicy` | CRUD scoped to warehouse |
| ItemCategory | `ItemCategoryPolicy` | CRUD |
| UnitOfMeasure | `UnitOfMeasurePolicy` | CRUD |
| Item | `ItemPolicy` | CRUD |
| Supplier | `SupplierPolicy` | CRUD + approve |
| PurchaseRequisition | `PurchaseRequisitionPolicy` | viewAny, view, create, update, delete, submit, cancel + requester/creator scope |
| RequestForQuotation | `RequestForQuotationPolicy` | viewAny, view, create, update, delete, issue, cancel + creator scope |
| SupplierQuotation | `SupplierQuotationPolicy` | viewAny, view, create, update, delete, submit, withdraw, evaluate + creator scope |
| QuotationEvaluation | `QuotationEvaluationPolicy` | viewAny, view, create, update, delete, submit, cancel + creator scope |
| PurchaseOrder | `PurchaseOrderPolicy` | viewAny, view, create, update, delete, submit, cancel, close, amend + creator/delivery-warehouse scope |
| StoreReceiptAdvice | `StoreReceiptAdvicePolicy` | viewAny, view, create, update, delete, submit, cancel, verify, startInspection, createGoodsReceivedNote + warehouse scope |
| QualityInspection | `QualityInspectionPolicy` | viewAny, view, start, record + warehouse scope |
| GoodsReceivedNote | `GoodsReceivedNotePolicy` | viewAny, view, create, post, cancel, reverse + warehouse scope |
| SupplierReturn | `SupplierReturnPolicy` | viewAny, view, create, update, delete, post, cancel + supplier/warehouse scope |
| InventoryBalance | `InventoryBalancePolicy` | viewAny, view + warehouse scope |
| StockTransaction | `StockTransactionPolicy` | viewAny, view + warehouse scope |
| MaterialRequisition | `MaterialRequisitionPolicy` | viewAny, view, create, update, delete, submit, cancel, reserve, adjustApprovedQuantities + requester/destination-warehouse scope |
| StockIssue | `StockIssuePolicy` | viewAny, view, create, update, delete, post, reverse, cancel, acknowledge + warehouse scope; acknowledgement recipient-only |
| StockTransfer | `StockTransferPolicy` | viewAny, view, create, update, delete, submit, cancel, dispatch, receive, resolve + source/destination warehouse scope |
| MaterialReturn | `MaterialReturnPolicy` | viewAny, view, create, update, delete, assess, post, cancel + warehouse scope; assessor ≠ returner/creator |
| StockAdjustment | `StockAdjustmentPolicy` | viewAny, view, create, update, delete, submit, post, reverse, cancel + warehouse scope; creator ≠ poster, original poster ≠ reverser |
| StockCount | `StockCountPolicy` | viewAny, view, create, update, delete, start, count, recount, analyse, adjust, complete, cancel, reconcile + warehouse scope; scheduler/counter cannot adjust own count |
| StockReconciliation | `StockReconciliationPolicy` | viewAny, view, prepare, approve, post + warehouse scope; preparer ≠ approver |
| User | `UserPolicy` | CRUD + assignRoles + manageWarehouseAccess |
| Role | `RolePolicy` | CRUD (Spatie role) |
| SystemSetting | `SystemSettingPolicy` | view, update |
| Activity | `ActivityPolicy` | viewAny |

Policy `before()` grants the Super Administrator. Every other ability checks a permission **and** the warehouse or creator scope. Services repeat the critical checks.

## 5. Model traits and observers

- `LogsActivity` on BusinessUnit, Department, CostCenter, Project, Warehouse, ItemCategory, UnitOfMeasure, Item, Supplier, User, SystemSetting. Log only dirty attributes, skip empty logs.
- `SoftDeletes` on all master data. Ledger and approval actions never use it.
- `HasFactory` on Phase 1 models with factories for tests.
- `booted()` hooks validate immutable codes: on `updating`, if `code` is dirty and the model exists, throw a domain exception. Services also prevent it.

## 6. Decimal handling

- Eloquent decimal casts return strings. Services compare with `bccomp` and add with `bcadd` where arithmetic is unavoidable.
- Validation uses `decimal:0,4` for quantities and `decimal:0,2` for money.
- Blade formats with `number_format((float) $value, 4)` for display only; never for calculations.
- Aggregates in reports use SQL `SUM(decimal)` and return strings.
