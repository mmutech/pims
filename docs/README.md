# DISCO IMS — Architecture & Delivery Documentation

This directory is the authoritative design record for the Enterprise Inventory Management System (IMS) for an electricity distribution company (DISCO).

## Document set

| # | Document | Purpose |
|---|----------|---------|
| 01 | [System Architecture](01-system-architecture.md) | Modular monolith, layers, runtime topology, security, NFRs |
| 02 | [Business Processes](02-business-processes.md) | End-to-end process flows, state machines, RACI, controls |
| 03 | [Database Schema](03-database-schema.md) | Full relational schema by domain, constraints, indexes |
| 04 | [ERD](04-erd.md) | Mermaid ERDs split by business domain |
| 05 | [Migration Plan](05-migration-plan.md) | Migration order, PostgreSQL specifics, rollout strategy |
| 06 | [Model Relationships](06-model-relationships.md) | Eloquent models, relationships, casts, traits |
| 07 | [Enums](07-enums.md) | PHP backed enum catalogue |
| 08 | [Approval Workflow Specification](08-approval-workflow-spec.md) | Configurable approval engine |
| 09 | [Inventory Posting Engine Specification](09-inventory-posting-engine-spec.md) | Central posting service, ledger, locking, reversal |
| 10 | [Implementation Roadmap](10-roadmap.md) | Phases, exit criteria, risks |
| 11 | [Role & Permission Matrix](11-permission-matrix.md) | Roles, permissions, data scopes |
| 12 | [UI Navigation & Screens](12-ui-navigation-and-screens.md) | Navigation, screens, states, responsiveness |
| 13 | [Phase 1 Foundation Design](13-phase1-foundation-design.md) | Approved design for the implemented foundation |
| 14 | [System Test Cases and Flow](14-system-test-cases-and-flow.md) | Written end-to-end flow and acceptance/regression cases |

## Implementation status

**Phase 1 — Foundation: implemented and verified.** Laravel 12 / Livewire 3 / Flux 2 / Tailwind 4 application with Breeze auth, Spatie Permission and Activitylog, organization, warehouse, catalog, supplier, and administration modules.

**Phase 2 — Approval engine: implemented and verified.** Configurable flows, stages, conditions, assignments, runtime snapshots, delegation, escalation, append-only action history, approval screens, and policies.

**Phase 3 — Procurement: implemented and verified.** Purchase requisitions (line-level partial approval), RFQs, supplier quotations, weighted evaluations, purchase orders, and numbered amendments, all routed through the approval engine with document numbering and organization scoping.

**Phase 4 — Receiving and quality: implemented and verified.** Store receipt advices, delivery verification, quality inspections with findings, goods received notes, and supplier returns, with an enforced inspection gate and idempotent GRN posting. Only accepted quantities reach available stock; rejected and quarantined quantities never do.

**Phase 5 — Inventory engine: implemented and verified.** Stock dimensions, transactions, append-only ledger, balances, multi-line reservations, weighted-average/FIFO valuation, cost layers, valuation snapshots, scheduled reconciliation, posting service, and reversal are in place. Reservation lifecycle (reserve/release/expire/consume), valuation snapshot generation, and ledger/balance/reservation reconciliation are covered by tests. Inventory reporting is delivered in Phase 8.

**Phase 6 — Distribution: implemented and verified.** Material requisitions with partial approval, reservation and backorders; stock issues with reserved-quantity enforcement and recipient-only acknowledgement; inter-warehouse transfers with in-transit stock, partial dispatch/receipt, and discrepancy resolution; project/department returns with mandatory condition assessment and quarantine routing; and project consumption cost visibility. All stock movements use `InventoryPostingService`, and requisition/transfer decisions use the approval engine.

**Phase 7 — Inventory control: implemented and verified.** Stock adjustments with configurable approval thresholds, reversal, and cancellation; full/cycle/blind stock counts with snapshots and a ledger watermark; recounts and variance analysis; count-generated adjustments; and an approvable reconciliation statement that proves `snapshot + post-snapshot movements + posted adjustments = current ledger`. All stock movement still routes through `InventoryPostingService`, and segregation of duties separates counting, adjustment, posting, and reconciliation.

**Phase 8 — Reporting and exports: implemented and verified.** A report centre plus inventory (valuation, movements, slow/non-moving, expiring, ledger reconciliation), procurement (order register, supplier spend), warehouse (utilisation, throughput), project consumption, and audit (activity trail, approval performance) reports. Every report is defined once in `ReportCatalogue` and shares filtering, data scope, decimal normalisation, CSV/Excel/PDF export, and daily snapshots. Dashboard KPI cards are permission-gated.

Verification: the full suite passes **300 tests (1421 assertions)**. Phase 8 contributes **23 reporting tests (264 assertions)** covering ledger reconciliation and valuation, mismatch detection, warehouse scope, throughput, project consumption scope, procurement visibility and filters, slow-moving and audit reports, exports, export denial, scoped export parity, Excel formats, snapshot capture/upsert, the scheduled job, catalogue integrity, screen gates, and dashboard KPI gating. The Phase 7 baseline remains **277 tests (1157 assertions)**; Phase 7 itself contributes 22 stocktaking tests in `tests/Feature/Stocktaking`. Evidence is in `storage/logs/full-test-run-phase8.txt`. `vendor/bin/pint --test` is clean. Local verification uses MySQL 9.7; PostgreSQL 16 and Redis/Horizon parity checks remain for the production-hardening phase.

## Non-negotiable engineering rules

1. Every stock movement is posted by `InventoryPostingService` inside one database transaction.
2. `stock_ledger` is append-only. Posted rows are never updated or deleted; corrections use compensating entries.
3. Stock balances are projections derived from the ledger and updated only by the posting service.
4. Negative available stock is rejected at posting time under row-level locks.
5. Approval stages are data, never code branches inside business modules.
6. Posted documents are immutable. Changes require reversal, cancellation, or a new revision.
7. Quantities and money use fixed-precision decimals (`decimal(18,4)` / `decimal(18,2)`), never floats.
8. Organization and warehouse authorization is enforced in policies and re-checked in services.
9. Every state transition is auditable through Spatie Activitylog plus domain documents.
10. The system is finance-integration-ready through an outbox, not an accounting implementation.

## Local environment note

The production target is PostgreSQL 16+, Redis, and Horizon on Linux. The current workstation has MySQL 9.7 and no PostgreSQL/Redis server, so local development and tests run on MySQL 9.7 with database-backed cache/queue/session. All migrations and queries are written to be PostgreSQL-compatible; see [Migration Plan](05-migration-plan.md) for the production switch checklist.
