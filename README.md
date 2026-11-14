# Procurement and Inventory Management System (DISCO IMS)

Procurement and inventory management platform for an electricity distribution company (DISCO), built as a modular monolith with Laravel 12, Livewire 3, Flux UI, Tailwind 4, Spatie Permission/Activitylog, and Pest.

## Status

| Phase | Scope | Status |
|-------|-------|--------|
| 1 | Foundation, RBAC, organization, warehouses, master data, admin shell | Implemented |
| 2 | Configurable approval engine | Implemented |
| 3 | Procurement: PR, RFQ, quotations, evaluation, PO, amendments | Implemented |
| 4 | Receiving: SRA, delivery verification, quality inspection, GRN, supplier returns | Implemented |
| 5 | Inventory engine: dimensions, transactions, ledger, balances, reservations, valuation, FIFO layers | Implemented |
| 6 | Distribution: requisitions, issues, acknowledgements, backorders, transfers, returns, project consumption | Implemented |
| 7 | Inventory control: adjustments, stocktaking, variance analysis, reconciliation | Implemented |
| 8 | Reporting and exports: dashboard KPIs, report centre, Excel/CSV/PDF exports, scheduled snapshots | Implemented |
| 9 | Production hardening and deployment | Planned |

Architecture and design records live in [`docs/`](docs/README.md).

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+ and npm
- MySQL 9.x (local) or PostgreSQL 16+ (production target)
- Redis + Horizon in production; database cache/queue are used locally

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Local defaults in `.env` use MySQL database `ims` (`root`/`root`), database cache/queue/session, `MAIL_MAILER=log`, and `REDIS_CLIENT=predis`.

## Demo accounts

All seeded demo users use password `password`.

| Email | Role |
|-------|------|
| `superadmin@disco.test` | Super Administrator |
| `executive@disco.test` | Executive |
| `auditor@disco.test` | Auditor |
| `procurement.manager@disco.test` | Procurement Manager |
| `procurement.officer@disco.test` | Procurement Officer |
| `requester@disco.test` | Department Requester |
| `warehouse.manager@disco.test` | Warehouse Manager |
| `store.officer@disco.test` | Store Officer |
| `quality.inspector@disco.test` | Quality Inspector |
| `abuja.manager@disco.test` | Warehouse Manager (Abuja) |

## Core rules

1. All stock movements go through `App\Domain\Inventory\Services\InventoryPostingService`.
2. `stock_ledger` is append-only; corrections are compensating transactions.
3. Balances are projections updated only by the posting service under row locks.
4. Negative available stock is rejected at posting time.
5. Quantities and money use fixed-precision decimals, never floats.
6. Approval configuration is data, not code branches in domain modules.
7. Posted documents are immutable; changes use reversal, cancellation, or a new revision.
8. Organization and warehouse scope is enforced in policies and re-checked in services.
9. Every state transition is auditable through Activitylog and domain documents.
10. Rejected or quarantined received goods never enter available stock.

## Testing and quality

```bash
vendor/bin/pest
vendor/bin/pint --test
npm run build
```

Tests use MySQL `ims_testing` because the local PHP build has no `pdo_sqlite`. Migrations are written to run on both MySQL and PostgreSQL.

Phase 8 verification: the full suite passes **300 tests / 1421 assertions** (evidence: `storage/logs/full-test-run-phase8.txt`). Phase 8 adds **23 reporting tests / 264 assertions** (`tests/Feature/Reporting`) covering ledger reconciliation and valuation, mismatch detection, warehouse scope, throughput, project consumption scope, procurement visibility and filters, slow-moving and audit reports, CSV/Excel/PDF exports, export permission denial and unknown keys, scoped export parity, Excel number formats, snapshot capture/upsert, the scheduled snapshot job, catalogue integrity, report-centre and screen gates, query fallback, and dashboard KPI gating. The Phase 7 baseline remains **277 tests / 1157 assertions**.

## Documentation

- [Architecture](docs/01-system-architecture.md)
- [Business processes](docs/02-business-processes.md)
- [Database schema](docs/03-database-schema.md)
- [ERD](docs/04-erd.md)
- [Migration plan](docs/05-migration-plan.md)
- [Model relationships](docs/06-model-relationships.md)
- [Enums](docs/07-enums.md)
- [Approval workflow](docs/08-approval-workflow-spec.md)
- [Inventory posting engine](docs/09-inventory-posting-engine-spec.md)
- [Roadmap](docs/10-roadmap.md)
- [Permission matrix](docs/11-permission-matrix.md)
- [UI navigation and screens](docs/12-ui-navigation-and-screens.md)
