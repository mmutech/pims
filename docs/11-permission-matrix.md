# 11 — Role and Permission Matrix

## 1. Permission naming

`{domain}.{resource}.{action}` — for example `organization.business_units.view`, `warehouse.warehouses.update`, `supplier.suppliers.approve`.

Actions: `view`, `create`, `update`, `delete`, `approve`, `manage`, `post`, `reverse`, `export`.

## 2. Phase 1 permissions (seeded)

### System

| Permission | Description |
|------------|-------------|
| `system.access.all` | Bypass organization scoping (not a substitute for module permissions) |
| `dashboard.view` | Access the dashboard |
| `audit.view` | View activity log and audit history |

### Administration

| Permission | Description |
|------------|-------------|
| `administration.users.view` | List/view users |
| `administration.users.create` | Create users |
| `administration.users.update` | Edit users, assign org and roles |
| `administration.users.delete` | Deactivate/delete users |
| `administration.roles.view` | List roles and permissions |
| `administration.roles.create` | Create roles |
| `administration.roles.update` | Edit roles and sync permissions |
| `administration.roles.delete` | Delete non-system roles |
| `administration.settings.view` | View system settings |
| `administration.settings.update` | Edit system settings |

### Organization

| Permission | Description |
|------------|-------------|
| `organization.business_units.view/create/update/delete` | Business units |
| `organization.departments.view/create/update/delete` | Departments |
| `organization.cost_centers.view/create/update/delete` | Cost centres |
| `organization.projects.view/create/update/delete` | Projects |
| `organization.project_members.manage` | Add/remove project members |

### Warehouse

| Permission | Description |
|------------|-------------|
| `warehouse.warehouses.view/create/update/delete` | Warehouse master data |
| `warehouse.locations.view/create/update/delete` | Storage locations |
| `warehouse.bins.view/create/update/delete` | Storage bins |
| `warehouse.access.manage` | Grant/revoke user warehouse access |

### Catalog

| Permission | Description |
|------------|-------------|
| `catalog.categories.view/create/update/delete` | Item categories |
| `catalog.units.view/create/update/delete` | Units of measure |
| `catalog.items.view/create/update/delete` | Items, units, specifications, batches, serials |

### Supplier

| Permission | Description |
|------------|-------------|
| `supplier.suppliers.view` | View suppliers and related records |
| `supplier.suppliers.create` | Register suppliers |
| `supplier.suppliers.update` | Edit suppliers, contacts, addresses, documents, evaluations |
| `supplier.suppliers.delete` | Deactivate suppliers |
| `supplier.suppliers.approve` | Approve/suspend/blacklist suppliers |

## 3. Phase 1 roles

| Role | Scope | Permissions |
|------|-------|-------------|
| Super Administrator | Global | All Phase 1 permissions + `Gate::before` bypass |
| System Administrator | Global | `system.access.all`, dashboard, audit, administration.*, all `.view` across org/warehouse/catalog/supplier |
| Executive | Global | `system.access.all`, dashboard, audit, all `.view` permissions |
| Procurement Manager | Home BU | supplier view/create/update/approve; catalog view; organization view; warehouse view; dashboard; audit view |
| Procurement Officer | Home BU | supplier view/create/update; catalog view; organization view; warehouse view; dashboard |
| Warehouse Manager | Assigned warehouses | warehouse view/create/update, locations/bins CRUD, access.manage; catalog view; organization view; supplier view; dashboard |
| Store Officer | Assigned warehouses | warehouse view, locations view, bins view; catalog view; organization view; dashboard; receiving create/verify/post; inventory views |
| Quality Inspector | Assigned warehouses | warehouse/catalog/organization view; receiving SRA/inspection view; inspections start/record; GRN view; inventory views |
| Project Manager | Home BU + member projects | organization projects view/update, project_members.manage; organization view; catalog view; warehouse view; dashboard |
| Department Requester | Home department | organization departments/projects view; catalog view; warehouse view; dashboard |
| Auditor | Global | `system.access.all`, dashboard, audit.view, all `.view` permissions |

`Super Administrator` is also granted every permission explicitly so the permission matrix UI is truthful; `Gate::before` remains a safety net.

### Phase 2 workflow additions (implemented)

| Permission | System Admin | Executive | Procurement Manager | Procurement Officer | Warehouse Manager | Project Manager | Department Requester | Auditor |
|------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `workflow.requests.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `workflow.act` | ✓ | ✓ | ✓ | — | ✓ | ✓ | — | — |
| `workflow.delegate` | ✓ | ✓ | ✓ | — | ✓ | ✓ | — | — |
| `workflow.escalate` | ✓ | ✓ | ✓ | — | — | — | — | — |
| `workflow.override` | ✓ | ✓ | — | — | — | — | — | — |
| `workflow.flows.view` | ✓ | ✓ | — | — | — | — | — | ✓ |
| `workflow.flows.create` | ✓ | — | — | — | — | — | — | — |
| `workflow.flows.update` | ✓ | — | — | — | — | — | — | — |
| `workflow.flows.delete` | ✓ | — | — | — | — | — | — | — |

`Super Administrator` receives every workflow permission through `Permissions::all()`. Request detail access is additionally scoped by policy to the requester, assignees, delegates, `workflow.override`, or `audit.view`. Workflow configuration is only writable by System Administrators; Executives can view flows but cannot edit them. Auditors can view flows and all requests but cannot act, delegate, escalate, or override.

### Phase 3 procurement permissions (implemented)

Permission groups: `procurement.requisitions.*`, `procurement.rfqs.*`, `procurement.quotations.*`, `procurement.orders.*`.

Actions seeded: requisitions `view/create/update/delete/submit/cancel`; RFQs `view/create/update/delete/issue/cancel`; quotations `view/create/update/delete/submit/evaluate`; orders `view/create/update/delete/approve/cancel/amend/close`.

| Permission | Sys Admin | Executive | Procurement Manager | Procurement Officer | Warehouse Manager | Project Manager | Dept Requester | Store Officer | Auditor |
|------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `procurement.requisitions.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| `procurement.requisitions.create` | — | — | ✓ | ✓ | — | ✓ | ✓ | — | — |
| `procurement.requisitions.update` | — | — | ✓ | ✓ | — | ✓ | ✓ | — | — |
| `procurement.requisitions.delete` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.requisitions.submit` | — | — | ✓ | ✓ | — | ✓ | ✓ | — | — |
| `procurement.requisitions.cancel` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.rfqs.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| `procurement.rfqs.create` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.rfqs.update` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.rfqs.delete` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.rfqs.issue` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.rfqs.cancel` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.quotations.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| `procurement.quotations.create` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.quotations.update` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.quotations.delete` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.quotations.submit` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.quotations.evaluate` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.orders.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| `procurement.orders.create` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.orders.update` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.orders.delete` | — | — | ✓ | ✓ | — | — | — | — | — |
| `procurement.orders.approve` | — | — | ✓ | — | — | — | — | — | — |
| `procurement.orders.cancel` | — | — | ✓ | — | — | — | — | — | — |
| `procurement.orders.amend` | — | — | ✓ | — | — | — | — | — | — |
| `procurement.orders.close` | — | — | ✓ | — | — | — | — | — | — |

`Super Administrator` receives every procurement permission through `Permissions::all()`. Approving a procurement document additionally requires `workflow.act` and stage assignment; the `procurement.orders.approve` permission does not bypass the engine. Documents are then scoped by creator or the warehouse that the document is bound to (see §4).

### Phase 4/5 receiving and inventory permissions (implemented)

Permission groups: `receiving.sra.*`, `receiving.inspections.*`, `receiving.grn.*`, `receiving.returns.*`, and `inventory.*`.

| Permission | Sys Admin | Executive | Procurement Manager | Procurement Officer | Warehouse Manager | Project Manager | Dept Requester | Store Officer | Quality Inspector | Auditor |
|------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `receiving.sra.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `receiving.sra.create` | | | | | Y | | | Y | | |
| `receiving.sra.update` | | | | | Y | | | Y | | |
| `receiving.sra.delete` | | | | | Y | | | | | |
| `receiving.sra.submit` | | | | | Y | | | Y | | |
| `receiving.sra.cancel` | | | | | Y | | | | | |
| `receiving.sra.verify` | | | | | Y | | | Y | | |
| `receiving.inspections.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `receiving.inspections.start` | | | | | Y | | | | Y | |
| `receiving.inspections.record` | | | | | Y | | | | Y | |
| `receiving.grn.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `receiving.grn.create` | | | | | Y | | | Y | | |
| `receiving.grn.post` | | | | | Y | | | Y | | |
| `receiving.grn.cancel` | | | | | Y | | | | | |
| `receiving.grn.reverse` | | | | | Y | | | | | |
| `receiving.returns.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `receiving.returns.create` | | | | | Y | | | Y | | |
| `receiving.returns.update` | | | | | Y | | | Y | | |
| `receiving.returns.delete` | | | | | Y | | | | | |
| `receiving.returns.post` | | | | | Y | | | Y | | |
| `receiving.returns.cancel` | | | | | Y | | | | | |
| `inventory.balances.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `inventory.ledger.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `inventory.transactions.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |

`Super Administrator` receives every permission through `Permissions::all()`. Global roles (`System Administrator`, `Executive`, `Auditor`) receive all `.view` permissions via `Permissions::viewPermissions()` but no receiving/inventory write abilities. Warehouse access (`view`/`operate`/`approve`/`manage`) is still required for warehouse-scoped operations; module permissions alone do not grant stock access.

Segregation of duties for Phase 4: the same user may prepare and post a GRN in the default configuration, but `receiving.grn.reverse` is held only by Warehouse Manager. Inspection is separate from posting: a Store Officer can create and post receipts but cannot record inspections (`Quality Inspector` or Warehouse Manager only).

### Phase 6 distribution permissions (implemented)

Permission groups: `requisition.requests.*`, `requisition.issues.*`, `distribution.transfers.*`, `distribution.returns.*`, `distribution.project_consumption.view`.

| Permission | Sys Admin | Executive | Procurement Manager | Procurement Officer | Warehouse Manager | Project Manager | Dept Requester | Store Officer | Quality Inspector | Auditor |
|------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `requisition.requests.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `requisition.requests.create` | | | | | Y | Y | Y | Y | | |
| `requisition.requests.update` | | | | | Y | Y | Y | Y | | |
| `requisition.requests.delete` | | | | | Y | Y | Y | Y | | |
| `requisition.requests.submit` | | | | | Y | Y | Y | Y | | |
| `requisition.requests.cancel` | | | | | Y | Y | Y | Y | | |
| `requisition.requests.reserve` | | | | | Y | | | Y | | |
| `requisition.issues.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `requisition.issues.create` | | | | | Y | | | Y | | |
| `requisition.issues.update` | | | | | Y | | | Y | | |
| `requisition.issues.delete` | | | | | Y | | | Y | | |
| `requisition.issues.post` | | | | | Y | | | Y | | |
| `requisition.issues.reverse` | | | | | Y | | | Y | | |
| `requisition.issues.cancel` | | | | | Y | | | Y | | |
| `requisition.issues.acknowledge` | | | | | Y | Y | Y | Y | | |
| `distribution.transfers.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `distribution.transfers.create` | | | | | Y | | | Y | | |
| `distribution.transfers.update` | | | | | Y | | | Y | | |
| `distribution.transfers.delete` | | | | | Y | | | Y | | |
| `distribution.transfers.submit` | | | | | Y | | | Y | | |
| `distribution.transfers.cancel` | | | | | Y | | | Y | | |
| `distribution.transfers.dispatch` | | | | | Y | | | Y | | |
| `distribution.transfers.receive` | | | | | Y | | | Y | | |
| `distribution.transfers.resolve` | | | | | Y | | | Y | | |
| `distribution.returns.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `distribution.returns.create` | | | | | Y | Y | Y | Y | | |
| `distribution.returns.update` | | | | | Y | Y | Y | Y | | |
| `distribution.returns.delete` | | | | | Y | Y | Y | Y | | |
| `distribution.returns.cancel` | | | | | Y | Y | Y | Y | | |
| `distribution.returns.assess` | | | | | Y | | | Y | Y | |
| `distribution.returns.post` | | | | | Y | | | Y | | |
| `distribution.project_consumption.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |

`Super Administrator` receives every permission through `Permissions::all()`. Global roles (`System Administrator`, `Executive`, `Auditor`) receive the distribution view permissions but no write abilities. Project Managers and Department Requesters raise requisitions and returns and acknowledge receipt; Store Officers and Warehouse Managers hold the store-operation set (reserve, issue, transfer, assess, post); Quality Inspectors can assess returns but cannot post them.

Segregation of duties for Phase 6:

- **Issue acknowledgement is recipient-only.** The issuer cannot acknowledge on the recipient's behalf. `workflow.override` is the audited exception. Acknowledgement is recorded once per issue.
- **Return assessment excludes the returner and creator.** The same user may not assess their own return unless holding `workflow.override`; damaged/defective material still cannot be returned to available stock regardless of override.
- **Transfer dispatch and receipt are warehouse-scoped.** Dispatching requires `operate` access at the source warehouse; receiving requires `operate` access at the destination. Source and destination must differ (database CHECK).
- **Posted issues and transfers are immutable.** Corrections use reversal/cancellation and, for transfers, discrepancy resolution; there is no delete of a posted document.
- Reservation and issue posting may both be performed by a Store Officer in the default configuration; the restrictive controls are the approved requisition, the active reservation, and the reserved-quantity ceiling.

### Phase 7 inventory-control permissions (implemented)

Permission groups: `inventory.adjustments.*`, `stocktaking.counts.*`, `stocktaking.reconciliations.*`, and `stocktaking.reconcile`.

| Permission | Sys Admin | Executive | Procurement Manager | Procurement Officer | Warehouse Manager | Project Manager | Dept Requester | Store Officer | Quality Inspector | Auditor |
|------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `inventory.adjustments.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `inventory.adjustments.create` | | | | | Y | | | Y | | |
| `inventory.adjustments.update` | | | | | Y | | | Y | | |
| `inventory.adjustments.delete` | | | | | Y | | | Y | | |
| `inventory.adjustments.submit` | | | | | Y | | | Y | | |
| `inventory.adjustments.cancel` | | | | | Y | | | Y | | |
| `inventory.adjustments.post` | | | | | Y | | | | | |
| `inventory.adjustments.reverse` | | | | | Y | | | | | |
| `stocktaking.counts.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `stocktaking.counts.create` | | | | | Y | | | Y | | |
| `stocktaking.counts.update` | | | | | Y | | | Y | | |
| `stocktaking.counts.delete` | | | | | Y | | | Y | | |
| `stocktaking.counts.start` | | | | | Y | | | Y | | |
| `stocktaking.counts.count` | | | | | Y | | | Y | Y | |
| `stocktaking.counts.recount` | | | | | Y | | | Y | | |
| `stocktaking.counts.analyse` | | | | | Y | | | Y | | |
| `stocktaking.counts.cancel` | | | | | Y | | | Y | | |
| `stocktaking.counts.adjust` | | | | | Y | | | | | |
| `stocktaking.counts.complete` | | | | | Y | | | | | |
| `stocktaking.reconciliations.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `stocktaking.reconciliations.prepare` | | | | | Y | | | Y | | |
| `stocktaking.reconcile` | | | | | Y | | | Y | | |
| `stocktaking.reconciliations.approve` | | | | | Y | | | | | |
| `stocktaking.reconciliations.post` | | | | | Y | | | | | |

`Super Administrator` receives every permission through `Permissions::all()`. Global roles (`System Administrator`, `Executive`, `Auditor`) receive all Phase 7 `.view` permissions but no write abilities. Warehouse Manager holds the full inventory-control set; Store Officer can prepare counts, count, recount, analyse, prepare reconciliations, and create/submit adjustments but cannot post or reverse adjustments, adjust/complete counts, or approve/post reconciliations; Quality Inspector can count but not adjust.

Segregation of duties for Phase 7:

- **Adjustment creator cannot post.** `posted_by ≠ created_by` is enforced in `StockAdjustmentService` and the policy, independent of the approval flow.
- **Original poster cannot reverse.** Reversal is a new equal and opposite document; the original remains immutable.
- **Counters cannot adjust.** Counting/recounting is separate from the `stocktaking.counts.adjust` ability.
- **Scheduler and counters cannot adjust their own count's variances.** `createAdjustments()` refuses unless the actor holds `workflow.override`.
- **Reconciliation preparer cannot approve.** Preparing and approving are distinct abilities and actors.
- **Approval threshold.** Adjustments at or above `config('inventory.adjustment_approval_threshold')` require approval by someone other than the creator before posting.

### Phase 8 reporting permissions (implemented)

Permission groups: `reports.inventory.*`, `reports.procurement.*`, `reports.warehouse.*`, `reports.project.*`, `reports.audit.*`, each with `view` and `export`.

| Permission | Sys Admin | Executive | Procurement Manager | Procurement Officer | Warehouse Manager | Store Officer | Quality Inspector | Project Manager | Dept Requester | Auditor |
|------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `reports.inventory.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `reports.inventory.export` | Y | Y | | | Y | Y | | | | Y |
| `reports.procurement.view` | Y | Y | Y | Y | Y | Y | | Y | | Y |
| `reports.procurement.export` | Y | Y | Y | Y | | | | | | Y |
| `reports.warehouse.view` | Y | Y | Y | Y | Y | Y | Y | Y | | Y |
| `reports.warehouse.export` | Y | Y | | | Y | Y | | | | Y |
| `reports.project.view` | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y |
| `reports.project.export` | Y | Y | | | | | | Y | | Y |
| `reports.audit.view` | Y | Y | Y | Y | Y | Y | | Y | | Y |
| `reports.audit.export` | Y | Y | | | | | | | | Y |

`Super Administrator` receives all permissions through `Permissions::all()`. Global roles (`System Administrator`, `Executive`, `Auditor`) receive every report view and every report export. Export is a separate permission by design: a user may read a report on screen without being able to download its full row set. The export route re-checks the report's export permission so a crafted URL cannot bypass the screen control.

Reporting data scope:

- Inventory, warehouse and procurement reports apply the user's warehouse scope (`ReportScope::warehouse()`), falling back to no rows when a non-global user has no active warehouse grants.
- Procurement orders additionally use `PurchaseOrder::scopeVisibleTo()` (delivery-warehouse access or creator).
- Project consumption uses `ReportScope::project()` (project manager or active project membership), so a user cannot read consumption for a project they do not manage or belong to.
- Audit activity and approval reports are limited to roles holding `reports.audit.view`; they are intentionally global read-only evidence views.
- Scheduled snapshots run as the system (`user = null`), so they capture the full organisational totals, never a single user's narrowed scope.

## 4. Data-scope rules

| Role | Business units | Departments | Projects | Warehouses |
|------|----------------|-------------|----------|------------|
| Global roles | All | All | All | All |
| Non-global roles | All with `organization.business_units.view` | All with `organization.departments.view` | Manager or active-member projects | Explicit grants only |

Read scope is enforced by policies; write scope is enforced by policies **and** services. Business units group warehouses only: they no longer scope users, departments, cost centres, projects, or warehouse-bound documents. `system.access.all` widens read/write scope to global but does not grant module permissions or warehouse access for stock operations.

## 5. Segregation-of-duties matrix (future phases)

| Action | Creator | Approver | Poster | Reverser |
|--------|---------|----------|--------|----------|
| Purchase requisition | May create | Cannot approve own | — | — |
| Purchase order | May create | Cannot approve own | — | — |
| Supplier approval | May register | Cannot approve own | — | — |
| GRN | May prepare | — | Cannot post own if SoD on | — |
| Material issue | May prepare | — | Cannot post own if SoD on | — |
| Issue acknowledgement | Issuer may not acknowledge | — | — | — |
| Stock transfer | May request | Source approver cannot approve own | Dispatcher ≠ destination receiver in practice | Discrepancy resolver holds `distribution.transfers.resolve` |
| Material return | May create | — | — | Returner/creator cannot assess own return unless `workflow.override` |
| Stock adjustment | May create | Cannot approve own | Cannot post own | Original poster cannot reverse; requires `inventory.adjustments.reverse` |
| Stock count | May schedule | Cannot adjust own count's variances unless `workflow.override` | — | — |
| Count reconciliation | May prepare | Preparer cannot approve own statement | Approved by a different user; posts the balancing statement | — |

## 6. Permission groups by phase

| Phase | Permissions |
|-------|-------------|
| 2 (implemented) | `workflow.flows.view/create/update/delete`, `workflow.requests.view`, `workflow.act`, `workflow.delegate`, `workflow.override`, `workflow.escalate` |
| 3 | `procurement.requisitions.view/create/update/delete/submit`, `procurement.rfqs.*`, `procurement.quotations.*`, `procurement.orders.view/create/update/approve/cancel`, `procurement.orders.amend` |
| 4 (implemented) | `receiving.sra.view/create/update/delete/submit/cancel/verify`, `receiving.inspections.view/start/record`, `receiving.grn.view/create/post/cancel/reverse`, `receiving.returns.view/create/update/delete/post/cancel` |
| 5 (implemented) | `inventory.balances.view`, `inventory.ledger.view`, `inventory.transactions.view` (posting, reservation, valuation and reconciliation operations are service/job-internal); planned UI permissions: `inventory.post`, `inventory.reverse`, `inventory.reservations.manage`, `inventory.valuation.view` |
| 6 (implemented) | `requisition.requests.view/create/update/delete/submit/cancel/reserve`, `requisition.issues.view/create/update/delete/post/reverse/cancel/acknowledge`, `distribution.transfers.view/create/update/delete/submit/cancel/dispatch/receive/resolve`, `distribution.returns.view/create/update/delete/assess/post/cancel`, `distribution.project_consumption.view` |
| 7 (implemented) | `inventory.adjustments.view/create/update/delete/submit/post/reverse/cancel`, `stocktaking.counts.view/create/update/delete/start/count/recount/analyse/adjust/complete/cancel`, `stocktaking.reconciliations.view/prepare/approve/post`, `stocktaking.reconcile` |
| 8 (implemented) | `reports.inventory.view/export`, `reports.procurement.view/export`, `reports.warehouse.view/export`, `reports.project.view/export`, `reports.audit.view/export` |
| 9 | `integration.connections.manage`, `integration.outbox.view/retry`, `system.maintenance.manage` |

## 7. Route protection

Every application route uses:

```php
Route::middleware(['auth', 'verified', 'permission:organization.business_units.view'])
    ->get('/organization/business-units', BusinessUnitIndex::class)
    ->name('organization.business-units.index');
```

Write actions additionally call `Gate::authorize()` in the Livewire component and in the service. Unauthorized attempts are logged with actor, route, and target.

## 8. Permission administration rules

- System roles (`Super Administrator`, `System Administrator`, `Auditor`) cannot be deleted.
- Only a Super Administrator can grant `system.access.all` or edit system roles.
- Role changes are activity-logged with old/new permission sets.
- Users are never hard-deleted once they have documents; they are deactivated and soft-deleted.
- Permission cache is flushed automatically by Spatie on role/permission changes.
