# 05 — Laravel Migration Plan

## 1. Principles

- One migration per coherent schema change; no editing applied migrations.
- Migrations are PostgreSQL-first and MySQL-compatible for local execution.
- Foreign keys are declared with explicit `restrictOnDelete()` unless the child is a true aggregate part (`cascadeOnDelete()`).
- Historical tables (ledger, approvals, posted documents) have no `deleted_at`.
- Indexes are created with the table or in a follow-up migration for large tables in later phases.
- All decimal columns specify precision and scale; no `float`/`double` for quantities or money.
- Seeders are idempotent (`firstOrCreate`/`updateOrCreate`) and safe to re-run.

## 2. Phase 1 migration order (implemented)

| # | Migration | Tables / changes | Depends on |
|---|-----------|------------------|------------|
| 1 | `0001_01_01_000000_create_users_table` | users, password_reset_tokens, sessions | — |
| 2 | `0001_01_01_000001_create_cache_table` | cache, cache_locks | — |
| 3 | `0001_01_01_000002_create_jobs_table` | jobs, job_batches, failed_jobs | — |
| 4 | `2026_10_31_101153_create_permission_tables` | Spatie permission tables | users |
| 5 | `2026_10_31_101215_create_activity_log_table` (+ event/batch columns) | activity_log | users |
| 6 | `2026_10_31_110000_create_organization_tables` | regions (dropped in Phase 8 cleanup), business_units, departments, cost_centers, projects, project_members | users |
| 7 | `2026_10_31_110100_add_organization_fields_to_users_table` | users employee/org/status columns | organization |
| 8 | `2026_10_31_110200_create_warehouse_tables` | warehouses, warehouse_locations, warehouse_bins, user_warehouse_access | organization, users |
| 9 | `2026_10_31_110300_create_supplier_tables` | suppliers, contacts, addresses, documents, evaluations | users |
| 10 | `2026_10_31_110400_create_catalog_tables` | item_categories, units_of_measure, items, item_units, item_specifications, item_batches, item_serial_numbers | supplier, organization |
| 11 | `2026_10_31_110500_create_document_sequences_table` | document_sequences | — |
| 12 | `2026_10_31_110600_create_system_settings_table` | system_settings | — |

`users` is created before organization, so the organization migration adds no FK back to users. The follow-up user migration adds nullable organization FKs after organization tables exist. `projects.manager_id` and warehouse `manager_id` reference `users` (already created). `supplier.approved_by` references users. `item_batches.supplier_id` references suppliers, so suppliers are created before catalog.

## 3. Phase 2+ migration order

| Phase | Migration group | Tables | Status |
|-------|-----------------|--------|--------|
| 2 | `2026_11_01_100000_create_approval_tables` | approval_flows, approval_stages, approval_stage_conditions, approval_assignments, approval_requests, approval_request_stages, approval_actions, approval_delegations, approval_escalations | Implemented |
| 3 | `2026_11_02_100000_create_procurement_tables` | purchase_requisitions(+items), request_for_quotations(+items, suppliers), supplier_quotations(+items), quotation_evaluations(+scores), purchase_orders(+items, amendments) | Implemented |
| 4 | `2026_11_03_110000_create_receiving_tables` | store_receipt_advices(+items), delivery_verifications(+items), quality_inspections(+items, findings), goods_received_notes(+items), supplier_returns(+items) | Implemented |
| 5 | `2026_11_03_100000_create_inventory_tables` | stock_dimensions, stock_transactions(+items), stock_ledger, inventory_balances, stock_reservations(+items), inventory_cost_layers, inventory_valuation_snapshots | Implemented (posting, reservations, valuation, reconciliation) |
| 5a | `2026_11_04_090000_normalise_stock_reservations` | moves reservation item/dimension/quantity fields to `stock_reservation_items`; adds `release_reason`, `closed_at`; replaces the warehouse/item index with `(warehouse_id,status)` | Implemented; rollback verified |
| 5b | `2026_11_04_100000_add_unique_index_to_inventory_valuation_snapshots` | unique `(snapshot_date,warehouse_id,item_id)` so snapshots regenerate in place | Implemented |
| 6 | `2026_11_05_100000_create_distribution_tables` | material_requisitions(+items), material_backorders, stock_issues(+items), issue_acknowledgements, stock_transfers(+items, dispatches, receipts, discrepancies), material_returns(+items, assessments) — 16 tables | Implemented; rollback and re-apply verified |
| 7 | `2026_11_06_100000_create_stocktaking_tables` | stock_adjustments(+items), stock_counts(+scopes, items, recounts, variances), stock_reconciliations — 8 tables | Implemented; rollback and re-apply verified |
| 8 | `2026_11_08_100000_create_report_snapshots_table` | report_snapshots | Implemented; reporting reads existing tables, snapshots upsert per report/date |
| 8a | `2026_11_12_000000_remove_regions_from_system` | drops `regions` and every `region_id` column/FK/index; business-unit scoping replaces region scoping | Implemented; `down()` restores the entity and columns |
| 8b | `2026_11_13_000000_remove_business_units_from_non_warehouse_tables` | drops `business_unit_id` from departments, cost centres, projects, users, purchase orders, and material requisitions; business units now group warehouses only | Implemented; `down()` restores nullable columns and indexes |
| 9 | Integration | integration_connections, external_reference_mappings, integration_outbox, integration_logs, integration_retry_policies |

Phase 5 must run before Phase 6 because distribution documents reference stock transactions. Phase 2 must run before Phase 3 because procurement documents request approvals.

Phase 6 migration notes:

- The distribution migration creates all 16 tables in dependency order and drops them in reverse in `down()`, so rollback is clean.
- Every foreign key and index has an explicit short name to stay within MySQL's 64-character identifier limit while remaining PostgreSQL-compatible.
- Check constraints (added only for `mysql`, `mariadb`, and `pgsql`) enforce non-negative quantities, approved ≤ requested, issued ≤ approved, issued > 0, transfer source ≠ destination, and returned > 0 with accepted + rejected ≤ returned.
- Decimal columns are `decimal(18,4)` for quantities, `decimal(18,6)` for unit costs, and `decimal(18,2)` for money.
- No MySQL-only schema APIs (such as `after()`) are used; the migration passed rollback and re-apply on MySQL 9.7.

Phase 7 migration notes:

- `2026_11_06_100000_create_stocktaking_tables.php` creates `stock_counts`, `stock_count_scopes`, `stock_count_items`, `stock_count_recounts`, `stock_adjustments`, `stock_adjustment_items`, `stock_count_variances`, and `stock_reconciliations` — 8 tables in dependency order, dropped in reverse in `down()`.
- `stock_counts.snapshot_ledger_id` is a plain nullable `unsignedBigInteger` (not an FK) so the watermark survives ledger retention rules and avoids a circular dependency.
- `stock_count_items` uses a named composite unique `stock_count_items_unique` across count, item, dimension, batch, serial, and `is_recount`; `stock_count_variances` is unique per count line (`stock_count_variances_item_unique`).
- `stock_adjustments.stock_count_id` links count-generated adjustments; `stock_adjustment_items.stock_count_item_id` links each line back to the counted line.
- Explicit short names (`stock_counts_wh_status_idx`, `stock_adj_items_adj_idx`, `stock_reconciliations_count_idx`, and others) keep identifiers within MySQL's 64-character limit.
- Quantities are `decimal(18,4)`, unit costs `decimal(18,6)`, and money `decimal(18,2)`. The migration is PostgreSQL-compatible and was rolled back and re-applied on MySQL 9.7.

Phase 8 migration notes:

- `2026_11_08_100000_create_report_snapshots_table.php` adds the only Phase 8 table, `report_snapshots`; all reports otherwise read existing operational tables.
- `report_snapshots` uses explicit names `report_snapshots_key_date_unique` on `(report_key, snapshot_date)` and `report_snapshots_date_key_idx` on `(snapshot_date, report_key)`, keeping identifiers inside MySQL's 64-character limit.
- The payload is `json`, which maps to `json` on MySQL and is compatible with `jsonb` on PostgreSQL if the column is converted during production hardening.
- Snapshots upsert on the unique key, so re-running a capture for the same business date replaces rather than duplicates the row. No operational table is altered or re-indexed by Phase 8.

## 4. PostgreSQL production specifics

1. **Connection**: `DB_CONNECTION=pgsql`, host/port/database/user/password from secrets manager. Set `DB_SSLMODE=require` where supported.
2. **Timezone**: store UTC (`APP_TIMEZONE=UTC`); render in the user's configured timezone.
3. **Search**: use `ILIKE` or `pg_trgm` indexes for name/code search. Production hardening (Phase 9) can add trigram indexes on `items.name`, `suppliers.name`, and document numbers; Phase 8 keeps portable `LIKE` search so local MySQL and target PostgreSQL behave identically.
4. **JSON**: use `jsonb`; GIN indexes only on columns actually filtered.
5. **Locking**: `SELECT ... FOR UPDATE` on `inventory_balances` rows. Use deterministic ordering and short transactions.
6. **Append-only ledger**: production role `ims_app` gets `INSERT, SELECT` on `stock_ledger` and `approval_actions`; a separate migration role owns DDL. Add a trigger in Phase 5 that raises on UPDATE/DELETE for defense in depth.
7. **Check constraints**: add `CHECK` constraints for non-negative balances, positive ledger quantities, valid statuses, and transfer source ≠ destination.
8. **Partial unique indexes**: active approval request per document, active flow per document type. Posted-GRN uniqueness per SRA is enforced in `GoodsReceivedNoteService` (plus the posting idempotency key) rather than by a partial index, for MySQL/PostgreSQL parity.
9. **Identity columns**: Laravel `bigIncrements` maps to `bigserial`/identity; keep bigint for ledger growth.
10. **Timestamps**: `timestamptz` via Laravel's `timestampTz` for ledger/posted documents; plain `timestamp` acceptable for master data.

## 5. Data migration and seeding

- Phase 1 seeders: permissions, roles, demo organization, warehouses, categories, UoM, items, suppliers, users.
- Production seeders: permissions/roles only (`db:seed --class=PermissionSeeder`), plus reference data approved by the business (UoM, categories).
- Opening balances are loaded through a controlled opening-balance document in Phase 5, never by direct inserts into `inventory_balances`.
- Legacy item/supplier data migration uses staging tables + validation reports; codes are normalized to uppercase and duplicates rejected before load.
- Every data load is idempotent and produces a reconciliation report.

## 6. Zero-downtime and rollback strategy

- Additive migrations first (new columns nullable, new tables), backfill in batches, then add constraints/indexes `CONCURRENTLY` in PostgreSQL.
- Destructive changes (drop column/table) are deferred one release and guarded by feature flags.
- `down()` exists for development, but production rollback is forward-fix or restore, not `migrate:rollback` on financial data.
- Before each release: backup, run migrations on a staging clone, verify row counts and constraints, then production.

## 7. Test database

`phpunit.xml` points to MySQL `ims_testing` because the local PHP build lacks `pdo_sqlite`. Tests use `RefreshDatabase`, so each run migrates from scratch. On CI with PostgreSQL, override `DB_CONNECTION=pgsql` and `DB_DATABASE=ims_test`; the same migrations must pass on both engines.

## 8. Verification checklist per migration

- [ ] `php artisan migrate:fresh --seed` succeeds.
- [ ] Every FK has an index.
- [ ] Unique constraints match business keys.
- [ ] Decimal precision is explicit.
- [ ] No destructive cascade on historical tables.
- [ ] `down()` reverses cleanly in development.
- [ ] Seeder re-run does not duplicate rows.
- [ ] PostgreSQL-compatible SQL (no MySQL-only functions in migrations).
