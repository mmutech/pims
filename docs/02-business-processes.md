# 02 — Business Process Documentation

## 1. Process map

```
Demand ──► Purchase Requisition ──► Approval ──► RFQ ──► Quotations ──► Evaluation
                                                                          │
                                                                          ▼
                              Supplier ◄── Registration/Evaluation ◄── Sourcing
                                 │
                                 ▼
                          Purchase Order ──► Approval ──► Supplier Delivery
                                                              │
                                                              ▼
                    Store Receipt Advice (SRA) ──► Delivery Verification
                                                              │
                                                              ▼
                     Quality Inspection ──► GRN ──► Inventory Posting (receipt)
                                                              │
                    ┌─────────────────────────────────────────┼─────────────────────┐
                    ▼                                         ▼                     ▼
             Stock Balance                            Material Requisition     Inter-warehouse
                    │                                         │                 Transfer
                    │                                         ▼                     │
                    │                              Approval → Reservation → Issue   │
                    │                                         │                     ▼
                    │                                         ▼              Dispatch → In-transit
                    │                              Recipient Acknowledgement        │
                    │                                         │                     ▼
                    └─────────────── Stock Ledger ◄───────────┴────────── Destination Receipt
                                              │
                                              ▼
                              Returns / Adjustments / Stocktaking / Reconciliation
```

## 2. Organization and access

### 2.1 Master data lifecycle

| Entity | Create | Change | Retire |
|--------|--------|--------|--------|
| Business Unit | BU administrator with permission | Code immutable | Deactivate when no active warehouses |
| Department | Organization administrator | Code immutable | Deactivate when no active projects |
| Cost Centre | Finance administrator | Code immutable; department optional | Deactivate when no active projects |
| Project | Project administrator | Code immutable; status transitions controlled | Close when no outstanding requisitions/issues; never hard delete |
| Warehouse | Warehouse administrator | Type and business unit immutable after first posting | Deactivate when on-hand and reserved stock are zero and no open transfers |
| Location/Bin | Warehouse manager | Code immutable within warehouse | Deactivate when empty |

### 2.2 Rules

- Codes are uppercase, unique, and immutable. They are the human key used on documents and exports.
- A business unit cannot be deactivated while it has active warehouses.
- A warehouse cannot be deactivated while it has stock, reservations, or open transfers (enforced from Phase 5 onward).
- Department and cost-centre records are standalone master data; they are not scoped to a business unit.
- Project membership is time-bounded (`joined_at`, `left_at`) and preserved for audit.
- Deactivation is preferred over deletion. Soft deletes preserve historical references.

### 2.3 Segregation of duties

- A user cannot approve a document they created when the approval flow sets `prevent_self_approval`.
- Warehouse access grants are issued by a user with `warehouse.access.manage`, not by the grantee.
- Stock adjustments and reversals require an approver distinct from the poster (Phase 7).
- Supplier approval requires `supplier.suppliers.approve`; the creator cannot approve their own supplier when SoD is enabled.

## 3. Supplier management

1. **Registration** — Procurement Officer captures legal identity, tax ID, contacts, addresses, and documents. Status starts `pending`.
2. **Due diligence** — Documents validated (registration certificate, tax clearance, bank letter, insurance). Expiry dates tracked.
3. **Approval** — A user with `supplier.suppliers.approve` and no conflict marks the supplier `active` and `is_approved = true`.
4. **Performance** — Evaluations record quality, delivery, pricing, and service scores; overall score is the weighted average configured by category.
5. **Suspension/blacklisting** — Status change requires approval and a reason; open POs are flagged for review. No new awards while suspended/blacklisted.
6. **Retirement** — Suppliers are never hard-deleted once referenced by a document; status becomes `inactive`.

Controls: unique supplier code, unique tax ID where supplied, mandatory approval before PO award, document expiry alerts, complete status history in activity log.

## 4. Procurement lifecycle (Phase 3 implemented)

### 4.1 Purchase Requisition (PR)

- Originates from a department, project, or authorized operational team.
- Captures requester, originating department, business unit, cost centre/project, required-by date, justification, line items, quantities, estimated prices, and suggested supplier (optional). A requisition keeps a business unit because it is not yet bound to a destination warehouse.
- Status: `draft → submitted → approved | partially_approved | rejected | returned → cancelled`; `converted_at` marks sourcing.
- Partial approval is line level: `approved_quantity` starts equal to `requested_quantity` and may be reduced before submission. Any reduced line makes the final status `partially_approved`, and `approved_amount` is recalculated from approved quantities.
- Approval is routed by the configurable engine (amount, department, project, warehouse, role). Submission sets `submitted`; the engine's callback updates the status on approve/reject/return/cancel/expire.
- A PR can be converted to one or more RFQs. `purchase_requisition_items.converted_quantity` tracks reserved quantities; conversion is rejected above the approved balance, and cancelling or editing a draft RFQ releases the reservation.
- Only `approved` and `partially_approved` requisitions can be sourced.
- Cancellation requires a reason and cancels any pending approval request.

### 4.2 RFQ and quotations

- RFQ is created from the unconverted balance of an approved PR, or standalone when procurement identifies a need; it is issued to one or more suppliers with a closing date.
- Status: `draft → issued → closed → awarded | cancelled`; `issued` and `closed` accept quotations.
- Suppliers submit quotations per RFQ line: quantity, unit price, tax rate, discount, lead time, validity, payment terms, warranty.
- Quotation totals are recalculated in the service with `Decimal` (money scale 2) and persisted; duplicates per `(RFQ, supplier)` are blocked and only invited suppliers may quote.
- Submitting a quotation sets the RFQ invitation to `responded`; withdrawing sets it to `no_response`.
- Evaluation records weighted technical/commercial scores and a recommended quotation.
- Supplier selection is approved through a `quotation_evaluation` approval. On approval the recommended quotation becomes `accepted`, all other quotations for the RFQ become `rejected`, and the RFQ becomes `awarded`.

### 4.3 Purchase Order (PO)

- Header: supplier, buyer org unit, delivery warehouse, currency, payment terms, incoterms, expected delivery, optional PR and accepted-quotation references.
- Lines: item, ordered quantity, unit price, tax, discount, required date, cost centre/project allocation, optional PR line reference.
- Status: `draft → pending_approval → approved → partially_received → fully_received → closed | cancelled`; rejected/returned/expired approvals return the PO to `draft`.
- POs can be created from an accepted quotation (copies lines and header terms) or directly from an approved/partially-approved PR.
- Sourcing guard: a PO requires an accepted quotation or an approved requisition unless `config('procurement.allow_unsourced_orders')` is explicitly enabled. An accepted quotation must match the PO supplier; only `accepted` quotations can be converted.
- Amendments create a numbered record per PO (`amendment_number`), capture original and amended values, require approval, and apply to PO lines on approval while incrementing `purchase_orders.version`. Amended quantities cannot fall below `received_quantity`. Each amendment approval request snapshots the projected post-amendment total (subtotal plus tax) so approvers see the amount they are authorising.
- Partial deliveries are normal. Received quantity per line is the sum of accepted GRN quantities (Phase 4).
- PO closure is allowed when no open receipts and no outstanding accepted quantities; cancellation requires approval and cannot reverse already posted receipts.

### 4.4 Controls

- No PO without an approved sourcing document (PR/RFQ) unless emergency procurement is enabled by policy.
- No receipt without an approved PO line.
- Over-receipt tolerance is configurable per item/category (default 0%).
- Three-way match readiness: PO ↔ GRN ↔ supplier invoice (invoice matching is future finance scope).
- All amounts decimal; totals computed in the service layer and persisted, never in Blade.
- Sourcing reservations are transactional and released on RFQ cancellation/update; amendment application and approval requests are transactional.
- No stock movements are posted in Phase 3; receipt and ledger effects belong to Phases 4–5.

## 5. Receiving and quality inspection

### 5.1 Documents

| Document | Owner | Purpose |
|----------|-------|---------|
| Store Receipt Advice (SRA) | Warehouse | Advises expected delivery against PO lines; records vehicle, driver, documents |
| Delivery Verification | Warehouse | Compares physical delivery with SRA/PO: quantity, packaging, labels, damage |
| Quality Inspection | Quality | Samples/tests against specification; records accepted, rejected, and quarantined quantities |
| Goods Received Note (GRN) | Warehouse | Final accepted receipt that triggers inventory posting |

SRA, inspection, and GRN are separate records with separate numbering, statuses, and audit trails. They may reference each other but cannot be collapsed into one table.

### 5.2 Flow

1. Supplier delivers against an approved PO. Warehouse creates an SRA (full or partial).
2. Delivery verification records actual quantities and condition. Discrepancies are noted with evidence.
3. If the item requires inspection, a Quality Inspection is raised. Rejected quantities never enter available stock; they move to quarantine/rejection holding.
4. GRN records accepted quantities per line, batch/serial details, bin assignment, and inspection reference.
5. `InventoryPostingService` posts the receipt: debit on-hand, create cost layer, update batch/serial status, write ledger, outbox.
6. Rejected material is returned to supplier or moved to a quarantine location under a separate document.

### 5.3 Controls

- GRN quantity ≤ verified quantity ≤ SRA quantity ≤ PO open quantity (plus tolerance).
- Inspection is mandatory when the item/category requires it; the GRN cannot post without a passed/partially-passed inspection.
- Serialized items require one serial per accepted unit; batch-tracked items require a batch number and expiry where applicable.
- Evidence documents are private and linked to the inspection.
- Receipt posting is idempotent; re-submission returns the original posting result.

### 5.4 Implemented state machine (Phase 4)

```text
SRA: draft -> pending_verification -> verified
                                  -> pending_inspection -> inspected
     verified|inspected -> posted (via GRN) -> reversed
     any non-posted -> cancelled

Delivery verification: pending -> verified | discrepancy
Quality inspection:    pending -> in_progress -> passed | failed | partially_passed
GRN:                   draft -> posted -> reversed ; draft -> cancelled
Supplier return:       draft/assessed -> posted ; draft -> cancelled
```

- `draft` SRAs are editable; submission validates remaining PO quantity and requires an approved or partially received PO.
- Verification is per line: verified quantity cannot exceed delivered quantity; damaged/short quantities are recorded separately.
- If any line requires inspection, verification sets `pending_inspection` and GRN creation is blocked. Otherwise the SRA goes straight to `verified`.
- Inspection records inspected, accepted, rejected, and quarantined quantities per line plus typed/severity-rated findings. The SRA becomes `inspected` with an aggregate result.
- GRN creation is allowed from `verified` or `inspected`, copies accepted/rejected quantities, and is idempotent on posting (`status = posted` + posting idempotency key).
- Only accepted quantities reach a `warehouse` stock dimension. Rejected and quarantined quantities never become available stock.
- GRN reversal posts an equal/opposite stock transaction, marks the original transaction `reversed`, and decrements PO received/rejected quantities.
- Supplier returns post `return_out` through `InventoryPostingService`; the service rejects quantities above available stock and the return remains draft.
- Receiving documents are permission-gated, not routed through the approval engine. Posting and reversal are separated by `receiving.grn.post` and `receiving.grn.reverse`.

### 5.5 Implemented integration points

- `InventoryPostingService` is the only writer of stock transactions, ledger rows, balances, and FIFO cost layers.
- Document numbering uses `DocumentSequenceService` with prefixes `SRA`, `DV`, `QI`, `GRN`, `SRT`, and `STX` for stock transactions.
- Activity log records creation, status transitions, posting, and reversal with actor and reason.
- Attachments for delivery/inspection evidence are deferred to Phase 8/9 document management.

## 6. Material requisition and issuance

### 6.1 Flow

```
Draft → Submitted → Approved (full/partial) → Reserved → Issued (full/partial) → Acknowledged → Closed
                  ↘ Rejected / Returned for correction
```

1. Requester selects originating department/project/cost centre, destination warehouse, purpose, required-by date, and lines.
2. Approval engine routes by value, org unit, project, and warehouse. Partial approval sets approved quantities per line.
3. Stock verification checks available = on-hand − reserved across authorized warehouses.
4. Reservation locks stock for the requisition (or a specific batch/serial). Reservations expire per policy.
5. Store Issue Voucher records physical issuance, batch/serial selection, and bin.
6. Recipient acknowledges receipt (signature/OTP/user confirmation) before the issue is closed.
7. Posting decreases on-hand and reserved, writes ledger, and records project/department consumption.

### 6.2 Shortages and backorders

- If available stock is insufficient, the requisition may be partially reserved and the balance placed on backorder.
- Backorders are visible to procurement and warehouse planning.
- Partial issuance is allowed; outstanding quantities remain open until fulfilled or cancelled.
- Reservation release is explicit and audited; expired reservations are released by a scheduled job.

### 6.3 Controls

- No issue without an approved requisition and an active reservation.
- No issue across warehouses without warehouse authorization.
- Issued quantities cannot exceed reserved quantities.
- Serialized items must be issued by serial; batch items by batch.
- Recipient acknowledgement is mandatory for closure; the issuer cannot acknowledge on behalf of the recipient unless delegated.

### 6.4 Implemented state machine (Phase 6)

```
draft ─submit─▶ submitted ─approve(full)─▶ approved ─reserve─▶ reserved
                      │                        └─approve(partial)─▶ partially_approved
                      ├─reject─▶ rejected
                      └─return for correction─▶ returned ─resubmit─▶ submitted
reserved ─issue(partial)─▶ partially_issued ─issue(full)─▶ issued
partially_issued/issued ─acknowledge─▶ acknowledged ─close─▶ closed
draft/submitted/approved/partially_approved/reserved/partially_issued/issued ─cancel─▶ cancelled
```

- `MaterialRequisitionService` owns create/update, submit, approved-quantity adjustment, reservation, cancellation, and deletion. Deletion is limited to drafts with no downstream documents.
- The `material_requisition` approval callback writes `approved_quantity` (defaulting to the requested quantity), recomputes `outstanding_quantity`, and sets `partially_approved` when any line is reduced.
- `reserve()` walks available warehouse stock (batch-aware) and creates or updates `material_backorders` for the uncovered balance. Reservation release is explicit and activity-logged.
- `StockIssueService` creates drafts from the latest open reservation, posts through `InventoryPostingService` with movement type `Issue`, consumes reservation lines, rejects quantities above the reserved amount, and updates requisition reserved/issued/outstanding quantities. Reversal posts a compensating transaction; cancellation is limited to drafts.
- Acknowledgement is written once per issue and restricted to the recipient. The issuer needs `workflow.override` to acknowledge on the recipient's behalf, and the exception is audited.
- Project/department consumption value is captured on `stock_issue_items.total_cost` at posting and aggregated by `StockIssueService::projectConsumption()`.

## 7. Inter-warehouse transfers

### 7.1 Flow

```
Request → Source Approval → Destination Confirmation → Reserve → Dispatch
       → In-Transit → Destination Receipt → Reconciliation → Completed
```

- Transfer request identifies source and destination warehouses, items, quantities, reason, and required date.
- Source approval checks stock and authorization. Destination confirmation accepts accountability.
- Dispatch posts `transfer_out`: stock leaves source on-hand and enters in-transit (a warehouse/location dimension, not a negative balance).
- Destination receipt posts `transfer_in`: in-transit decreases, destination on-hand increases.
- Multiple dispatches and partial receipts are supported.
- Discrepancies (short, damaged, missing) create a reconciliation record; the difference remains in transit until resolved or written off with approval.
- Cancellation is allowed only before dispatch; after dispatch, reversal requires a compensating transfer and approval.

### 7.2 Controls

- Source and destination cannot be the same warehouse.
- In-transit stock is owned by the transfer, not by either warehouse's available balance.
- Destination receipt cannot exceed dispatched quantity.
- Every dispatch/receipt pair is reconciled; unresolved discrepancies are reported.
- Complete audit history: request, approvals, dispatch, transit, receipt, variance, closure.

### 7.3 Implemented state machine (Phase 6)

```
draft ─submit─▶ pending_approval ─approve─▶ approved
approved ─dispatch(partial)─▶ partially_dispatched ─dispatch(remaining)─▶ in_transit
partially_dispatched/in_transit ─receive(partial)─▶ partially_received
partially_received/in_transit ─receive(complete)─▶ completed
draft/pending_approval/approved ─cancel─▶ cancelled
```

- `stock_transfer` is an approval-engine document type. Cancellation before dispatch also cancels any pending approval request.
- `StockTransferService::dispatch()` posts `TransferOut` from the source warehouse main dimension and `TransferIn` into the source warehouse `InTransit` dimension. Dispatches are numbered (`TRD`) and may be partial.
- `receive()` posts good units `TransferIn` to the destination main dimension, damaged units to the destination quarantine dimension, and leaves missing units in transit. Receipts are numbered (`TRR`).
- Damaged, missing, short, and over variances create `stock_transfer_discrepancies`. `resolveDiscrepancy()` accepts `return_to_source`, `write_off`, `claim`, or `replacement`; the first two post stock movements, the others record the terminal outcome only.
- A transfer completes only when every dispatched quantity is accounted for and no discrepancy remains pending. The source and destination warehouse check constraint is enforced at the database level.

## 8. Material returns

### 8.1 Project/department returns

1. Return document references the original issue and requester.
2. Condition assessment: good, damaged, defective, quarantined, repairable, disposal pending.
3. Good, accepted material is posted back to on-hand (return_in) with batch/serial validation.
4. Damaged/defective material goes to quarantine or repair holding; it is not available for issue.
5. Disposal requires approval and is recorded as an adjustment/outbound movement.

### 8.2 Supplier returns

1. References the original GRN/PO and rejected or defective quantity.
2. Return authorization and shipping documents captured.
3. Posting removes stock from the appropriate location (on-hand or quarantine) and writes the ledger.
4. Supplier credit note reference is stored for future finance integration.
5. Replacement receipts follow the normal receiving process.

### 8.3 Controls

- Return quantity ≤ originally issued/received quantity less prior returns.
- Condition assessment is mandatory.
- Only accepted good-condition returns become available stock.
- Serial/batch identity is validated against the original movement.
- Returns are never deleted; corrections use reversal documents.

### 8.4 Implemented state machine (Phase 6)

```
draft ─assess─▶ assessed ─post─▶ posted
draft ─cancel─▶ cancelled     (posted returns are immutable)
rejected status is reserved for a failed/refused return decision
```

- `MaterialReturnService` owns create/update, assessment, posting, cancellation, and deletion (draft only). `MaterialReturnType::Supplier` is rejected; supplier returns continue through the Phase 4 `SupplierReturnService`.
- Project and department returns require an original stock issue. Lines are limited to the issued quantity less prior returns.
- Assessment is mandatory before posting. Each line must split the returned quantity into accepted + rejected (`accepted + rejected == returned`), and every line must be assessed.
- **Only accepted good-condition quantities re-enter available stock.** `MaterialCondition::Good` maps only to `ConditionDisposition::ReturnToStock` and posts `ReturnIn` to an issue-available warehouse dimension.
- Damaged, defective, quarantined, and repairable quantities must use a quarantine/repair disposition and post to a non-available quarantine dimension. Disposal-pending lines may only be disposed or returned to the supplier.
- The returner/creator cannot assess their own return unless holding `workflow.override`; the override does not relax the good-condition availability rule.
- Project cost and consumption visibility come from the linked posted issue, so a return does not rewrite historical project cost; subsequent issue values reflect the stock position.

## 9. Stock adjustments (Phase 7 implemented)

- Adjustment documents capture reason code, warehouse, stock count (optional), item, unit, location-derived dimension, batch/serial, signed quantity delta, unit cost, value delta, and evidence in the reason/line notes.
- Reasons: damage, theft, obsolescence, count correction, reclassification, opening balance, system correction (`AdjustmentReason`).
- Posting writes `adjustment_in` or `adjustment_out` ledger entries through `InventoryPostingService`; no service writes balances directly.
- Negative stock is never allowed; an outbound adjustment beyond available stock is rejected by the posting engine.
- Adjustments at or above `inventory.adjustment_approval_threshold` require approval by a user other than the creator. A threshold of `0` auto-approves every adjustment, and a below-threshold adjustment may be posted directly.
- Posted adjustments are immutable. Reversal creates a new equal and opposite adjustment (`reversal_of_id`) with its own document number; the original is marked `reversed`.
- Every adjustment is reported and reconciled to the ledger.

### 9.1 Implemented state machine (Phase 7)

```
draft ──submit──▶ pending_approval ──approve──▶ approved ──post──▶ posted ──reverse──▶ reversed
  │                     │                          │
  │                     ├──reject/return──▶ draft  └──cancel──▶ cancelled
  └──post (below threshold)──▶ posted
  └──cancel──▶ cancelled
```

- `StockAdjustmentService::create()` accepts signed line deltas, derives the direction, rejects mixed increase/decrease lines in one document, and computes `total_value`.
- `submit()` raises an approval request only when the value meets the threshold; otherwise it marks the document approved-ready for direct posting.
- `post()` enforces `posted_by ≠ created_by`; `reverse()` enforces `reversed_by ≠ original poster`.
- `delete()` is limited to drafts; `cancel()` is limited to draft/pending/approved states and records `cancelled_at` + reason.
- Adjustment lines linked to a count variance (`stock_count_item_id`) clear the variance resolution to `adjusted` when the adjustment posts.

## 10. Stocktaking and reconciliation (Phase 7 implemented)

### 10.1 Types

- Full warehouse count, cycle count, and blind count (`CountType`).
- Count can be scheduled or ad hoc; scope by warehouse, location, category, or item (`stock_count_scopes` rows combine with OR; a blank scope covers the whole warehouse).

### 10.2 Flow

1. Count initiated. `StockCountService::start()` freezes on-hand quantities per item/dimension/batch/serial into `stock_count_items` and records `snapshot_at` plus `snapshot_ledger_id`.
2. Counting proceeds physically; counters record quantities. Blind counts hide system quantities and variances while the status is `counting`.
3. Movements after initiation are tracked separately and applied to the snapshot during expected-quantity calculation. Expected quantity = snapshot quantity + net ledger movement with `id > snapshot_ledger_id`, **excluding adjustments created by the same count**.
4. Recounts are recorded per line (`stock_count_recounts`) and recalculate the variance. The count stays in `recount` status.
5. Variance analysis (`analyseVariances()`) classifies gain, loss, misplacement, or recording error, computes value, and flags tolerance breaches.
6. `createAdjustments()` generates one adjustment per open variance and auto-submits it. It refuses if the actor scheduled the count, counted any line, or lacks the adjust ability (unless holding `workflow.override`).
7. Posting applies adjustments through the centralized posting engine. A separate `stock_reconciliation` document proves `snapshot + movements + posted adjustments = current ledger`.
8. The count can only complete once a reconciliation has been prepared, approved, and posted.

### 10.3 Controls

- Counters cannot adjust stock directly; the adjust ability is separate from count/recount.
- Blind count hides expected quantities until the count leaves `counting`.
- Recounts are recorded with their own counter, timestamp, and number.
- Adjustment approval is segregated from counting and posting.
- Snapshot and subsequent movement windows are deterministic and auditable (`snapshot_ledger_id`).
- The count's scheduler and any counter are barred from adjusting its variances (unless `workflow.override`).
- The reconciliation preparer cannot approve their own statement.

### 10.4 Implemented state machine (Phase 7)

```
draft ──start──▶ counting ──record──▶ variance_review ──createAdjustments──▶ adjustment_pending
                   ▲                        │
                   └──────recount───────────┘

adjustment_pending ──(all count adjustments posted + reconciliation posted)──▶ completed
draft|counting|recount|variance_review|adjustment_pending ──cancel──▶ cancelled
```

- `StockCountService::update()`/`delete()` are draft-only.
- `recordCount()` records one entry per line, marks `counted_by`/`counted_at`, recalculates variance, and moves the count to `variance_review`.
- `recount()` records a recount and keeps the `recount` status; `analyseVariances()` no longer overwrites it.
- `createAdjustments()` moves open variances into `adjustment_pending` and links each variance to its adjustment.
- `complete()` requires a posted reconciliation.
- All stock changes still route through `InventoryPostingService`.

### 10.5 Reconciliation equation

`StockReconciliationService` prepares, approves, and posts a separate document (`REC/…`) with:

| Field | Meaning |
|-------|---------|
| `snapshot_quantity` | On-hand captured at count start |
| `movement_quantity` | Net ledger movement after `snapshot_ledger_id` |
| `adjustment_quantity` | Posted adjustments created by this count (increase − decrease) |
| `final_quantity` | Resulting ledger position (`snapshot + movements + adjustments`) |
| `system_quantity` | Current projected on-hand at preparation time |
| `difference_quantity` | `final − system`; must be zero for a clean reconciliation |

`prepare()` reuses or recalculates only a draft statement. Approval is segregated from preparation, and posting is the prerequisite for completing the count.

## 11. Approval workflow (configuration, not code)

- Every document type declares an approval flow key (e.g., `purchase_order`, `material_requisition`).
- Flows contain ordered stages; stages can be sequential or parallel.
- Stage assignment supports role, user, department, project, warehouse, and amount bands.
- Conditions are evaluated against document attributes (amount, org unit, category, warehouse, project).
- Delegation transfers an approver's pending actions for a period; escalation triggers after SLA hours.
- Actions: approve, reject, return for correction, delegate, escalate (system), cancel.
- Segregation of duties: `prevent_self_approval` blocks the document creator and, optionally, the last editor.
- Approval history is append-only and includes actor, stage, action, comment, IP, and timestamp.

Detailed engine design: [08 — Approval Workflow Specification](08-approval-workflow-spec.md).

## 12. RACI summary

| Activity | Requester | Dept/Project Manager | Procurement | Warehouse | Quality | Finance | Executive |
|----------|-----------|----------------------|-------------|-----------|---------|---------|-----------|
| Raise requisition | R | A | C | I | – | – | I |
| Approve requisition | C | A/R | I | C | – | – | I |
| Supplier registration | – | – | R | I | – | C | I |
| Supplier approval | – | – | R | – | – | C | A |
| Create RFQ/PO | – | C | R | I | – | C | I |
| Approve PO | – | C | R | I | – | C | A |
| Receive goods (SRA) | – | – | I | R | – | – | I |
| Quality inspection | – | – | I | C | R/A | – | I |
| Post GRN | – | – | – | R/A | C | I | I |
| Approve issue | C | A | – | R | – | – | I |
| Issue stock | – | I | – | R/A | – | – | I |
| Transfer stock | – | I | – | R/A | – | – | I |
| Stock adjustment | – | C | – | R | – | C | A |
| Stocktake | – | I | – | R/A | – | C | I |
| Reconcile variance | – | – | – | R | – | C | A |

R = responsible, A = accountable/approver, C = consulted, I = informed.

## 13. Document state conventions

All controlled documents share:

- `status` (backed enum string)
- `document_number` (unique, generated under lock)
- `created_by`, `updated_by`, `approved_by`, `posted_by`, `posted_at`
- `version` for amendments
- `cancelled_at`, `cancellation_reason`
- optimistic concurrency via `updated_at` checks on critical transitions
- append-only history table or activity log entries for every transition

## 14. Exception handling

| Exception | Response |
|-----------|----------|
| Duplicate document number | Sequence retry under lock; user sees a safe error if exhausted |
| Duplicate posting | Idempotency key returns existing posting; no second ledger entry |
| Insufficient stock | Reject posting with available/reserved breakdown; suggest transfer or backorder |
| Unauthorized warehouse | Policy denies; service throws `AuthorizationException`; activity log records attempt |
| Approval conflict (self) | Engine blocks action; requires delegate or different approver |
| Rejected material | Divert to quarantine; GRN accepts only passed quantities |
| Transfer discrepancy | Remain in transit; reconciliation workflow required |
| Failed integration | Outbox retries with backoff; alert after max attempts; no business rollback |
| Concurrent count movement | Snapshot + movement window reconciles; no lost updates |
