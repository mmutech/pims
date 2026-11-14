<?php

namespace App\Domain\Workflow\Contracts;

use App\Domain\Workflow\Models\ApprovalRequest;

/**
 * Implemented by every document type that participates in approval routing.
 */
interface Approvable
{
    /**
     * Logical document type stored on approval requests, e.g. "purchase_order".
     */
    public function approvalDocumentType(): string;

    /**
     * Attributes used for routing, conditions, and assignment resolution.
     *
     * Recognised keys: amount, document_number, requested_by, created_by,
     * updated_by, department_id, project_id, warehouse_id,
     * item_category_id, supplier_id, priority.
     *
     * @return array<string, mixed>
     */
    public function approvalAttributes(): array;

    /**
     * Apply the approval decision to the underlying document.
     */
    public function onApprovalStatusChanged(ApprovalRequest $request): void;
}
