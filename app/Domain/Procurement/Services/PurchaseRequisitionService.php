<?php

namespace App\Domain\Procurement\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Procurement\Models\PurchaseRequisition;
use App\Domain\Procurement\Models\PurchaseRequisitionItem;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Workflow\Contracts\ApprovalEngine;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\DTOs\Procurement\PurchaseRequisitionData;
use App\Enums\DocumentType;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\RequestForQuotationStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly ApprovalEngine $approvals,
    ) {}

    public function create(PurchaseRequisitionData $data, User $actor): PurchaseRequisition
    {
        $this->validateItems($data);

        return DB::transaction(function () use ($data, $actor): PurchaseRequisition {
            $requisition = PurchaseRequisition::create([
                ...$data->toArray(),
                'document_number' => $this->sequences->nextNumber(DocumentType::PurchaseRequisition),
                'status' => PurchaseRequisitionStatus::Draft,
                'total_estimated_amount' => '0',
                'approved_amount' => '0',
                'version' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncItems($requisition, $data);
            $this->recalculateTotals($requisition);

            return $requisition->refresh();
        });
    }

    public function update(PurchaseRequisition $requisition, PurchaseRequisitionData $data, User $actor): PurchaseRequisition
    {
        if (! $requisition->canBeEdited()) {
            throw new DomainException('Only draft or returned requisitions can be edited.');
        }

        $this->validateItems($data);

        return DB::transaction(function () use ($requisition, $data, $actor): PurchaseRequisition {
            $requisition->update([
                ...$data->toArray(),
                'updated_by' => $actor->id,
            ]);

            $requisition->items()->delete();
            $this->syncItems($requisition, $data);
            $this->recalculateTotals($requisition);

            return $requisition->refresh();
        });
    }

    public function updateApprovedQuantities(PurchaseRequisition $requisition, array $quantities, User $actor): PurchaseRequisition
    {
        if (! in_array($requisition->status, [
            PurchaseRequisitionStatus::Submitted,
            PurchaseRequisitionStatus::Draft,
            PurchaseRequisitionStatus::Returned,
        ], true)) {
            throw new DomainException('Approved quantities can only be adjusted before the requisition is finalised.');
        }

        return DB::transaction(function () use ($requisition, $quantities, $actor): PurchaseRequisition {
            foreach ($requisition->items()->get() as $item) {
                if (! array_key_exists($item->id, $quantities)) {
                    continue;
                }

                $approved = Decimal::normalize((string) $quantities[$item->id], Decimal::QUANTITY_SCALE);

                if (Decimal::compare($approved, '0') < 0) {
                    throw new DomainException('Approved quantities cannot be negative.');
                }

                if (Decimal::compare($approved, $item->requested_quantity) > 0) {
                    throw new DomainException('Approved quantities cannot exceed the requested quantity.');
                }

                $item->forceFill(['approved_quantity' => $approved])->save();
            }

            $requisition->forceFill([
                'updated_by' => $actor->id,
                'approved_amount' => $requisition->approvedAmount(),
            ])->save();

            return $requisition->refresh();
        });
    }

    public function submit(PurchaseRequisition $requisition, User $actor): PurchaseRequisition
    {
        if (! $requisition->canBeSubmitted()) {
            throw new DomainException('This requisition cannot be submitted.');
        }

        if (! $requisition->items()->exists()) {
            throw new DomainException('A requisition must contain at least one item.');
        }

        return DB::transaction(function () use ($requisition, $actor): PurchaseRequisition {
            $requisition->forceFill([
                'status' => PurchaseRequisitionStatus::Submitted,
                'updated_by' => $actor->id,
            ])->save();

            $this->approvals->submit($requisition, $actor);

            return $requisition->refresh();
        });
    }

    public function cancel(PurchaseRequisition $requisition, User $actor, string $reason): PurchaseRequisition
    {
        if (! $requisition->canBeCancelled()) {
            throw new DomainException('This requisition cannot be cancelled.');
        }

        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($requisition, $actor, $reason): PurchaseRequisition {
            $this->cancelPendingApproval($requisition, $actor, $reason);

            $requisition->forceFill([
                'status' => PurchaseRequisitionStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ])->save();

            RequestForQuotation::query()
                ->where('purchase_requisition_id', $requisition->id)
                ->where('status', '!=', RequestForQuotationStatus::Cancelled->value)
                ->update(['status' => RequestForQuotationStatus::Cancelled->value, 'cancelled_at' => now()]);

            return $requisition->refresh();
        });
    }

    public function delete(PurchaseRequisition $requisition): void
    {
        if (! $requisition->canBeEdited()) {
            throw new DomainException('Only draft or returned requisitions can be deleted.');
        }

        $requisition->delete();
    }

    private function syncItems(PurchaseRequisition $requisition, PurchaseRequisitionData $data): void
    {
        foreach ($data->items as $index => $item) {
            PurchaseRequisitionItem::create([
                'purchase_requisition_id' => $requisition->id,
                ...$item->toArray(),
                'approved_quantity' => $item->requestedQuantity,
                'converted_quantity' => '0',
                'estimated_line_total' => Decimal::multiply(
                    $item->requestedQuantity,
                    $item->estimatedUnitPrice,
                    Decimal::MONEY_SCALE,
                ),
                'sort_order' => $index,
            ]);
        }
    }

    private function recalculateTotals(PurchaseRequisition $requisition): void
    {
        $total = '0';

        foreach ($requisition->items()->get() as $item) {
            $total = Decimal::add($total, $item->estimated_line_total, Decimal::MONEY_SCALE);
        }

        $requisition->forceFill([
            'total_estimated_amount' => $total,
            'approved_amount' => $requisition->approvedAmount(),
        ])->save();
    }

    private function validateItems(PurchaseRequisitionData $data): void
    {
        if ($data->items === []) {
            throw new DomainException('A requisition must contain at least one item.');
        }

        foreach ($data->items as $item) {
            if (Decimal::compare($item->requestedQuantity, '0') <= 0) {
                throw new DomainException('Requested quantities must be greater than zero.');
            }

            if (Decimal::compare($item->estimatedUnitPrice, '0') < 0) {
                throw new DomainException('Estimated unit prices cannot be negative.');
            }
        }
    }

    private function cancelPendingApproval(PurchaseRequisition $requisition, User $actor, string $reason): void
    {
        $request = ApprovalRequest::query()
            ->where('document_type', $requisition->approvalDocumentType())
            ->where('document_id', $requisition->id)
            ->where('status', 'pending')
            ->first();

        if ($request !== null) {
            $this->approvals->cancel($request, $actor, $reason);
        }
    }
}
