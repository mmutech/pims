# 10 — Implementation Roadmap

## Phase overview

| Phase | Scope | Status |
|-------|-------|--------|
| 1 | Foundation: auth, RBAC, organization, warehouses, master data, admin shell | **Implemented** |
| 2 | Approval engine: flows, stages, assignments, delegation, escalation | **Implemented** |
| 3 | Procurement: PR, RFQ, quotations, evaluation, PO, amendments | **Implemented** |
| 4 | Receiving and quality: SRA, verification, inspection, GRN, receipt posting | **Implemented** |
| 5 | Inventory engine: dimensions, transactions, ledger, balances, reservations, valuation, tracking | **Implemented** |
| 6 | Distribution: requisitions, issues, transfers, returns, project consumption | **Implemented** |
| 7 | Inventory control: adjustments, stocktaking, reconciliation, audit | **Implemented** |
| 8 | Reporting: dashboards, inventory/procurement/warehouse/project reports, Excel/PDF | **Implemented** |
| 9 | Production: performance, security, UAT, migration, backup, monitoring, release | Planned |

## Phase 1 — Foundation (implemented)

**Deliverables**

- Laravel 12 + Livewire 3 + Flux 2 + Tailwind 4 project, PostgreSQL-ready, MySQL-tested.
- Breeze authentication (login, registration, password reset, email verification, profile).
- Spatie Permission with seeded roles/permissions; Spatie Activitylog.
- Organization: business units, departments, cost centres, projects, project members.
- Warehouse: warehouses, locations, bins, user access grants.
- Catalog: categories, units of measure, items, item units, specifications, batches, serials.
- Supplier: suppliers, contacts, addresses, documents, evaluations.
- Administration: users, roles, settings, activity log.
- Dashboard with foundation KPIs.
- Policies with organization and warehouse scoping.
- Seeders and Pest tests.

**Exit criteria**

- [x] `migrate:fresh --seed` succeeds on MySQL (verified). PostgreSQL execution pending a server; schema is PostgreSQL-compatible by design.
- [x] All Phase 1 tests pass — 84 passed, 241 assertions.
- [x] Unauthorized users cannot read or mutate out-of-scope records.
- [x] Codes and serial numbers cannot duplicate.
- [x] Every master-data change is activity-logged.
- [x] UI is responsive and keyboard navigable — 360 px probe passed on all Phase 1 list/detail screens (no horizontal overflow), skip-to-content link present, Lighthouse dashboard accessibility 1.0.
- [x] No stock/ledger code exists yet; Phase 5 owns it.

**Verification:** `php -l` clean (181 files), 24 routes registered, `npm run build` succeeds. Local tests run on MySQL 9.7 because PostgreSQL and Redis are unavailable on the workstation.

## Phase 2 — Approval engine (implemented)

**Deliverables**: workflow tables, engine service, condition/assignment resolvers, delegation/escalation jobs, notifications, configuration UI, pending/history screens, tests.

**Exit criteria**: every document type can submit/act without module-specific approval code; SoD enforced; history append-only; concurrency-safe stage progress.

**Implementation notes**

- Domain lives in `App\Domain\Workflow` (`Models`, `Services`, `Contracts`, `Events`, `Notifications`).
- Runtime snapshots preserve the flow/stage/assignment state at submission time.
- `ApprovalEngine` contract exposes `submit`, `act`, `cancel`, `expire`, `pendingFor`, `historyFor`.
- `approval_requests.active_key` (`document_type:document_id`) enforces one pending request per document.
- Unsupported `department_manager` / `requester_manager` assignments resolve to nobody and mark the stage `blocked` (see cross-cutting backlog).
- Document types are registered in `config/approvals.php`.

**Verification:** shipped with Phase 1/2 suite; covered by `tests/Feature/Workflow`.

## Phase 3 — Procurement (implemented)

**Deliverables**: PR, RFQ, supplier quotations, comparison/evaluation, PO, amendments, approvals, document numbering, screens, tests.

**Exit criteria**: full PR → PO lifecycle with approvals and traceability; partial approval; PO versioning; no PO without approved sourcing (unless emergency policy).

**Implementation notes**

- Domain under `App\Domain\Procurement` (`Models`, `Services`); DTOs under `App\DTOs\Procurement`.
- Document types registered for approval: `purchase_requisition`, `quotation_evaluation`, `purchase_order`, `purchase_order_amendment`.
- PR lines carry `requested_quantity`, `approved_quantity`, and `converted_quantity`; the status becomes `PartiallyApproved` when any approved quantity is below the requested quantity.
- RFQ conversion reserves requisition quantity and is released when an RFQ is cancelled or edited.
- Quotation selection is routed through a `quotation_evaluation` approval; approval awards the RFQ, accepts the recommended quotation, and rejects the remainder.
- PO sourcing requires an accepted quotation or an approved/partially-approved requisition; `config('procurement.allow_unsourced_orders')` is the explicit override.
- PO amendments are numbered per order, require approval, cannot reduce a line below its received quantity, and bump `purchase_orders.version` when applied.
- No stock is posted in Phase 3; receipt/inventory effects belong to Phases 4–5.
- Screens: requisitions (`index/form/show`), RFQs (`index/form/show`), quotations (`index/form/show`), evaluations (`show`), orders (`index/form/show`).

**Dependencies**: Phase 2.

**Verification:** `php artisan migrate:fresh --seed --force` succeeds; 49 Phase 3 tests cover lifecycle, sourcing guard, partial approval, scoping, permissions, and PO versioning.

## Phase 4 — Receiving and quality

**Deliverables**: SRA, delivery verification, inspection templates and findings, GRN, supplier returns, attachments, receipt posting integration.

**Exit criteria**: partial deliveries; accepted/rejected quantities separated; rejected goods never enter available stock; inspection gate enforced; GRN posting idempotent.

**Implementation notes**

- Domain under `App\Domain\Receiving` (`Models`, `Services`); DTOs under `App\DTOs\Receiving`; tables from `2026_11_03_110000_create_receiving_tables.php`.
- Receiving documents are *not* routed through the approval engine: they are permission-gated with explicit status workflows. Document types `DV`, `QI`, `GRN`, `SRT` use the shared `DocumentSequenceService`.
- `StoreReceiptAdviceService` validates that the PO is approved/partially received and that cumulative delivered quantity does not exceed the outstanding order quantity. Submission transitions the SRA to `PendingVerification`.
- `DeliveryVerificationService` records per-line verified/damaged/short quantities and moves the SRA to `PendingInspection` when any line requires inspection, otherwise to `Verified`.
- `QualityInspectionService::start()` requires `PendingInspection`; `record()` classifies lines into accepted/rejected/quarantined quantities and sets the SRA to `Inspected` with an `InspectionResult`.
- `GoodsReceivedNoteService::createFromSra()` requires `Verified` or `Inspected` (inspection gate) and copies accepted/rejected quantities. `post()` sends only accepted quantities through `InventoryPostingService` with idempotency key `...:{id}:grn_post`, updates PO line received/rejected quantities, and activates batches/serials. `reverse()` posts an equal and opposite stock transaction and restores PO quantities.
- `SupplierReturnService` posts `ReturnOut` through `InventoryPostingService` and relies on its available-stock guard; posted returns are immutable and serials are released when a draft is cancelled.
- Rejected and quarantined quantities never enter an available stock dimension.
- Policies: `StoreReceiptAdvicePolicy` (incl. `startInspection`, `createGoodsReceivedNote`), `QualityInspectionPolicy`, `GoodsReceivedNotePolicy`, `SupplierReturnPolicy`.
- Roles/permissions: `receiving.sra.*`, `receiving.inspections.*`, `receiving.grn.*`, `receiving.returns.*`; new `Quality Inspector` role and `quality.inspector@disco.test` demo user.
- Screens: SRA (`index/form/show`), GRN (`index/show`), supplier returns (`index/form/show`).
- Attachments: upload hooks are deferred to the Phase 8/9 document-management work.

**Dependencies**: Phase 3, Phase 5 inventory engine.

**Verification:** 8 Phase 4 tests in `tests/Feature/Receiving/ReceivingWorkflowTest.php` cover all exit criteria (partial delivery, accepted/rejected separation, inspection gate, idempotent GRN posting, supplier-return stock guard, GRN reversal, over-delivery rejection, fully-received PO lock).

## Phase 5 — Inventory engine

**Deliverables**: stock dimensions, transactions, ledger, balances, reservations, weighted-average valuation, FIFO cost layers, batch/serial tracking, posting service, reversal, reconciliation job, tests.

**Exit criteria**: all movements via `InventoryPostingService`; ledger append-only; no negative stock under concurrency; ledger/balance reconciliation passes; duplicate posting impossible.

**Implementation notes**

- Tables from `2026_11_03_100000_create_inventory_tables.php`: `stock_dimensions`, `stock_transactions`, `stock_transaction_items`, `stock_ledger`, `inventory_balances`, `stock_reservations`, `stock_reservation_items`, `inventory_cost_layers`, `inventory_valuation_snapshots`.
- `InventoryPostingService::post()` validates the source, applies an idempotency key `source_type:source_id:operation`, locks/creates balances, rejects negative available stock, writes append-only ledger rows, and maintains FIFO cost layers for `ValuationMethod::Fifo` items.
- `InventoryPostingService::reverse()` posts an equal and opposite transaction, sets the original to `Reversed`, and records `reversed_by_transaction_id` / `reversal_reason`.
- `InventoryBalance` stores a non-null `batch_key` (`batch_id` or `0`) so the unique key `(warehouse_id, stock_dimension_id, item_id, batch_key)` is portable across MySQL and PostgreSQL despite nullable `batch_id`.
- `StockDimension::resolve()` creates deterministic dimensions keyed by `W{warehouse}:L{location|0}:B{bin|0}:{type}`; only `warehouse` dimensions are available for issue.
- `StockReservationService` completes the reservation lifecycle: reserve (multi-line, warehouse-only, one open reservation per source), partial/full release with reason, expiry, and consumption through `InventoryPostingService` via `StockPostingLineData::$reservationItemId`. Header + line schema is normalised; outstanding = `quantity - consumed - released`.
- `InventoryValuationService` writes one snapshot per `(snapshot_date, warehouse_id, item_id)` and safely regenerates the same row. A unique index enforces uniqueness.
- `InventoryReconciliationService` compares ledger sums with balances and open reservation outstanding with `reserved_quantity`; `assertReconciled()` fails on drift.
- Scheduled queued jobs in `routes/console.php`: `ExpireStockReservations` (hourly), `GenerateInventoryValuationSnapshots` (daily 23:55), `ReconcileInventoryBalances` (daily 00:15).
- Implemented screens: inventory balances and stock transactions (read-only list views).
- Verification: `tests/Feature/Inventory/StockReservationTest.php` (10 tests) and `tests/Feature/Inventory/ValuationReconciliationTest.php` (8 tests) cover reservation arithmetic, over-reservation, release activity logging, expiry, consumption, FIFO layer consumption, snapshot totals/idempotency, reconciliation drift, and job behaviour.
- **Remaining Phase 5 hardening**: batch/serial expiry automation; concurrency/deadlock stress tests; a database-level unique constraint (analogous to approval `active_key`) to make "one open reservation per source" race-proof under simultaneous reservation creation; and inventory reporting views (delivered in Phase 8).

**Dependencies**: Phase 1 master data and warehouses; Phase 2 for document approvals; Phases 3–4 for posting sources.

## Phase 6 — Distribution

**Deliverables**: material requisitions, approvals, reservations, issues, acknowledgements, backorders, transfers with in-transit, returns and condition assessment, project consumption.

**Exit criteria**: partial approval/issuance; recipient acknowledgement; transfer discrepancy handling; only good returns become available; project cost visibility.

**Implementation notes**

- Domain under `App\Domain\Distribution` (`Models`, `Services`); DTOs under `App\DTOs\Distribution`; 16 tables from `2026_11_05_100000_create_distribution_tables.php`; models are `MaterialRequisition(+Item)`, `MaterialBackorder`, `StockIssue(+Item)`, `IssueAcknowledgement`, `StockTransfer(+Item, Dispatch, DispatchItem, Receipt, ReceiptItem, Discrepancy)`, and `MaterialReturn(+Item, Assessment)`.
- Approval document types `material_requisition` and `stock_transfer` are registered in `config/approvals.php`; `ApprovalFlowSeeder` creates both flows assigned to `Warehouse Manager`.
- Requisitions move `draft → submitted → approved|partially_approved → reserved → partially_issued|issued → acknowledged → closed`, with rejection, return-for-correction and cancellation branches. The approval callback stores `approved_quantity` (defaulting to requested), recomputes `outstanding_quantity`, and sets `partially_approved` when any line is reduced.
- `MaterialRequisitionService::reserve()` allocates explicitly across available warehouse balances (batch-aware) and creates/updates `material_backorders` for shortfalls. Reservation release is explicit and audited.
- Issues are created as drafts and posted through `InventoryPostingService` with movement type `Issue`. Posting consumes the matching `stock_reservation_items`, rejects issuance above the reserved quantity, decrements requisition `reserved_quantity`, increments `issued_quantity`, and recomputes `outstanding_quantity`. Reversal posts a compensating movement; cancellation is limited to drafts. Posted issues are immutable.
- Recipient acknowledgement is recorded once per issue (`issue_acknowledgements`) and is restricted to the recipient; the issuer cannot acknowledge unless holding `workflow.override`. Acknowledgement captures comment, signature path, IP address, and timestamp.
- Transfers use the approval engine at the source. Dispatch posts `TransferOut` from the source warehouse main dimension and `TransferIn` into the source warehouse `InTransit` dimension (`TRD`), supporting multiple partial dispatches. Receipt (`TRR`) moves good units to the destination main dimension, damaged units to the destination quarantine dimension, and leaves missing units in transit while creating `stock_transfer_discrepancies`.
- Discrepancies are resolved as `return_to_source` (transit → source), `write_off` (adjustment out of transit), `claim`, or `replacement`; only the first two post stock. A transfer completes when all dispatched quantities are accounted for and every discrepancy is resolved.
- Returns reference the original issue for project/department returns; supplier returns remain owned by Phase 4 `SupplierReturnService` and are rejected by `MaterialReturnService`. Condition assessment is mandatory and must split returned quantity into accepted + rejected. Only accepted good-condition quantities post `ReturnIn` to an issue-available warehouse dimension; damaged, defective, quarantined, and repairable quantities post to a non-available quarantine dimension; disposal-pending lines may only be disposed or returned to the supplier.
- Project cost visibility comes from `stock_issue_items.total_cost` (quantity × posting unit cost) and `StockIssueService::projectConsumption()`, which aggregates posted issues by project and optional date range.
- Policies: `MaterialRequisitionPolicy`, `StockIssuePolicy`, `StockTransferPolicy`, `MaterialReturnPolicy`, enforcing module permissions plus organization and warehouse scope. SoD: the issuer cannot acknowledge, and the returner/creator cannot assess their own return unless holding `workflow.override`.
- Permissions: Distribution group `requisition.requests.*`, `requisition.issues.*`, `distribution.transfers.*`, `distribution.returns.*`, `distribution.project_consumption.view`.
- Screens: requisitions (`index/form/show/edit`), issues (`index/form/show`), transfers (`index/form/show`), returns (`index/form/show`), project consumption (`index`) — 16 routes under `distribution.*`, with a Distribution sidebar group.

**Dependencies**: Phase 2, Phase 4, Phase 5.

**Verification:** the full suite passes **255 tests / 1029 assertions** (evidence: `storage/logs/full-test-run-phase6-final.txt`). The distribution suite contributes 55 tests / 273 assertions (`tests/Feature/Distribution`), covering requisitions, partial approval, reservation/backorders, issue posting and reversal, acknowledgement SoD, transfer dispatch/receipt/discrepancies, return assessment/disposition, project consumption, and policy scoping. `tests/Feature/Distribution/DistributionUiTest.php` adds 6 Livewire tests that exercise every index screen plus requisition creation, issue/acknowledgement, transfer dispatch/receipt with a damaged line, return assessment/posting, and project consumption. Migration rollback and re-apply verified on MySQL.

**Deferred / residual hardening:** serial-number-aware issue and transfer posting (dispatch stores `serial_numbers` JSON but posting does not yet pass serial IDs); department/requester manager approval resolution until `departments.manager_id` and `users.manager_id` exist; concurrency stress tests for simultaneous reservation/dispatch; notification templates.

## Phase 7 — Inventory control

**Deliverables**: adjustments, stocktaking (full/cycle/blind), recounts, variance analysis, reconciliation, approval integration, audit reports.

**Exit criteria**: snapshot + movement window reconciles; counters cannot adjust; adjustment SoD; variance approval.

**Implementation notes**

- Domain under `App\Domain\Stocktaking` (`Models`, `Services`); DTOs under `App\DTOs\Stocktaking`; 8 tables from `2026_11_06_100000_create_stocktaking_tables.php`. Models: `StockAdjustment(+Item)`, `StockCount(+Scope, Item, Recount, Variance)`, `StockReconciliation`.
- Enums: `AdjustmentType`, `AdjustmentReason`, `AdjustmentStatus`, `CountType`, `CountStatus`, `VarianceType`, `VarianceResolution`, `ReconciliationStatus`. `DocumentType` adds `stock_adjustment` (`ADJ`), `stock_count` (`CNT`), and `stock_reconciliation` (`REC`).
- `StockAdjustmentService` supports create/update/delete (draft only), submit, post, reverse, cancel. Mixed increase/decrease lines are rejected; value is computed from line deltas; approval is required at or above `config('inventory.adjustment_approval_threshold')` (env `INVENTORY_ADJUSTMENT_APPROVAL_THRESHOLD`, default `1000.00`).
- `StockAdjustment` implements `Approvable` and registers as `stock_adjustment` in `config/approvals.php`. The approval callback writes `approved_at`/`approved_by` and returns rejected/returned/expired documents to draft.
- `StockCountService` supports draft create/update/delete, start (snapshot + `snapshot_ledger_id` watermark), record counts, recount, variance analysis, adjustment generation, complete, and cancel. Expected quantity is the snapshot plus net ledger movement after the watermark, excluding the count's own adjustments.
- Blind counts hide system quantities and variance columns while the count is in `counting`. The scheduler and any counter are excluded from adjusting the count's variances unless holding `workflow.override`.
- `createAdjustments()` creates one auto-submitted adjustment per open variance and links it to the variance. Recounts keep the `recount` status; analysis no longer overwrites it.
- `StockReconciliationService` prepares/recalculates a draft balancing statement (`REC`), then approves and posts it. The equation is `snapshot + post-snapshot movement + posted adjustments = current ledger`; `difference_quantity` must be zero. The preparer cannot approve their own statement, and the count cannot complete before the reconciliation is posted.
- Every stock movement goes through `InventoryPostingService` (`AdjustmentIn`/`AdjustmentOut`); reversals create an equal and opposite adjustment, never an edit of posted rows.
- Policies: `StockAdjustmentPolicy`, `StockCountPolicy`, `StockReconciliationPolicy`, enforcing module permissions plus warehouse scope. SoD: creator ≠ adjustment poster, original poster ≠ reverser, scheduler/counter ≠ variance adjuster, preparer ≠ reconciliation approver.
- Permissions: `inventory.adjustments.*`, `stocktaking.counts.*`, `stocktaking.reconciliations.*`, `stocktaking.reconcile`. Warehouse Manager holds the full set; Store Officer holds the store-operation set (no posting/reversal, no count adjust/complete, no reconciliation approve/post); Quality Inspector can count; global roles receive the view permissions.
- Screens: adjustments (`index/create/edit/show`), counts (`index/create/edit/show`), reconciliations (`index`) — 9 routes under `stocktaking.*`, with an **Inventory Control** sidebar group.
- Tests: `tests/Feature/Stocktaking` covers posting and threshold approval, self-posting and self-reversal rejection, negative-stock rejection, reversal, cancellation, snapshot/movement window, blind counts, recounts, variance analysis, adjustment generation, reconciliation, policy scoping/SoD, and Livewire screen smoke tests.

**Dependencies**: Phase 5, Phase 6 (distribution documents and project consumption feed stocktaking variance analysis).

**Verification:** the full suite passes **277 tests / 1157 assertions** (evidence: `storage/logs/full-test-run-phase7-final.txt`). Phase 7 contributes 22 tests / 128 assertions in `tests/Feature/Stocktaking`, covering adjustment posting and threshold approval, creator-can't-post and original-poster-can't-reverse duty separation, negative-stock rejection, reversal, cancellation, snapshot/movement-window reconciliation, blind counts, recounts, variance analysis, count-generated adjustments, reconciliation approval, policy scoping, and Livewire screen smoke tests. The Phase 7 migration was rolled back and re-applied on MySQL 9.7, and `vendor/bin/pint --test` is clean.

**Deferred / residual hardening:** no dedicated reconciliation detail screen (reconciliation actions live on the count detail screen); `stock_counts.approval_request_id` is reserved but count-level approval is not wired; Phase 7 models have no factories (fixtures build them through services); PostgreSQL/Redis parity validation still pending because only MySQL is available locally.

## Phase 8 — Reporting

**Deliverables**: dashboard KPIs, inventory/procurement/warehouse/project reports, filters, Excel/PDF exports, scheduled snapshots, slow/non-moving and expiry analysis.

**Exit criteria**: reports reconcile to ledger; exports respect permissions and data scope; p95 targets met on production-sized data.

**Implementation notes**

- Domain under `App\Domain\Reporting` (`Models`, `Services`, `Support`), DTO under `App\DTOs\Reporting\ReportCriteria`, one table from `2026_11_08_100000_create_report_snapshots_table.php`. `ReportSnapshot` is the only new model; reporting never writes to inventory, procurement, warehouse, project or audit tables.
- `ReportCatalogue` is the single source of truth: 12 report keys across Inventory, Procurement, Warehouse, Project and Audit, each mapped to a view permission, export permission, service, service method, summary method, columns and value/quantity keys. Screens, exports and snapshots all resolve reports through it.
- `ReportScope` applies data scope consistently: warehouse scope for inventory, warehouse and procurement reports; business-unit/project-membership scope for project consumption; procurement orders additionally honour `PurchaseOrder::scopeVisibleTo()`. A non-global user with no warehouse grants sees no rows.
- `ReportRows` normalises models into scalar rows (raw decimal strings, dates, status labels) and supplies Excel column-letter number formats, so the screen, xlsx/csv, PDF and snapshot payloads cannot drift apart.
- Services: `InventoryReportService` (valuation, movements, slow/non-moving, expiring, ledger reconciliation with mismatch detection), `ProcurementReportService` (order register, supplier spend), `WarehouseReportService` (utilisation, throughput), `ProjectReportService` (posted-issue consumption), `AuditReportService` (activity trail, approval performance). All accept `?User` where `null` means system scope and return `LengthAwarePaginator`.
- `DashboardMetricsService` produces permission-gated KPI cards (inventory value, expiring and slow-moving stock, open purchase orders, project consumption MTD, pending approvals); the dashboard renders only the cards the role may view.
- `ReportExportService` builds one immutable payload (`paginated()` for screens, `payload()` for files and snapshots); `ReportExportController` streams `xlsx`, `csv` or `pdf` via `maatwebsite/excel` 4 and `barryvdh/laravel-dompdf`, re-checking the report's export permission and capping rows at `config('reports.export_row_limit')`.
- `ReportSnapshotService` captures bounded sample rows plus exact totals and upserts on `(report_key, snapshot_date)`. Daily capture runs through `GenerateReportSnapshots` at 23:45 (`withoutOverlapping`); `php artisan reports:snapshots --date=YYYY-MM-DD` captures on demand.
- Permissions: `reports.inventory.*`, `reports.procurement.*`, `reports.warehouse.*`, `reports.project.*`, `reports.audit.*` (each `view`/`export`), assigned in `RoleSeeder`. Screen and export gates are separate, so a role may read a report without downloading its full row set.
- Screens: `reports.index`, `reports.inventory`, `reports.procurement`, `reports.warehouses`, `reports.projects`, `reports.audit`, plus `reports.export/{report}/{format}`. All use the shared `InteractsWithReports` trait and a **Reports** sidebar group.
- Tests: `tests/Feature/Reporting` (5 files) covers ledger reconciliation and valuation, mismatch detection, warehouse scope, throughput, project consumption scope, procurement visibility/filters, slow-moving and audit reports, exports (csv/xlsx/pdf), export denial and unknown keys, scoped export parity, Excel formats, snapshot capture/upsert, the scheduled job, catalogue integrity, report-centre and screen gates, query fallback, and dashboard KPI gating.

**Dependencies**: Phases 3–7.

**Verification:** the full suite passes **300 tests / 1421 assertions** (evidence: `storage/logs/full-test-run-phase8.txt`). Phase 8 contributes **23 tests / 264 assertions** in `tests/Feature/Reporting`, covering valuation/reconciliation against the posted ledger, mismatch detection, warehouse and project data scope, throughput, procurement row visibility and filters, slow-moving and audit reports, CSV/Excel/PDF exports, export permission denial and unknown keys, screen/export parity, Excel number formats, snapshot capture/upsert, the scheduled snapshot job, catalogue integrity, report-centre and screen gates, query fallback, and dashboard KPI gating. `vendor/bin/pint --test` is clean.

**Deferred / residual hardening:** p95 targets on production-sized data cannot be measured locally (no PostgreSQL/large dataset); snapshot trend charts and period-over-period comparison screens are deferred; exports are synchronous and capped, with no background export queue; Redis/Horizon export workers remain Phase 9; the `order_date` in procurement reports is derived from `created_at` because purchase orders carry no explicit order date.

## Phase 9 — Production deployment

**Deliverables**: PostgreSQL/Redis/Horizon deployment, S3, backups/PITR, monitoring, security testing, load testing, UAT, data migration, runbooks, release.

**Exit criteria**: restore drill passed; security scan clean; UAT sign-off; monitoring/alerts live; rollback plan approved.

## Cross-cutting backlog

- Department manager and user manager fields for approval resolution.
- Emergency procurement policy and override audit.
- Currency table and exchange-rate normalization (if multi-currency procurement is required).
- Barcode/QR label printing.
- Email/SMS notification templates.
- API layer for mobile warehouse scanning (future; same services).
- Finance integration outbox relay and ERP mappings.

## Risks and mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Local stack differs from production (MySQL vs PostgreSQL, no Redis) | Late defects | CI on PostgreSQL; migration tests on both; Redis integration tests in Phase 9 |
| Master data quality (duplicate items/serials) | Reporting and stock errors | Unique constraints, validation, staged imports, data steward role |
| Approval configuration complexity | Slow rollout | Start with 3–4 flows, versioned config, UAT per document type |
| Concurrency under load | Overselling/negative stock | Row locks, idempotency, deterministic ordering, concurrency tests |
| Scope creep into finance | Delays core IMS | Outbox only; finance remains a consumer |
| User adoption | Workarounds/off-system stock | Role-based training, simple screens, barcode support, audit enforcement |
| Windows dev limitations | Horizon not runnable locally | Docker/WSL or Linux staging for queue tests |

## Definition of done (every phase)

1. Migrations, models, relationships, enums, services, DTOs, policies, Livewire screens, seeders, and tests delivered together.
2. Authorization and organization/warehouse scope verified by tests.
3. Business invariants enforced in services and, where possible, the database.
4. Activity/audit history recorded.
5. Documentation updated (schema, ERD, permissions, screens).
6. No placeholder implementations; deferred items are explicitly listed.
7. `php artisan test` green; Pint clean; production build succeeds.
