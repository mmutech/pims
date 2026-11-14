# 12 — UI Navigation and Screen Specification

## 1. Design language

- Enterprise SaaS: neutral zinc surfaces, one accent colour, dense but readable tables, clear status badges, no decorative clutter.
- Flux UI components + Tailwind 4; Inter font; light and dark modes via Flux appearance.
- Every screen: page title, short purpose line, primary action, search/filter bar, table or empty state, pagination.
- Desktop-first, fully usable at 360 px. Sidebar collapses to an overlay on mobile; tables scroll horizontally.
- Keyboard: all controls reachable, visible focus rings, `Esc` closes modals, `Enter` submits forms.
- Confirmation required for deletes, approvals, posting, reversals, and access revocation.
- No destructive action is one click; dialogs state the consequence.

## 2. Application shell

```
┌──────────────────────────────────────────────────────────────┐
│ Sidebar                     │ Header (breadcrumbs, user menu) │
│  Brand                      ├────────────────────────────────┤
│  Dashboard                  │ Page header + actions          │
│  Organization               │ Filters / summary cards        │
│  Master Data                │ Table / detail                 │
│  Procurement (phase badge)  │                                │
│  Warehouse                  │                                │
│  Distribution               │                                │
│  Inventory Control (badge)  │                                │
│  Approvals (phase badge)    │                                │
│  Reports (phase badge)      │                                │
│  Administration             │                                │
└──────────────────────────────┴────────────────────────────────┘
```

- Sidebar groups mirror the required navigation. Future-phase items are visible but disabled with a phase badge; implemented items are links.
- Header: sidebar toggle, breadcrumbs, global search (future phase), appearance toggle, user dropdown (profile, logout).
- Footer: app version and environment badge.

## 3. Navigation map

| Group | Item | Route | Phase |
|-------|------|-------|-------|
| Dashboard | Dashboard | `dashboard` | 1 |
| Organization | Business Units | `organization.business-units.index` | 1 |
| Organization | Departments | `organization.departments.index` | 1 |
| Organization | Cost Centres | `organization.cost-centers.index` | 1 |
| Organization | Projects | `organization.projects.index` | 1 |
| Master Data | Item Categories | `catalog.categories.index` | 1 |
| Master Data | Items | `catalog.items.index` | 1 |
| Master Data | Units of Measurement | `catalog.units.index` | 1 |
| Master Data | Suppliers | `supplier.suppliers.index` | 1 |
| Procurement | Purchase Requisitions | `procurement.requisitions.index` | 3 (implemented) |
| Procurement | RFQs | `procurement.rfqs.index` | 3 (implemented) |
| Procurement | Quotations | `procurement.quotations.index` | 3 (implemented) |
| Procurement | Purchase Orders | `procurement.orders.index` | 3 (implemented) |
| Warehouse | Warehouses | `warehouse.warehouses.index` | 1 |
| Warehouse | Goods Receiving (SRA) | `receiving.sra.index` | 4 (implemented) |
| Warehouse | Quality Inspection | `receiving.sra.show` (inspection panel) | 4 (implemented) |
| Warehouse | Stock Balances | `inventory.balances.index` | 5 (implemented) |
| Warehouse | Stock Ledger | `inventory.transactions.index` | 5 (implemented) |
| Warehouse | Goods Received Notes | `receiving.grn.index` | 4 (implemented) |
| Warehouse | Supplier Returns | `receiving.returns.index` | 4 (implemented) |
| Distribution | Material Requisitions | `distribution.requisitions.index` | 6 (implemented) |
| Distribution | Stock Issues | `distribution.issues.index` | 6 (implemented) |
| Distribution | Stock Transfers | `distribution.transfers.index` | 6 (implemented) |
| Distribution | Material Returns | `distribution.returns.index` | 6 (implemented) |
| Distribution | Project Consumption | `distribution.project-consumption.index` | 6 (implemented) |
| Inventory Control | Stock Adjustments | `stocktaking.adjustments.index` | 7 (implemented) |
| Inventory Control | Stocktaking | `stocktaking.counts.index` | 7 (implemented) |
| Inventory Control | Reconciliation | `stocktaking.reconciliations.index` | 7 (implemented) |
| Inventory Control | Inventory Valuation | — | 5 |
| Approvals | My Approvals | `approvals.index` | 2 (implemented) |
| Approvals | Approval History | `approvals.history` | 2 (implemented) |
| Approvals | Delegations | `delegations.index` | 2 (implemented) |
| Approvals | Workflows | `workflows.index` | 2 (implemented) |
| Reports | Inventory Reports | — | 8 |
| Reports | Procurement Reports | — | 8 |
| Reports | Warehouse Reports | — | 8 |
| Reports | Project Reports | — | 8 |
| Reports | Audit Reports | `audit.index` | 1 (activity log) |
| Administration | Users | `administration.users.index` | 1 |
| Administration | Roles & Permissions | `administration.roles.index` | 1 |
| Administration | Document Numbering | `administration.document-sequences.index` | 1 |
| Administration | System Settings | `administration.settings.index` | 1 |
| Administration | Audit Logs | `audit.index` | 1 |

## 4. Phase 1 screens

### 4.1 Dashboard

Summary cards: warehouses, items, suppliers, projects, users, active stock dimensions (0 until Phase 5), pending approvals (0 until Phase 2). Foundation activity list (last 8 changes), quick links, organization snapshot. No fabricated inventory values before the ledger exists; inventory cards show "Available from Phase 5".

### 4.2 Master data list pattern

Each list screen (Business Units, Departments, Cost Centres, Projects, Warehouses, Categories, Units, Items, Suppliers, Users, Roles):

- Header: title, count, primary "New …" button (permission-gated).
- Filter bar: search (code/name), active status, organization filters where relevant, reset.
- Table columns: code, name, scope, status, updated, actions.
- Row actions: edit, deactivate/delete (permission-gated), detail where applicable.
- Pagination: 10/25/50 per page.
- Empty state: explanation + primary action.
- Loading: skeleton rows; buttons disabled while saving.
- Errors: inline field errors, toast for domain errors.

### 4.3 Business Unit form

Code, name, description, active.

### 4.4 Department / Cost Centre forms

Code, name, description, active; the cost centre adds an optional department.

### 4.5 Project form

Code, name, department, cost centre, manager, start/end dates, status, budget, currency, description. Validation: end ≥ start; budget ≥ 0. Detail drawer lists members and allows add/remove (permission `organization.project_members.manage`).

### 4.6 Warehouse list and detail

List: code, name, type, business unit, manager, active.
Detail screen (`warehouse.warehouses.show`): header summary; tabs:
- **Locations**: CRUD table (code, name, type, active).
- **Bins**: select location, CRUD table (code, name, capacity, active).
- **Access**: user, access level, granted by/at, revoke action; grant form with user search and level.
- **Overview**: address/contact/manager.

### 4.7 Item Categories

Tree-aware list (parent name), code, name, tracking defaults (batch/serial/inspection), active. Form includes parent select (prevent self/descendant), defaults.

### 4.8 Units of Measurement

Code, name, symbol, type, decimal places, active. Decimal places 0–6.

### 4.9 Items

List: code, name, category, tracking type, base UoM, reorder level, standard cost, active.
Form sections: identity (code, name, category, brand, model, MPN, description), tracking (type, quality inspection, shelf life), stocking (base UoM, min/max/reorder, standard cost, valuation), specifications (repeatable attribute/value/UoM), units (additional UoM + conversion factor), batches/serials summary.
Validation: max ≥ min; reorder ≥ 0; standard cost ≥ 0; conversion > 0; tracking requirements consistent with category defaults.

### 4.10 Suppliers

List: code, name, status, approval, rating, tax ID, city, updated.
Form sections: identity, contact, address, notes; approval action (permission-gated) with reason; detail tabs for contacts, addresses, documents, evaluations.
Validation: code unique; tax ID unique when present; email format; rating 0–5; document file type/size.

### 4.11 Users

List: name, employee number, email, role(s), department, status, last login.
Form: name, email, employee number, phone, job title, password (create/reset), department/cost centre, roles multi-select, warehouse access grants, status.
Rules: cannot deactivate own account; cannot remove own Super Administrator role; email/employee number unique.

### 4.12 Roles & Permissions

List roles with user count and system flag. Editor: role name, permission matrix grouped by domain with select-all per group, system-role lock. Changes logged.

### 4.13 Document Numbering

List sequences by type/year: prefix, next number, padding, reset policy, preview of next number. Editing next number requires confirmation and is audited.

### 4.14 System Settings

Grouped key/value editor (general, inventory, procurement, notifications, security). Encrypted values masked; changing a secret requires re-entry.

### 4.15 Audit Log

Filter by log name, event, causer, subject type, date range. Read-only table with expandable properties diff. Downloadable audit exports are provided by the Phase 8 Audit reports screen (`/reports/audit`).

## 4A. Phase 2 approval screens (implemented)

The sidebar shows an expandable **Approvals** group to users with `workflow.requests.view`: My Approvals, History, Delegations, and Workflows (the last only with `workflow.flows.view`). Non-global users only see requests they raised, are assigned to, or inherit through an active delegation; override holders and auditors see all.

### 4A.1 My Approvals (`/approvals`)

- Header with total, overdue, and blocked counts; debounced search over document type, document number, and requester.
- Table: document, requester, current stage, received/required approvals, SLA state, requested time, action.
- Blocked stages are visible only to `workflow.override` holders; no bulk approval is offered.
- Empty state explains that nothing needs a decision.

### 4A.2 Approval Detail (`/approvals/{request}`)

- Document summary from request metadata, status badge, requester, and submitted date.
- Stage timeline with runtime name, mode, assignee list, per-assignee status, and completion time.
- Append-only action timeline (actor, action, comment, timestamp, delegated-from metadata).
- Comment box; Approve, Reject, and Return for Correction actions. Reject/Return require a comment.
- Cancel request and Delegate authority modals, gated by policy.
- Approver actions are confirmed; no editing or deleting of prior actions.

### 4A.3 Approval History (`/approvals/history`)

- Debounced search plus status and document-type filters; clear-filters action.
- Columns: document, requester, status, current stage, requested, completed.
- Read-only; every row links to the detail screen. Results are permanent even after flow changes.

### 4A.4 Workflows (`/workflows`, `/workflows/create`, `/workflows/{flow}/edit`)

- Index with search and active/inactive filter; columns for stages, version, request count, and status.
- Row actions: edit, activate/deactivate, delete. Delete is blocked while pending requests exist.
- Full-page builder: workflow details, then stacked stage cards with reorder controls.
- Each stage exposes name, approval mode, required approvals, SLA hours, amount band, prevention of self-approval, final-stage flag, escalation role/user, conditions, and assignees.
- Condition rows accept a field, operator, comma-separated value list/range, and logical group. Groups are ANDed; conditions within a group are ORed.
- Assignee rows choose a supported type; role and user pickers appear conditionally. Unsupported manager types are not offered.
- Server-side and client-side validation cover duplicate document types, amount-band ordering, list/range shapes, missing roles/users, and missing stages.

### 4A.5 Delegations (`/delegations`)

- Search by delegator, delegate, or document type; filter by active, scheduled, and ended.
- Create modal: delegate picker limited to active users holding `workflow.act`, optional document type, start/end dates, reason.
- Self-delegation and overlapping delegations are rejected with inline errors.
- Cancel action is available to the delegator and workflow administrators while the delegation is active.

### 4A.6 Dashboard

Adds an **Awaiting Approval** card for users who can act, linking to My Approvals; the value comes from `ApprovalEngine::pendingFor()`.

## 4B. Phase 3 procurement screens (implemented)

The sidebar shows a **Procurement** group to users holding any procurement view permission. Lists are scoped to the creator for requisitions, RFQs, quotations, and evaluations; purchase orders are scoped to the creator or delivery-warehouse access. All actions also check policies and domain services.

### 4B.1 Purchase Requisitions (`/procurement/requisitions`)

- Index: debounced search over document number, justification, requester, and department; status filter; pagination.
- Columns: document number, department, requester, required-by, total estimate, approved amount, status, created.
- Row actions: view; edit and delete only while editable (`draft` or `returned`).
- Form: department, business unit, cost centre/project, suggested supplier, required-by, justification, and repeatable line rows (item, UoM, requested quantity, estimated unit price, specification, required-by). Business unit remains because a requisition has no destination warehouse.
- Show: header summary, line table with requested/approved/converted quantities, approval summary, and action buttons (submit, cancel, edit, source RFQ).
- Submit sends the document to the approval engine; cancellation requires a reason.

### 4B.2 RFQs (`/procurement/rfqs`)

- Index: search by number/title/PR, status filter, closing date, supplier count, quotation count.
- Form: source requisition (approved/partially approved), title, description, closing date, invited suppliers, and line rows seeded from the remaining requisition balance.
- Show: RFQ header, invited suppliers and response state, line table, linked quotations with totals, and actions (issue, close, cancel, create quotation, evaluate).
- Issue requires at least one line, at least one supplier, and a future closing date; cancellation releases converted requisition quantities.

### 4B.3 Supplier Quotations (`/procurement/quotations`)

- Index: search by number/supplier/RFQ, status filter, supplier, RFQ, total amount.
- Form: RFQ selector, supplier selector limited to invited suppliers, validity, lead time, payment/warranty terms, and per-RFQ-line quantities, unit prices, tax, and discount.
- Show: header terms, line table, computed subtotal/tax/total, invitation response state, and actions (submit, withdraw, edit, delete while draft).
- Totals are recalculated server-side with fixed-point decimals; the UI never computes persisted totals.

### 4B.4 Quotation Evaluations (`/procurement/evaluations/{evaluation}`)

- Detail screen opened from an RFQ or quotation.
- Shows the RFQ, all submitted quotations side by side, weighted score rows by criterion, and the recommended quotation.
- Actions: edit scores and recommendation while draft, submit for approval, cancel with a reason.
- Approval awards the RFQ and updates quotation statuses; the screen becomes read-only.

### 4B.5 Purchase Orders (`/procurement/orders`)

- Index: search by number/supplier/PR, status and supplier filters, delivery warehouse, totals, version.
- Form: supplier, delivery warehouse, source accepted quotation or approved PR, currency, payment terms, incoterms, expected delivery, and line rows. The delivery warehouse determines business-unit grouping.
- Selecting a source rehydrates line items from the quotation or the requisition balance.
- Show: header, sourcing references, line table (ordered/received/rejected), totals, version, approval summary, and actions (submit, cancel, close, amend, edit/delete while draft).
- Amendment modal captures a reason and amended quantities/prices per line; the resulting amendment is numbered, reviewed for received-quantity limits, and sent for approval.
- Each amendment row shows its status and an `Open approval` link when an approval request exists, so pending amendment decisions are reachable directly from the order.
- No stock is received from this screen; receipt entry belongs to Phase 4.

## 4C. Phase 4/5 receiving and inventory screens (implemented)

The sidebar shows **Receiving** (SRA, GRN, Supplier Returns) and **Inventory** (Balances, Transactions) groups to users holding the corresponding view permissions. Lists are scoped to the user's accessible warehouses.

### 4C.1 Store Receipt Advices (`/receiving/sra`)

- Index: debounced search over document number, PO, supplier, delivery note; status filter; pagination.
- Columns: SRA number, purchase order, supplier, warehouse, expected date, status, received by, created.
- Row actions: view; edit/delete only while draft; submit while draft.
- Form: purchase order selector (approved/partially received), expected delivery date, vehicle/driver/delivery-note fields, and line rows pre-filled from outstanding PO quantities (expected/delivered quantity, batch number, serial numbers).
- Show: header, line comparison (expected vs delivered), delivery verification summary, quality inspection summary, linked GRN, and action buttons (submit, verify, start inspection, create GRN, cancel).
- Verification panel records per-line verified/damaged/short quantities; the SRA moves to `PendingInspection` only when a line requires inspection, otherwise `Verified`.
- Inspection panel (Quality Inspector/Warehouse Manager) records per-line inspected/accepted/rejected/quarantined quantities and findings; the SRA becomes `Inspected`.
- Creating a GRN requires `Verified` or `Inspected`; posting is gated by `receiving.grn.post`.

### 4C.2 Goods Received Notes (`/receiving/grn`)

- Index: search by GRN/PO/supplier; status filter; posted date, warehouse, accepted/rejected totals.
- Show: header, SRA/PO references, accepted/rejected line table, linked stock transaction, posting/reversal audit, and actions (post while draft, reverse while posted, cancel while draft).
- Posting shows the resulting stock transaction reference and posted-by/at; reversal requires a reason and confirms stock restoration.

### 4C.3 Supplier Returns (`/receiving/returns`)

- Index: search by return number/supplier/GRN; status filter; warehouse, reason, posted date.
- Form: supplier, warehouse, optional source GRN, reason, and line rows (item, UoM, quantity, unit cost, batch/serial where tracked). Lines default from the selected GRN's accepted quantities.
- Show: header, source GRN, line table, stock transaction reference, and actions (post while draft, cancel while draft, edit while draft/assessed).
- Posting sends `ReturnOut` through `InventoryPostingService`; insufficient availability is rejected with an actionable domain error and the return stays draft.

### 4C.4 Inventory Balances (`/inventory/balances`)

- Read-only list of balances per warehouse/stock dimension/item/batch.
- Columns: warehouse, dimension/location/bin, item, batch, on-hand, reserved, available, unit cost, updated.
- Filters: warehouse, item search, and an in-stock-only toggle; pagination. Availability respects warehouse access grants.

### 4C.5 Stock Transactions (`/inventory/transactions`)

- Read-only list of posted stock transactions.
- Columns: transaction number, type, source document, warehouse, status (posted/reversed), posted by/at, line count.
- Source documents are resolved to their human-readable number (for example `GRN/2026/00001`) with a morph fallback.
- Ledger rows themselves are append-only and are not editable from the UI. A dedicated transaction detail screen remains deferred.

## 4D. Phase 6 distribution screens (implemented)

The sidebar shows a **Distribution** group — Material Requisitions, Stock Issues, Stock Transfers, Material Returns, and Project Consumption — to users holding the matching view permission. Lists are scoped to the requester or to warehouses the user can access. All 16 `distribution.*` routes are permission- and policy-guarded.

### 4D.1 Material Requisitions (`/distribution/requisitions`)

- Index: debounced search over number, purpose, and department; columns for number, department, project, destination warehouse, required-by, line count, status, and row actions (view, edit while draft/returned, delete while draft).
- Form: requesting department, cost centre/project, destination warehouse, purpose, required-by, priority, and repeatable line rows (item, UoM, requested quantity, notes). Duplicate items are rejected.
- Show: header summary; approval panel with an **Open approval** link; line table with requested, approved, reserved, issued, and outstanding quantities; **Save approved quantities** for approvers (partial approval); open-backorder table; stock-issue list; actions to submit, reserve stock, issue stock, edit, and cancel.
- **Reserve stock** allocates available stock across warehouse balances; quantities that cannot be covered become open backorders. Cancellation releases open reservations and backorders after confirmation.
- Submit sends the requisition to the `material_requisition` approval flow. Partial approval is reflected by reduced `approved_quantity` and the `partially_approved` status.

### 4D.2 Stock Issues (`/distribution/issues`)

- Index: debounced search over issue and requisition number; columns for number, requisition, recipient, warehouse, issue date, acknowledged state, status, and actions (view, delete while draft).
- **Issue from requisition** opens the issue form for the latest open reservation. If no reservation exists, the form warns the user to reserve stock first.
- Form: recipient, issuer, issue date, remarks, and reserved lines (item, unit, batch, dimension, reserved quantity, issue-now quantity). Only reserved quantities can be issued; reducing a line leaves the balance reserved.
- Show: issue summary, issued lines with unit cost and total cost, acknowledgement panel, and actions to post, acknowledge, reverse, cancel, or delete a draft.
- Posting deducts stock through the posting engine, consumes the reservation, and is confirmed with a warning that it cannot be deleted. Reversal posts a compensating entry and keeps the original history.
- **Acknowledge receipt** is shown to the recipient only; it records comment, signature path, IP address, and timestamp.

### 4D.3 Stock Transfers (`/distribution/transfers`)

- Index: debounced search over number and reason; columns for number, source/destination warehouse, requester, required-by, line count, status, and actions (view, delete while draft).
- Form: source and destination warehouse, reason, required-by, and repeatable line rows (item, UoM, requested quantity). The service rejects identical source and destination warehouses.
- Show: summary, approval panel, transfer line table (requested/approved/dispatched/received/variance), dispatch history, and discrepancy panel.
- Actions: submit (approval flow `stock_transfer`), **Dispatch**, **Receive**, **Resolve** per pending discrepancy, cancel, and delete while draft.
- Dispatch modal: outstanding quantities per line, optional batch per line, vehicle number, driver, and dispatch date. Posting issues from the source warehouse and parks stock in transit.
- Receive modal: outstanding, good, damaged, and missing quantities per dispatch line. Good units enter available destination stock, damaged units enter destination quarantine, and missing units remain in transit with a discrepancy record.
- Discrepancy resolution modal: choose `return_to_source`, `write_off`, `claim`, or `replacement` with notes. Return-to-source and write-off post stock movements; claim/replacement only record the terminal outcome.

### 4D.4 Material Returns (`/distribution/returns`)

- Index: debounced search over number and reason; columns for number, type, original issue, warehouse, returned-by, line count, status, and actions (view, delete while draft).
- Form: return type (project/department), original issue, warehouse, returned-by, reason, and returnable lines limited to issued quantity less prior returns, with batch and notes per line.
- Show: summary, assessment rule, line table (returned, condition, accepted, rejected, assessment), and actions to edit, assess, post, cancel, or delete.
- **Assess condition** modal: per line condition (`good`, `damaged`, `defective`, `quarantined`, `repairable`, `disposal_pending`), disposition (`return_to_stock`, `quarantine`, `repair`, `dispose`, `return_to_supplier`), accepted/rejected quantities that must sum to the returned quantity, findings, and evidence path.
- Posting sends only accepted good-condition quantities back to an issue-available warehouse dimension. Damaged/defective/quarantined/repairable quantities post to a non-available quarantine dimension; disposal and supplier-return lines post no stock.
- Supplier returns are not created here; they use the Phase 4 supplier-return screen.

### 4D.5 Project Consumption (`/distribution/project-consumption`)

- Filter by project and optional from/to dates; **Apply filters** recalculates the summary.
- KPI cards show total issued quantity and total issued value for posted issues only; draft, cancelled, and reversed issues are excluded.
- Consumption-by-item table lists item, code, quantity, and value. Values come from `stock_issue_items.total_cost`, captured at posting time.
- Read-only; no stock or cost edits from this screen.

## 4E. Phase 7 inventory-control screens (implemented)

The sidebar shows an **Inventory Control** group — Stock Adjustments, Stock Counts, and Reconciliations — to users holding the matching view permission. All 9 `stocktaking.*` routes are permission- and policy-guarded, queries are warehouse-scoped through `scopeVisibleTo`, and every mutation delegates to the stocktaking services.

### 4E.1 Stock Adjustments (`/stocktaking/adjustments`)

- Index: debounced search over number, reason, and reason code; status and type filters; columns for number, type, warehouse, reason code, value, line count, and status; row actions open the document or delete a draft.
- Create/Edit: warehouse, adjustment type, reason code, and reason header plus repeatable line rows (item, unit, quantity, unit cost, batch, line note). Quantities are entered as positive values and the adjustment type sets the direction.
- Detail: document summary (type, reason, warehouse, total value, linked stock count, creator, approval/posting actors, cancellation and reversal links), approval panel (status, requested date, link to the approval request, or a threshold explanation), an SoD explainer, and the signed adjustment lines.
- Actions: Submit, Post, Reverse (modal with reason), Cancel (modal with reason), Delete (drafts only), Edit (drafts only). Reversal creates a new equal and opposite document; the reversal list links to it.
- Below-threshold adjustments can be posted directly; at/above the threshold the document must be approved first.

### 4E.2 Stock Counts (`/stocktaking/counts`)

- Index: debounced search over number and scope, status and type filters; columns for number, type, warehouse, scope, snapshot time, line/variance counts, and status; actions open or delete a draft.
- Create/Edit: warehouse, count type, quantity/value tolerances, scope description, and OR-combined scope rows (location, category, item, include zero balances). A blank scope covers the whole warehouse. Editing is draft-only.
- Detail: summary (type, warehouse, snapshot, scheduler, tolerances, completion/cancellation), scope badges, reconciliation panel, and the count-lines table.
- Blind counts display a callout and hide system quantity/variance columns while the status is `counting`.
- Count line actions open a recount modal. Bulk "Record counts" opens a modal with one quantity and note field per line; blank lines are skipped.
- Actions: Start (snapshot), Record counts, Create adjustments (one per open variance), Complete (requires a posted reconciliation), Cancel, Delete (drafts only), Edit (drafts only).
- Variance analysis table shows type, quantity, value, tolerance breach, resolution, and the linked adjustment. Count adjustments table lists generated adjustments and their statuses.
- Reconciliation panel shows snapshot/movements/adjustments/ledger/difference, and offers Prepare/Recalculate, Approve, and Post depending on state and permission.

### 4E.3 Stock Reconciliations (`/stocktaking/reconciliations`)

- Read-only list of `REC` balancing statements with search over reconciliation and count number and a status filter.
- Columns: number, linked count, warehouse, snapshot, movements, adjustments, ledger, difference, status, and an action to open the count.
- Reconciliation state changes (prepare, approve, post) happen on the count detail screen so the equation and its source lines stay in context. A dedicated reconciliation detail screen is deferred.

## 4F. Phase 8 reporting screens (implemented)

The sidebar shows a **Reports** group — Report Centre, Inventory, Procurement, Warehouses, Projects, and Audit — filtered by the matching `reports.*.view` permission. Every screen is a full-page Livewire component using the shared `InteractsWithReports` trait; `App\Livewire\Reports\Index` lists the catalogue entries the user can open.

Routes: `reports.index`, `reports.inventory`, `reports.procurement`, `reports.warehouses`, `reports.projects`, `reports.audit`, and `reports.export/{report}/{format}` (`xlsx|csv|pdf`). The export route is outside the Livewire group and re-checks the report's `reports.*.export` permission.

### 4F.1 Report Centre (`/reports`)

- Grouped cards for Inventory, Procurement, Warehouse, Project, and Audit.
- Each card shows the reports in the group that the current role may view, with key, title, and description.
- Empty state explains that an administrator can grant reporting permissions.

### 4F.2 Inventory Reports (`/reports/inventory`)

- Report selector: valuation, movement summary, slow/non-moving stock, expiring stock, ledger reconciliation.
- Filters: search, item, category, warehouse; date range for movements; idle window for slow-moving; expiry window/from-date for expiring; mismatches-only for reconciliation.
- Summary cards adapt per report (items, on-hand quantity, value, movement totals, flagged lines, batch balances, mismatches and status).
- Table columns and decimal formatting come from `ReportCatalogue`.

### 4F.3 Procurement Reports (`/reports/procurement`)

- Report selector: purchase order register and supplier spend.
- Filters: search, supplier, warehouse (delivery warehouse), status, and date range on order creation date.
- Summary cards show order count, supplier count, and committed value.
- Rows respect `PurchaseOrder::scopeVisibleTo()` so a procurement user only sees orders they created or orders delivered to a warehouse they can access.

### 4F.4 Warehouse Reports (`/reports/warehouses`)

- Report selector: utilisation and throughput.
- Filters: search, warehouse, item; date range for throughput.
- Summary cards show warehouses/items/on-hand/value or inbound/outbound/net quantity and value.
- Warehouse scope is applied before aggregation.

### 4F.5 Project Reports (`/reports/projects`)

- Report selector: project consumption.
- Filters: search, project, warehouse, item, category, date range on issue date.
- Summary cards show projects, issued quantity, and issued value.
- Only posted issues count; the report reads the distribution ledger and `stock_issue_items` totals.

### 4F.6 Audit Reports (`/reports/audit`)

- Report selector: activity trail and approval performance.
- Activity filters: search, module (`log_name`), subject type, and date range. Approval filters: status, search over document type, and date range.
- Activity rows show timestamp, module, event, description, actor, and subject; approval rows show document type, status, request count, oldest, and latest request timestamps.
- Audit screens are read-only evidence views.

### 4F.7 Exports and snapshots

- Excel (`xlsx`) and CSV downloads carry the same filtered rows and headings as the screen; PDF renders the same payload in a landscape A4 table. Downloads are capped by `config('reports.export_row_limit')` and the payload reports whether it was truncated.
- Decimal cells keep their fixed precision; Excel receives column-letter number formats from `ReportRows::numberFormats()`.
- Snapshots are captured by the `App\Jobs\Reporting\GenerateReportSnapshots` job daily at 23:45 and store the valuation, reconciliation, supplier-spend and warehouse-utilisation totals plus bounded sample rows in `report_snapshots`. `php artisan reports:snapshots --date=YYYY-MM-DD [--user=ID]` captures on demand.
- Dashboard KPI cards are permission-gated: inventory value/expiring/slow-moving, open purchase orders, project consumption MTD, and pending approvals appear only for roles holding the corresponding report view permission.

## 5. Interaction standards

- **Create/Edit**: Flux modal for simple entities; full-page or large modal for items, suppliers, users.
- **Delete**: confirmation modal naming the record; destructive button uses danger variant.
- **Status**: badges — green active/approved, amber pending, red suspended/rejected, zinc inactive.
- **Toasts**: success after save; domain error with code and actionable message.
- **Unsaved changes**: warn on close when dirty (future enhancement; Phase 1 disables backdrop close on dirty forms where feasible).
- **Search**: debounced 300 ms; server-side; resets pagination.
- **Filters**: URL-persisted where useful (`#[Url]`), reset button.
- **Bulk actions**: not offered for approvals or posting; safe bulk deactivate in later phases.

## 6. Responsive behaviour

| Breakpoint | Behaviour |
|------------|-----------|
| ≥ 1280 px | Persistent sidebar, 4-column cards, full tables |
| 768–1279 px | Collapsible sidebar, 2-column cards, horizontal table scroll |
| < 768 px | Overlay sidebar, stacked cards, stacked form fields, sticky primary action, tables scroll |

## 7. Accessibility and content

- Inputs have labels and descriptions; errors are linked with `aria-describedby`.
- Colour is never the only status signal (badge text included).
- Focus order follows visual order; modals trap focus and restore it on close.
- Currency and quantity formatting is locale-aware but stored/validated as decimals.
- Dates display as `DD MMM YYYY`; timestamps include timezone in detail views.
- Empty states explain why a list is empty and what to do next.

## 8. Screen acceptance checklist

- [ ] Permission-gated actions hidden and server-enforced.
- [ ] Organization/warehouse scope applied to queries.
- [ ] Search, filter, sort, pagination work with URL state.
- [ ] Create/edit validation covers uniqueness and decimal rules.
- [ ] Delete/deactivate blocked when dependants exist.
- [ ] Activity log entry created for every mutation.
- [ ] Mobile layout usable at 360 px.
- [ ] Loading, empty, error, and success states present.
- [ ] No business logic in Blade; no direct model writes from the component.
