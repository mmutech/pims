# 01 — System Architecture

## 1. Context

The IMS supports a DISCO's material lifecycle: procurement planning, supplier management, receiving and quality inspection, multi-warehouse storage, requisition and issuance, inter-warehouse transfers, returns, stocktaking, reconciliation, approvals, reporting, and audit. It is an internal enterprise system; finance and ERP integration are deferred but designed for.

### Actors

| Actor | Primary responsibilities |
|-------|--------------------------|
| Requester | Raises material requisitions for a department, project, or operational team |
| Department / Project Manager | Endorses requisitions and confirms consumption |
| Procurement Officer | Maintains suppliers, RFQs, quotations, and purchase orders |
| Procurement Manager | Approves sourcing and purchase orders |
| Warehouse Officer | Receives, inspects, stores, issues, transfers, and counts stock |
| Warehouse Manager | Authorizes warehouse operations, adjustments, and transfers |
| Quality Inspector | Records inspection findings and accepted/rejected quantities |
| Finance Officer (future) | Consumes valuation and outbox events |
| Executive | Read-only dashboards and reports across the organization |
| Auditor | Read-only access to documents, ledger, approvals, and activity logs |
| System Administrator | Users, roles, permissions, numbering, settings, integrations |

## 2. Architectural style

**Modular monolith.** One deployable Laravel application, one primary database, with strict domain boundaries in code. This gives transactional integrity for inventory while keeping operational complexity lower than distributed services. Domains communicate through application services, domain events, and the outbox — never by reaching into each other's persistence from controllers or Livewire components.

```
HTTP / Livewire
      │
      ▼
┌──────────────────────────────────────────────────────────────┐
│ Presentation: Livewire 3 components + Flux UI + Blade        │
│  - validation, loading states, confirmation dialogs           │
│  - no business rules, no stock writes                         │
└───────────────┬──────────────────────────────────────────────┘
                │ calls
┌───────────────▼──────────────────────────────────────────────┐
│ Application layer: Services, DTOs, Policies, Events           │
│  - orchestration, transactions, authorization re-checks       │
│  - document state machines, approval requests                 │
└───────────────┬──────────────────────────────────────────────┘
                │ uses
┌───────────────▼──────────────────────────────────────────────┐
│ Domain layer: app/Domain/<Domain>                             │
│  - models, enums, domain services, repositories (selective)   │
│  - invariants: no negative stock, immutable ledger, SoD       │
└───────────────┬──────────────────────────────────────────────┘
                │ persists
┌───────────────▼──────────────────────────────────────────────┐
│ Infrastructure: Eloquent, PostgreSQL, Redis, S3, Horizon      │
└──────────────────────────────────────────────────────────────┘
```

### Layer rules

- **Livewire/Blade** may read projections and call application services. It may not write stock, ledger, balances, or approval decisions directly.
- **Services** own transactions and invariants. They accept DTOs, not request arrays.
- **Models** express relationships, casts, scopes, and small invariant helpers. They do not orchestrate multi-aggregate workflows.
- **Policies** answer "may this user perform this action on this record?" Services re-check critical authorization because jobs, commands, and future APIs bypass HTTP middleware.
- **Events/Listeners/Jobs** handle side effects (notifications, report refresh, outbox relay). They never replace the posting transaction.
- **Enums** are PHP backed enums persisted as short strings. Database `CHECK` constraints are added where the value set is stable.

## 3. Code layout

```
app/
  Domain/
    Organization/   Models, enums, services, policies for business units → warehouses
    Warehouse/      Warehouses, locations, bins, access grants
    Catalog/        Categories, items, UoM, conversions, batches, serials
    Supplier/       Supplier master, contacts, addresses, documents, evaluations
    Procurement/    PR, RFQ, quotations, PO, amendments
    Receiving/      SRA, delivery verification, GRN
    Quality/        Inspection templates, inspections, findings
    Inventory/      Posting engine, ledger, balances, reservations, valuation
    Distribution/   Requisitions, issues, acknowledgements, backorders, transfers,
                    in-transit, returns, condition assessment, project consumption
    Stocktaking/    Counts, recounts, variance, reconciliation
    Workflow/       Approval flows, stages, requests, actions, delegation
    Integration/    Connections, mappings, outbox, logs, retries
  Livewire/         Class-based interactive screens (one concern per component)
  DTOs/             Immutable input objects
  Enums/            Cross-domain backed enums
  Events/ Listeners/ Jobs/ Notifications/
  Policies/         Cross-domain policies where a single model has no domain home
  Support/          Decimal helpers, permission registry, document numbers
```

Phase 1 implements Organization, Warehouse, Catalog, Supplier, and the administration shell. Later domains are scaffolded only when their phase begins; the documentation defines their contracts in advance.

The Distribution domain (`App\Domain\Distribution`) owns the operational material lifecycle after receipt: requisitions, reservations and backorders, stock issues and acknowledgements, inter-warehouse transfers with an in-transit dimension and discrepancy resolution, and project/department returns with mandatory condition assessment. It calls the Inventory domain's `InventoryPostingService` for every stock movement and the Workflow domain's `ApprovalEngine` for requisition and transfer decisions; it never writes balances or ledger rows directly.

## 4. Request lifecycle

1. Route middleware enforces `auth`, `verified`, and module permission.
2. Livewire validates input with typed rules and decimal constraints.
3. Component calls an application service with a DTO.
4. Service opens a transaction, re-checks authorization and invariants, writes the aggregate, records activity, and emits a domain event.
5. For inventory documents, the service delegates to `InventoryPostingService` (Phase 5).
6. The transaction commits. Queued listeners handle notifications and outbox relay after commit.

Idempotency: document posting uses a unique `(source_type, source_id, operation)` key on stock transactions plus a database unique index, so retries cannot double-post. UI submits are protected with `wire:loading.attr="disabled"` and server-side uniqueness/state checks.

## 5. Runtime topology (production)

| Component | Technology | Notes |
|-----------|------------|-------|
| Web | Nginx + PHP-FPM 8.3+ | TLS terminated at load balancer |
| App | Laravel 12, Livewire 3 | Stateless; sessions in Redis |
| Database | PostgreSQL 16+ | Primary + streaming replica; PITR backups |
| Cache/session/queue | Redis 7 | Separate logical DBs for cache, sessions, queues |
| Queue workers | Horizon on Linux | Supervisors per queue: `default`, `notifications`, `reports`, `integrations` |
| Object storage | S3-compatible | Supplier documents, inspection evidence, exports |
| Scheduler | `schedule:run` every minute | Expiry alerts, escalation, outbox retries, report snapshots |
| Observability | Laravel logs + Horizon + uptime/DB metrics | No secrets in logs; PII minimized |

Horizon is installed and configured but cannot run on the current Windows workstation (`ext-pcntl`/`ext-posix` absent). Local development uses `QUEUE_CONNECTION=database` and `php artisan queue:work`. Production uses Redis + Horizon.

## 6. Multi-organization and data access

Organizational hierarchy:

```
Business Unit
     └── Warehouse (regional, business-unit, project, transit, quarantine)
          └── Location
               └── Bin

Department
     └── Cost Centre

Project (may span departments; has members)
```

Business units exist only to group warehouses. Departments, cost centres,
projects, and users are standalone master data.

Users carry a home department and optional cost centre. Access is the union of:

- global scope (`system.access.all`) for executives, administrators, and auditors;
- project management or active project membership;
- explicit warehouse grants in `user_warehouse_access`.

Policies implement the read rule; services implement the write rule and re-check warehouse grants for every stock operation. A user cannot approve their own document when the flow marks `prevent_self_approval`.

## 7. Security model

- Laravel session authentication, password hashing (bcrypt/argon), email verification, throttled login.
- Spatie Permission for roles and granular permissions; roles are seeded and editable only by administrators.
- Policies for every aggregate; permission middleware on every route.
- CSRF protection on all Livewire interactions; signed, private file downloads.
- Uploaded documents validated by MIME type and size, stored outside the web root (local) or in private S3 prefixes.
- Activitylog records who changed what, when, and from where for master data, documents, approvals, and configuration.
- Segregation of duties: requester ≠ approver, receiver ≠ inspector where configured, posting officer ≠ reversal approver.
- Sensitive configuration encrypted at rest (`system_settings.is_encrypted`).
- SQL injection prevented by Eloquent/query builder bindings; mass assignment restricted by `$fillable`.
- Rate limiting on authentication and export endpoints.

## 8. Transaction and concurrency strategy

- Default isolation: PostgreSQL `READ COMMITTED`.
- Posting locks balance rows in deterministic order (warehouse, item, batch, serial) using `SELECT ... FOR UPDATE` to avoid deadlocks.
- Unique constraints protect document numbers and posting idempotency keys.
- Balance projection update and ledger insert occur in the same transaction.
- Long-running work (PDF/Excel generation, notifications, outbox relay) is queued after commit.
- Retries are safe because posting is idempotent and queue jobs use backoff with a maximum attempt count.

## 9. Integration readiness

Phase 1 creates no finance integration. The design reserves:

- `integration_connections` — endpoint, auth reference, environment, status.
- `external_reference_mappings` — local entity ↔ ERP/GL identifier.
- `integration_outbox` — immutable event payloads written in the same transaction as the business change.
- `integration_logs` — request/response metadata, retry count, last error.
- `integration_retries` — scheduled retry policy per event.

Inventory valuation and project consumption events are designed to be consumable by Accounts Payable, General Ledger, Cost Centre Accounting, Project Costing, and Inventory Accounting without changing the operational schema.

## 10. Non-functional requirements

| Area | Target |
|------|--------|
| Availability | 99.5% business hours; graceful maintenance mode |
| Performance | List screens p95 < 1.5 s at 100k items / 1M ledger rows with indexed filters |
| Posting throughput | ≥ 20 postings/second sustained, 100 burst |
| Data integrity | Zero unbalanced ledger; nightly reconciliation job |
| Audit retention | Ledger and posted documents retained ≥ 7 years; activity log configurable |
| Backups | Daily full + continuous WAL archiving; restore tested quarterly |
| Accessibility | Keyboard navigable, labelled inputs, visible focus, WCAG 2.1 AA target |
| Browser support | Current Chrome, Edge, Firefox, Safari; responsive ≥ 360 px |

## 11. Key architecture decisions

| ID | Decision | Rationale |
|----|----------|-----------|
| AD-01 | Modular monolith, not microservices | Inventory requires strong transactional consistency; operational simplicity |
| AD-02 | Central posting service + append-only ledger | Single control point for stock integrity and audit |
| AD-03 | Balance projections updated in posting transaction | Fast reads without sacrificing correctness |
| AD-04 | Weighted average and FIFO valuation | Weighted average covers general stock; FIFO consumption is implemented through `inventory_cost_layers` for items configured with `ValuationMethod::Fifo` |
| AD-05 | Database-driven approval engine | Avoids hardcoded stages; supports amount/role/org routing |
| AD-06 | String-backed enums + check constraints | Portable across PostgreSQL/MySQL and safe for evolution |
| AD-07 | Explicit warehouse grants | Warehouse accountability cannot rely on org membership alone |
| AD-08 | Soft deletes for master data, never for ledger | Preserves references while allowing retirement |
| AD-09 | Document sequences table with row locking | Human-readable, gap-free-enough numbering per type/year |
| AD-10 | Outbox for integration | Reliable at-least-once delivery without distributed transactions |

## 12. Phase boundaries

Implemented: Phase 1 foundation, Phase 2 approval engine, Phase 3 procurement, Phase 4 receiving/quality, Phase 5 inventory engine (dimensions, transactions, append-only ledger, balances, reservations, FIFO layers, posting/reversal, valuation snapshots, and scheduled reconciliation), Phase 6 distribution (requisitions, approvals, reservations/backorders, issues and acknowledgements, transfers with in-transit and discrepancy resolution, returns with condition assessment, and project consumption), and Phase 7 inventory control (stock adjustments with approval thresholds and reversal, full/cycle/blind stocktaking, recounts, variance analysis, adjustment generation, and count reconciliation).

Deferred: reports/exports are delivered in Phase 8; production hardening remains Phase 9. Schemas and contracts are documented so earlier choices do not block them.

Phase 7 keeps the same architectural rules as the earlier phases:

- Domain logic lives in `App\Domain\Stocktaking\Services` (`StockAdjustmentService`, `StockCountService`, `StockReconciliationService`); Livewire components only validate input, authorize, and delegate.
- Every stock movement still goes through `App\Domain\Inventory\Services\InventoryPostingService`. Adjustments post `AdjustmentIn`/`AdjustmentOut`; reversals create an equal and opposite document.
- Adjustments implement `App\Domain\Workflow\Contracts\Approvable` and register as the `stock_adjustment` document type. The approval threshold is configuration (`config/inventory.php`, `INVENTORY_ADJUSTMENT_APPROVAL_THRESHOLD`), not a code branch.
- Counts are reconciled by a separate, approvable `stock_reconciliation` document; the count equation is `snapshot + post-snapshot movements + posted adjustments = current ledger`.
- Snapshot determinism is anchored on `stock_counts.snapshot_ledger_id`; expected movement only includes ledger rows with `id > snapshot_ledger_id` (falling back to `posted_at >= snapshot_at` where no watermark exists).
