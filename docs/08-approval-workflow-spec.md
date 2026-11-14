# 08 — Approval Workflow Engine Specification

## 1. Goals

- Approval stages are configured in the database, never hardcoded in modules.
- Support sequential, parallel, any-one, and all-must-approve stages.
- Route by amount, role, user, department, project, warehouse, and document attributes.
- Support delegation, escalation, rejection, return for correction, and cancellation.
- Enforce segregation of duties and preserve an append-only approval history.
- Provide one API used by every document type.

## 2. Data model

| Table | Purpose |
|-------|---------|
| `approval_flows` | One active flow per document type; versioned |
| `approval_stages` | Ordered stages with mode, SLA, amount bands, self-approval rule |
| `approval_stage_conditions` | Field/operator/value conditions evaluated against the document |
| `approval_assignments` | Who can act: role, user, department manager, project manager, warehouse manager |
| `approval_requests` | One active request per document |
| `approval_request_stages` | Runtime snapshot of each stage and its progress |
| `approval_stage_assignees` | Resolved actors for a runtime stage, including delegates |
| `approval_actions` | Append-only history of every action |
| `approval_delegations` | Time-bound delegation of authority |
| `approval_escalations` | SLA breaches and escalation targets |
| `approval_notifications` | Deduplication ledger for approval notifications |

Runtime rows snapshot the stage configuration (sequence, name, mode, required approvals, `prevent_self_approval`, `is_final`) so later configuration edits do not change in-flight approvals. `approval_request_stages.approval_stage_id` is nullable: deleting a flow stage does not destroy request history.

Active-request uniqueness is enforced by `approval_requests.active_key` holding `document_type:document_id` while the request is open and cleared when it reaches a final status. This works on PostgreSQL and MySQL without partial indexes.

## 3. Flow configuration

```php
ApprovalFlow {
  document_type: 'purchase_order',
  name: 'Purchase Order Approval',
  is_active: true,
  stages: [
    Stage {
      sequence: 1,
      name: 'Procurement Review',
      mode: 'any_one',
      required_approvals: 1,
      amount_min: null, amount_max: null,
      prevent_self_approval: true,
      assignments: [ role: 'Procurement Manager' ],
      sla_hours: 24,
    },
    Stage {
      sequence: 2,
      name: 'Finance Review',
      mode: 'all',
      required_approvals: 2,
      amount_min: 5_000_000, amount_max: null,
      assignments: [ role: 'Finance Manager', role: 'Financial Controller' ],
      sla_hours: 48,
    },
  ],
}
```

### 3A. Registered document types

`config/approvals.php` maps each document type to its approvable model:

| Document type | Model | Phase |
|---------------|-------|-------|
| `purchase_requisition` | `PurchaseRequisition` | 3 |
| `quotation_evaluation` | `QuotationEvaluation` | 3 |
| `purchase_order` | `PurchaseOrder` | 3 |
| `purchase_order_amendment` | `PurchaseOrderAmendment` | 3 |
| `material_requisition` | `MaterialRequisition` | 6 |
| `stock_transfer` | `StockTransfer` | 6 |
| `stock_adjustment` | `StockAdjustment` | 7 |

Phase 6 details:

- `ApprovalFlowSeeder` registers `material_requisition` and `stock_transfer` flows assigned to the `Warehouse Manager` role. Tests create role-based flows because `warehouses.manager_id` is not set in fixtures.
- A requisition's approval attributes include its requesting department, destination warehouse, project (when present), priority, and a zero amount. The `material_requisition` callback writes approved quantities and marks partially approved lines.
- A transfer's approval attributes include the source and destination warehouses and requester. Approval is required before dispatch; cancellation before dispatch also cancels the pending request.
- Document numbers and statuses stay with the domain services. The approval engine only records decisions and invokes `onApprovalStatusChanged()`.

Phase 7 details:

- `StockAdjustment` implements `Approvable`; `approvalDocumentType()` returns `stock_adjustment` and `approvalAttributes()` exposes `amount` (`total_value`), document number, creator/editor, warehouse, reason code, adjustment type, linked `stock_count_id`, and priority.
- Approval is conditional on value, not document type: `StockAdjustmentService::submit()` raises a request only when `total_value >= config('inventory.adjustment_approval_threshold')`. Below the threshold the adjustment may be posted directly. A threshold of `0` auto-approves every adjustment.
- `onApprovalStatusChanged()` maps `approved → approved` (recording `approved_at`/`approved_by` from the last approving action), and sends rejected/returned/expired back to `draft` and cancelled to `cancelled`.
- Self-approval is blocked by the flow's `prevent_self_approval`; the service additionally enforces that the creator cannot post their own adjustment even when no approval flow is configured.
- `StockCount` carries an `approval_request_id` column for future count-level approval, but counts themselves are not currently routed through the approval engine. Reconciliation uses the same direct approval pattern (`prepared_by ≠ approved_by`) and is not a configured flow.

## 4. Request lifecycle

```
submit(document)
  → resolve active flow by document_type
  → evaluate conditions per stage (amount, org, project, warehouse, category)
  → create approval_request(status=pending, current_stage=first applicable)
  → snapshot stages into approval_request_stages
  → notify eligible approvers
  → document.status = pending_approval

act(request, stage, actor, action, comment)
  → authorize actor (assignment, delegation, permission)
  → enforce prevent_self_approval
  → lock the request and current stage rows
  → append approval_action (append-only)
  → update stage progress / stage assignee status
  → if stage satisfied and is_final → request approved; document.approved
  → if stage satisfied → advance to next applicable stage
  → if no stages remain → request approved; document.approved
  → if rejected/returned/cancelled → document status updated; notify requester
```

Stages whose `is_final` flag is set complete the request immediately even if later stages exist; those later stages remain pending and are never acted on. A request rejected or returned at any stage terminates immediately. `act()` accepts only `approve`, `reject`, and `return`; delegation and escalation are recorded by their own services, and cancellation/expiry have dedicated methods.

## 5. Stage evaluation

A stage is **applicable** when:

1. Its amount band contains the document amount (`amount_min ≤ amount < amount_max`; null = unbounded).
2. All conditions in every logical group evaluate true. Groups are ANDed; conditions inside a group are ORed when `logical_group` repeats.
3. At least one assignment resolves to an active user.

Condition fields are read from the document when present (`amount`, `currency`, `department_id`, `business_unit_id`, `project_id`, `warehouse_id`, `item_category_id`, `supplier_id`, `priority`, `document_number`, `requested_by`, `created_by`, `updated_by`). Operators: `equals`, `not_equals`, `in`, `not_in`, `greater_than`, `less_than`, `between`. Values are stored as JSON arrays; the builder accepts comma-separated input and normalises it. Amount comparisons use `App\Support\Decimal` (bcmath), never floats.

If no stages are applicable, the request is auto-approved and the action is recorded as a system approval with reason `no_applicable_stages`.

## 6. Assignment resolution

| Assignee type | Resolution |
|---------------|------------|
| `role` | Active users with the role, scoped to the document's warehouse when it has one |
| `user` | Specific active user |
| `department_manager` | Not yet supported — resolves to nobody until `departments.manager_id` exists |
| `project_manager` | `projects.manager_id` |
| `warehouse_manager` | `warehouses.manager_id` for the document warehouse |
| `requester_manager` | Not yet supported — resolves to nobody until `users.manager_id` exists |

The UI only offers supported assignee types; the two unsupported types are rejected by the builder and marked `blocked` if present in legacy data. Resolution excludes inactive/suspended users and the document creator/last editor when `prevent_self_approval` is true. If resolution yields nobody, the stage is marked `blocked` with a reason and surfaced to `workflow.override` holders; it does not silently auto-approve.

## 7. Approval modes

- `any_one`: stage approves when `received_approvals >= 1` (the default).
- `all`: every resolved assignee must approve; rejection by any assignee rejects the stage.
- `parallel`: same as `all` but assignments may act concurrently; progress is tracked per assignee.
- `sequential`: assignments act in order; the next assignee is notified only after the previous approves. In the current UI, sequential routing is represented by separate stages, so the engine treats `sequential` like an all-assignees stage within one stage.

Rejection at any stage terminates the request immediately. Approvals already recorded remain in history. Voting is idempotent per assignee: a second action from the same user is rejected.

## 8. Segregation of duties

- `prevent_self_approval` blocks the document creator, the last editor, and (optionally) the poster.
- A user cannot act twice on the same stage.
- Delegation cannot be to oneself; the delegate must be active and hold `workflow.act`.
- Reversal approval (Phase 7) requires an approver distinct from the original poster.
- Supplier approval requires `supplier.suppliers.approve`; the creator is blocked when SoD is enabled in settings.
- Administrative override is not a hidden bypass: it requires `workflow.override` and still appends an ordinary `approval_actions` row with the acting user, timestamp, and comment.

## 9. Delegation

- A delegator grants a delegate a date range and optional document type.
- Pending actions assigned to the delegator become actionable by the delegate while active.
- The action records `actor_id` = delegate and `metadata.delegated_from` = delegator.
- Expired delegations are ignored by `isActiveAt()`; the `approvals:expire-delegations` command marks them inactive.
- Overlapping active delegations for the same delegator/delegate/scope are rejected.
- Delegates see delegated requests in My Approvals and can open them; the audit trail distinguishes delegate actions.
- Delegation is audited and visible on the document timeline.

## 10. Escalation

- Each stage has `sla_hours`. `approvals:escalate` checks open stages whose `sla_due_at` has passed.
- Escalation targets: the stage's configured `escalation_role_id` or `escalation_user_id`, resolved to active users.
- Escalation creates an `approval_escalations` row, adds the target as an additional stage assignee, notifies the target, and records an `escalate` action with `metadata.automatic = true`.
- Escalation does not change `required_approvals`; it adds visibility and another eligible actor. A blocked stage becomes in-progress when escalation adds a target.

## 11. Rejection, return, cancellation

- **Reject**: request and document become `rejected`; no further actions. A new revision is required to proceed.
- **Return for correction**: document returns to `draft`/`returned`; the requester may edit and resubmit. Prior actions remain in history; a new request is created on resubmit.
- **Cancel**: requester or administrator with permission cancels before final approval. Cancelled requests are immutable.
- **Expiry**: if the document's validity date passes, a job marks the request `expired`; the document must be resubmitted.

## 12. API contract

```php
interface ApprovalEngine
{
    public function submit(Model&Approvable $document, User $actor): ApprovalRequest;
    public function act(ApprovalRequest $request, User $actor, ApprovalActionType $action, ?string $comment = null, array $metadata = []): ApprovalRequest;
    public function cancel(ApprovalRequest $request, User $actor, string $reason): ApprovalRequest;
    public function expire(ApprovalRequest $request, string $reason = 'Document validity expired'): ApprovalRequest;
    public function pendingFor(User $user): Collection;
    public function historyFor(Model&Approvable $document): Collection;
}
```

`ApprovalDocumentRegistry` maps the logical document type to a model class; it is empty in Phase 2 because no document models exist yet. Document modules call `submit()` after persisting their draft and before marking themselves submitted. They never inspect stages directly. The engine emits `ApprovalRequested`, `ApprovalStageCompleted`, `DocumentApproved`, `DocumentRejected`, `DocumentReturned`, `ApprovalCancelled`, and `ApprovalExpired` events.

Policies enforce `workflow.requests.view` for list/detail access, `workflow.act` plus assignment/delegation for decisions, requester ownership or `workflow.override` for cancellation, and `workflow.flows.*` for configuration.

## 13. Notifications

Implemented notifications: `ApprovalAssignedNotification`, `ApprovalDecisionNotification`, and `ApprovalEscalatedNotification`. They implement `ShouldQueue` and deliver database and mail channels, so mail rendering and SMTP delivery never block the approval transaction; locally they run through the `database` queue and in production through Redis/Horizon.

- Approver assigned when a stage opens and when a delegate gains access.
- Escalation to the target and requester.
- Final decision (approved, rejected, returned, cancelled, expired) to the requester.
- Notifications are deduplicated by `request_id:stage_id:user_id:type` in `approval_notifications`.
- SLA reminders at 50%/100% remain a roadmap item; escalation runs from `approvals:escalate`.

## 14. UI requirements

Implemented as Livewire screens under `App\Livewire`:

- **My Approvals** (`/approvals`): pending requests visible to the user as an assignee, delegate, or override holder; overdue and blocked counts; debounced search. Bulk approval is deliberately absent.
- **Approval Detail** (`/approvals/{request}`): stage timeline, assignee statuses, append-only action history, comment box, approve/reject/return, cancel, and delegate actions. Reject and return require a comment.
- **Approval History** (`/approvals/history`): filter by status and document type with debounced search; scoped to requests the user raised, acted on, or is involved in via delegation, unless they hold `workflow.override` or `audit.view`.
- **Workflow Configuration** (`/workflows`, `/workflows/create`, `/workflows/{flow}/edit`): list with activate/deactivate/delete; full-page stage builder with reorder, conditions (AND groups / OR members), assignee picker, amount bands, SLA, escalation targets, SoD and final-stage toggles. One flow per document type.
- **Delegations** (`/delegations`): list active/scheduled/ended delegations, create a delegation with a date window and optional document type, cancel own delegations.
- All screens are responsive at 360 px and use Flux components with the shared `HandlesDomainActions` feedback pattern.

## 15. Testing

Implemented tests live in `tests/Feature/Workflow` (28 tests, 62 assertions as of Phase 2):

- Sequential, parallel, any-one, and all modes.
- Amount-band routing and condition evaluation.
- Role, user, project, and warehouse assignment resolution; unsupported manager types block safely.
- Self-approval block, duplicate action block, unauthorized actor block, and override permission checks.
- Delegation active/expired visibility and action; escalation after SLA.
- Rejection, return, resubmit, cancellation, expiry.
- History append-only and document status synchronization.
- UI coverage for approvals index/history, workflow authorization and form validation, delegation create/cancel, and self-delegation rejection.
- Concurrency: request and current-stage rows are locked (`lockForUpdate`) inside a transaction so simultaneous decisions advance consistently.

Not covered locally: PostgreSQL row-lock behavior and Redis/Horizon queued notification delivery, because the development machine only has MySQL and no Redis server.
