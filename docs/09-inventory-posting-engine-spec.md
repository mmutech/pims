# 09 — Inventory Posting Engine Specification

## 1. Purpose

`InventoryPostingService` is the **only** code path allowed to change stock. Every receipt, issue, transfer, return, adjustment, opening balance, and reversal goes through it. Controllers, Livewire components, jobs, and console commands must not write `inventory_balances`, `stock_ledger`, `stock_transactions`, or serial/batch status directly.

## 2. Input contract

```php
final readonly class PostingRequest
{
    public function __construct(
        public DocumentType $documentType,
        public string $sourceType,
        public int $sourceId,
        public StockMovementType $movementType,
        public int $warehouseId,
        public ?int $destinationWarehouseId,
        public array $lines,            // PostingLine[]
        public User $actor,
        public string $idempotencyKey,
        public ?string $reference = null,
        public ?array $metadata = null,
    ) {}
}

final readonly class PostingLine
{
    public function __construct(
        public int $itemId,
        public string $quantity,        // decimal string, > 0
        public int $unitOfMeasureId,
        public int $stockDimensionId,
        public ?int $batchId = null,
        public ?int $serialNumberId = null,
        public ?string $unitCost = null,
        public ?array $metadata = null,
    ) {}
}
```

The service validates that `quantity` is a positive decimal string with at most 4 decimal places and that `unitCost` has at most 6.

## 3. Posting pipeline

All steps run inside one database transaction.

1. **Authorize** — actor has the posting permission for the document type and warehouse access ≥ `Operate`; source document belongs to the actor's organization.
2. **Idempotency** — look up `stock_transactions.idempotency_key`. If found and `status = posted`, return the existing transaction (no new ledger). If found and reversed, reject with a clear error.
3. **Source validation** — document exists, is in an approved/postable state, is not already posted, and its lines match the posting request.
4. **Tracking validation** — batch-tracked lines require a valid batch of the same item; serial-tracked lines require one available serial per unit; batch+serial requires both.
5. **Dimension validation** — stock dimension belongs to the warehouse, is active, and is available for the movement type (e.g., issues cannot come from `in_transit` unless the document is a transfer receipt).
6. **Lock balances** — select affected `inventory_balances` rows `FOR UPDATE` in deterministic order: `warehouse_id, stock_dimension_id, item_id, batch_id`. Create missing rows with zero balances inside the lock.
7. **Availability check** — for outbound movements: `on_hand - reserved >= requested` (or `on_hand >= requested` for reservation consumption paths). Reject with a breakdown if insufficient. For inbound, no availability check.
8. **Valuation** - weighted average: new average = `(old_value + inbound_value) / (old_qty + inbound_qty)` using decimal math; outbound uses current average. FIFO mode consumes cost layers oldest-first.
9. **Create transaction** — insert `stock_transactions` with unique transaction number and idempotency key, status `posted`.
10. **Create transaction items** — one row per posting line with direction and cost.
11. **Write ledger** — append one row per line with `quantity_in`/`quantity_out`, value, and running balance snapshot. Ledger rows are never updated.
12. **Update balances** — on-hand, reserved, available, average cost, total value, last movement, version. Update is projection only.
13. **Update tracking** — batch status/quantity, serial status/current warehouse.
14. **Mark source posted** — set `posted_at`, `posted_by`, status `posted`; set `posting_transaction_id`.
15. **Outbox** — insert `integration_outbox` event (`inventory.posted`) with payload and aggregate reference.
16. **Commit** — after commit, dispatch `InventoryPosted` event and queue listeners.

If any step throws, the transaction rolls back completely; no partial ledger or balance change survives.

## 4. Locking and concurrency

```sql
SELECT *
FROM inventory_balances
WHERE (warehouse_id, stock_dimension_id, item_id, batch_id) IN (...)
ORDER BY warehouse_id, stock_dimension_id, item_id, batch_id
FOR UPDATE;
```

- Lock ordering is mandatory to prevent deadlocks.
- Keep the transaction short: no HTTP calls, file I/O, or notifications inside it.
- `inventory_balances.version` increments on each update; optimistic checks detect unexpected concurrent writes in tests.
- Database constraints are the last line of defense: `on_hand >= 0`, `reserved >= 0`, `reserved <= on_hand`, `available = on_hand - reserved`.
- Unique `idempotency_key` prevents duplicate posting even if two requests race.

## 5. Available stock

```
available = on_hand - reserved
```

- Reservation increases `reserved_quantity` and decreases available, without changing on-hand.
- Issue consumes reservation: on-hand decreases, reserved decreases, available is unchanged by the issue itself (it was already reserved).
- Reservation release decreases reserved and increases available.
- Transfers: source on-hand decreases at dispatch; in-transit dimension on-hand increases. Destination on-hand increases at receipt; in-transit decreases.
- Negative on-hand and negative available are rejected.

## 6. Reversal

Reversal never edits or deletes original rows.

```php
reverse(StockTransaction $original, User $actor, string $reason): StockTransaction
```

1. Authorize `inventory.reverse` and ensure actor ≠ original poster when SoD is enabled.
2. Ensure original status is `posted` and not already reversed.
3. Create a new transaction of type `reversal` with a new idempotency key derived from the original.
4. For each original ledger row, append the opposite movement (`quantity_in` ↔ `quantity_out`) with the original unit cost.
5. Update balances in the opposite direction. If reversing an outbound movement would make on-hand negative (because stock was subsequently consumed), reject the reversal and require a compensating adjustment/return instead.
6. Mark the original transaction `reversed`, link `reversed_by_transaction_id`, and record the reason.
7. Emit `InventoryReversed` and an outbox event.
8. Original ledger rows remain untouched and visible in reports.

Reversal is allowed only for receipts, issues, adjustments, returns, and transfer dispatch/receipt pairs where the downstream movement has not occurred. Transfer reversal after destination receipt requires a compensating transfer.

## 7. Valuation

### Weighted average (default)

```
inbound_value  = quantity * unit_cost
new_total_value = old_total_value + inbound_value
new_on_hand     = old_on_hand + quantity
new_avg_cost    = new_total_value / new_on_hand
outbound_value  = quantity * old_avg_cost
new_total_value = old_total_value - outbound_value
```

Decimal math with scale 6 for unit cost and 2 for value; all arithmetic is performed with bcmath at the configured scale, never with native floats.

### FIFO

`inventory_cost_layers` stores each receipt as a layer with remaining quantity and unit cost. Outbound consumes layers oldest-first and returns the blended cost for the ledger row. The posting service selects the valuation strategy from `items.valuation_method`; the ledger schema is identical, so reports and reconciliation do not change.

## 8. Tracking rules

| Tracking type | Receipt | Issue / transfer out | Return |
|---------------|---------|----------------------|--------|
| none | quantity only | quantity only | quantity only |
| batch | batch required; expiry validated | batch required; FEFO recommended | batch must match original |
| serial | one serial per unit; unique | serials must be available and in the source warehouse | serials must match original issue |
| batch_serial | batch + serials | batch + serials | both must match |

Serial numbers are globally unique. A serial cannot be in two warehouses or two open transactions. Batch expiry is blocked for issue when `expiry_date < today` unless an approved override exists.

## 9. Idempotency

- Key format: `{document_type}:{source_id}:{operation}` (e.g., `goods_received_note:42:post`).
- Unique index on `stock_transactions.idempotency_key`.
- A retry returns the original transaction and a `already_posted` result flag.
- UI disables submit while processing; server-side state checks are authoritative.
- Reversal keys include the original transaction id: `reversal:{original_id}`.

## 10. Error taxonomy

| Code | Meaning | User action |
|------|---------|-------------|
| `DOCUMENT_NOT_APPROVED` | Source not in postable state | Complete approval |
| `DOCUMENT_ALREADY_POSTED` | Idempotent replay | Show existing posting |
| `WAREHOUSE_UNAUTHORIZED` | Actor lacks access | Request access |
| `INSUFFICIENT_STOCK` | Available < requested | Transfer, backorder, or adjust |
| `BATCH_REQUIRED` / `SERIAL_REQUIRED` | Tracking incomplete | Capture tracking data |
| `BATCH_EXPIRED` | Expired stock | Use approved override or dispose |
| `SERIAL_NOT_AVAILABLE` | Serial wrong state/warehouse | Verify serial |
| `DIMENSION_INVALID` | Location/bin mismatch | Correct destination |
| `NEGATIVE_BALANCE` | Projection would go below zero | Investigate, do not bypass |
| `REVERSAL_NOT_ALLOWED` | Downstream movement exists | Use compensating document |
| `CONCURRENT_UPDATE` | Lock/version conflict | Retry |

Errors are domain exceptions with stable codes, translated in the UI; they are never raw SQL errors.

## 11. Reconciliation

A nightly job verifies, per warehouse/item/dimension/batch:

```
SUM(ledger.quantity_in - ledger.quantity_out) == inventory_balances.on_hand_quantity
SUM(ledger.value_in - ledger.value_out) == inventory_balances.total_value
SUM(open reservation line outstanding) == inventory_balances.reserved_quantity
```

`InventoryReconciliationService` returns the discrepancy sets and `assertReconciled()` throws on drift; `ReconcileInventoryBalances` logs the detail and fails the queued job so it reaches monitoring. Mismatches raise a critical alert and require an approved adjustment; the job never auto-corrects balances.

## 12. Security and database hardening

- Application DB role has `INSERT/SELECT` on `stock_ledger`; no `UPDATE/DELETE`.
- Trigger on `stock_ledger` raises an exception on UPDATE/DELETE (PostgreSQL).
- `stock_transactions` and `stock_ledger` are never soft-deleted.
- Posted document rows cannot return to draft; a database trigger or service guard enforces this.
- All posting endpoints require `inventory.post` or a document-specific permission plus warehouse access.
- Audit records include actor, IP, user agent, document, and idempotency key.

## 13. Test matrix

- Receipt increases on-hand and value with correct weighted average.
- Issue decreases on-hand, consumes reservation, and rejects insufficient stock.
- Concurrent issues for the last unit: exactly one succeeds.
- Duplicate posting with the same key returns the original and writes no second ledger.
- Reversal creates compensating entries and restores balances.
- Reversal blocked when downstream consumption exists.
- Batch/serial validation for all four tracking types.
- Transfer dispatch/receipt keeps in-transit accountable.
- Ledger sum equals balance after every scenario (property-style test).
- Deadlock retry under randomized concurrent postings.
- No negative balance under any sequence.

## 14. Implementation status (Phase 5 inventory engine)

Implemented in `App\Domain\Inventory\Services\InventoryPostingService`:

- `post(StockPostingData $data): StockTransaction` with source validation, idempotency key `source_type:source_id:operation`, row-locked balance upserts, negative-available guard, append-only ledger inserts, and FIFO cost-layer writes/consumption for `ValuationMethod::Fifo`.
- `reverse(StockTransaction $transaction, User $actor, string $reason): StockTransaction` posting an equal/opposite transaction with key `reverse:{id}` and linking the original via `reversed_by_transaction_id`.
- `StockDimension::resolve()` for deterministic warehouse/location/bin dimensions; only `warehouse` dimensions are issue-available.
- `inventory_balances.batch_key` projection for portable uniqueness across nullable `batch_id`.
- Receiving integration: GRN posts accepted quantities only; supplier returns post `return_out` with the available-stock guard.
- `StockTransaction::sourceLabel()` resolves the originating document number (for example `GRN/2026/00001`) for transaction lists, falling back to the morph type and id.
- `StockReservationService` reservation lifecycle: `reserve()`, `release()`, `expire()`, with multi-line header/line schema, warehouse-only dimensions, one open reservation per source, and `reserved <= on_hand` enforcement.
- Reservation consumption: `StockPostingLineData::$reservationItemId` locks and validates the reservation line, adds reserved quantity back to the availability check, decrements reserved on issue, and records consumed quantity/status.
- `InventoryValuationService` aggregates balances per warehouse/item into `inventory_valuation_snapshots`; regeneration updates the same row, enforced by a unique `(snapshot_date, warehouse_id, item_id)` index.
- `InventoryReconciliationService` verifies ledger-vs-balance quantity/value plus open reservation outstanding-vs-reserved projection, and `assertReconciled()` throws on drift.
- Scheduled queued jobs: `ExpireStockReservations` (hourly), `GenerateInventoryValuationSnapshots` (daily), `ReconcileInventoryBalances` (daily), registered in `routes/console.php`.

Deferred (remaining Phase 5 hardening):

- Batch/serial expiry automation.
- Concurrency/deadlock stress tests and property-style ledger reconciliation tests.
- A database-level unique constraint (analogous to approval `active_key`) to enforce "one open reservation per source" under simultaneous reservation creation.
- Inventory reporting views and valuation dashboards — delivered in Phase 8 through `App\Domain\Reporting\Services\InventoryReportService` and the report screens.

## 15. Phase 6 distribution integration

All Phase 6 stock movements call `InventoryPostingService`; no distribution service writes balances or ledger rows directly.

| Document | Movement type | Operation key | Source dimension → destination dimension |
|----------|---------------|---------------|-------------------------------------------|
| Stock issue posting | `Issue` | `issue` | Source warehouse main → issue consumption (no destination) |
| Stock issue reversal | reversal of the original | `reverse:{id}` | Compensating ledger entry; original retained |
| Transfer dispatch (out) | `TransferOut` | `dispatch-out` | Source warehouse main → source warehouse `InTransit` |
| Transfer dispatch (in-transit) | `TransferIn` | `dispatch-in-transit` | Into source warehouse `InTransit` |
| Transfer receipt (good) | `TransferIn` | `receipt-good` | Destination warehouse main |
| Transfer receipt (damaged) | `TransferIn` | `receipt-damaged` | Destination warehouse quarantine |
| Transfer receipt (missing) | `TransferOut` | `receipt-transit-out` | Removes the missing quantity from in-transit (no destination on-hand) |
| Discrepancy return-to-source | `TransferOut` / `TransferIn` | `resolve-return-out` / `resolve-return-in` | In-transit → source warehouse main |
| Discrepancy write-off | `AdjustmentOut` | `resolve-write-off` | Out of in-transit |
| Material return (good) | `ReturnIn` | `material_return_post` | Into an issue-available warehouse dimension |
| Material return (non-good) | `ReturnIn` | `material_return_post` | Into a non-available quarantine dimension |

Additional Phase 6 posting rules:

- Issues consume `stock_reservation_items` through `StockPostingLineData::$reservationItemId`; posting rejects any quantity above the reserved amount. On success the reservation line is marked consumed and the requisition counters are updated.
- Issue unit cost and `total_cost` are captured from the posting valuation layer; `total_cost` drives project consumption reporting.
- Transfer dispatch and receipt are separate idempotent postings, so a retry cannot double-post either leg. Dispatch supports partial quantities across multiple dispatch documents.
- Missing transfer quantities remain in transit until a discrepancy is resolved by return-to-source or write-off; `claim` and `replacement` record the outcome without posting stock.
- Only accepted good-condition return quantities reach an issue-available dimension (`StockDimensionType::isAvailableForIssue()`); every other condition targets quarantine or posts no movement.
- Serial-number-aware posting remains deferred: dispatch items store `serial_numbers` JSON, but current posting lines do not pass serial-number IDs.

## 16. Phase 7 inventory-control integration

Stock adjustments are the only Phase 7 documents that move stock; counts and reconciliations read balances/ledger and create adjustments rather than posting directly. Every adjustment goes through `InventoryPostingService`.

| Document | Movement type | Operation key | Source/direction |
|----------|---------------|---------------|------------------|
| Increase adjustment | `AdjustmentIn` | `adjustment:{id}` | Adds quantity to the warehouse dimension |
| Decrease adjustment | `AdjustmentOut` | `adjustment:{id}` | Removes quantity from the warehouse dimension; negative available stock is rejected |
| Adjustment reversal | reversal of the original transaction | `reverse:{transaction_id}` | Equal and opposite posting; original ledger rows retained |

Additional Phase 7 posting rules:

- `StockAdjustmentService::post()` locks and re-validates the document, requires the status to be `approved` or a draft below the approval threshold, and enforces `posted_by ≠ created_by`.
- Each adjustment line posts through `StockPostingLineData` with item, unit, dimension, batch/serial, signed quantity, and unit cost. The posting engine computes the ledger quantity in/out from the sign and updates balances under row locks.
- A decrease that would drive on-hand negative is rejected before any ledger write; the whole transaction rolls back.
- Line `stock_transaction_item_id` and the header `stock_transaction_id` are written after posting so the adjustment is traceable to the ledger.
- Posting a count-generated adjustment marks its linked `StockCountVariance` resolution as `adjusted`.
- `StockAdjustmentService::reverse()` refuses when the original poster is the reverser and creates a new adjustment with `reversal_of_id`, its own `ADJ` number, and a fresh posting. The original becomes `reversed`.
- Count expected quantities are computed from `stock_count_items.system_quantity` plus ledger movements with `id > stock_counts.snapshot_ledger_id`, excluding transactions whose source is an adjustment belonging to the same count. This keeps the snapshot deterministic even when postings and counting overlap.
- `StockReconciliationService` never posts stock. It compares the snapshot-plus-movement-plus-adjustment equation with projected balances and records `difference_quantity`; posting the reconciliation only finalizes the balancing statement.
