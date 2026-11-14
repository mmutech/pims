<?php

namespace App\Domain\Distribution\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Distribution\Models\IssueAcknowledgement;
use App\Domain\Distribution\Models\MaterialRequisition;
use App\Domain\Distribution\Models\StockIssue;
use App\Domain\Distribution\Models\StockIssueItem;
use App\Domain\Inventory\Models\StockReservationItem;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\DTOs\Distribution\StockIssueData;
use App\DTOs\Inventory\StockPostingData;
use App\DTOs\Inventory\StockPostingLineData;
use App\Enums\DocumentType;
use App\Enums\IssueStatus;
use App\Enums\RequisitionStatus;
use App\Enums\StockMovementType;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Permissions;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Store issue vouchers. Every posted issue consumes reservation lines through
 * InventoryPostingService, keeping the ledger as the only physical truth.
 */
class StockIssueService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly InventoryPostingService $posting,
    ) {}

    public function create(StockIssueData $data, User $actor): StockIssue
    {
        $requisition = MaterialRequisition::query()->find($data->materialRequisitionId);

        if ($requisition === null) {
            throw new DomainException('The stock issue references an unknown material requisition.');
        }

        if (! $requisition->isApprovedForIssue()) {
            throw new DomainException('Stock can only be issued against an approved requisition.');
        }

        if ($requisition->destination_warehouse_id !== $data->warehouseId) {
            throw new DomainException('The issue warehouse must match the requisition destination warehouse.');
        }

        if ($data->items === []) {
            throw new DomainException('A stock issue must contain at least one item.');
        }

        return DB::transaction(function () use ($data, $requisition, $actor): StockIssue {
            $issue = StockIssue::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::StockIssue),
                'material_requisition_id' => $requisition->id,
                'warehouse_id' => $data->warehouseId,
                'issued_to' => $data->issuedTo,
                'issued_by' => $data->issuedBy,
                'issue_date' => $data->issueDate ?? now()->toDateString(),
                'status' => IssueStatus::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($data->items as $index => $line) {
                $requisitionItem = $requisition->items()->find($line->materialRequisitionItemId);

                if ($requisitionItem === null) {
                    throw new DomainException('The issue line references an unknown requisition line.');
                }

                $reservationItem = StockReservationItem::query()
                    ->with('reservation')
                    ->find($line->reservationItemId);

                if ($reservationItem === null || $reservationItem->reservation === null) {
                    throw new DomainException('The issue line references an unknown stock reservation line.');
                }

                $reservation = $reservationItem->reservation;

                if ($reservation->source_type !== $requisition->getMorphClass()
                    || (int) $reservation->source_id !== (int) $requisition->id) {
                    throw new DomainException('The reservation does not belong to this requisition.');
                }

                if (! $reservation->status->isOpen()) {
                    throw new DomainException('The stock reservation is no longer open.');
                }

                if ($reservationItem->item_id !== $requisitionItem->item_id) {
                    throw new DomainException('The reservation line does not match the requisition item.');
                }

                $quantity = Decimal::normalize($line->quantity, Decimal::QUANTITY_SCALE);

                if (! Decimal::isPositive($quantity)) {
                    throw new DomainException('Issued quantities must be greater than zero.');
                }

                if (Decimal::compare($quantity, $reservationItem->outstandingQuantity()) > 0) {
                    throw new DomainException(sprintf(
                        'Only %s is reserved for this line; %s was requested.',
                        $reservationItem->outstandingQuantity(),
                        $quantity,
                    ));
                }

                StockIssueItem::create([
                    'stock_issue_id' => $issue->id,
                    'material_requisition_item_id' => $requisitionItem->id,
                    'item_id' => $requisitionItem->item_id,
                    'unit_of_measure_id' => $requisitionItem->unit_of_measure_id,
                    'stock_reservation_item_id' => $reservationItem->id,
                    'issued_quantity' => $quantity,
                    'unit_cost' => '0',
                    'total_cost' => '0',
                    'batch_id' => $reservationItem->batch_id,
                    'stock_dimension_id' => $reservationItem->stock_dimension_id,
                    'sort_order' => $index,
                ]);
            }

            return $issue->refresh()->load('items');
        });
    }

    public function post(StockIssue $issue, User $actor): StockIssue
    {
        return DB::transaction(function () use ($issue, $actor): StockIssue {
            $issue = StockIssue::query()->lockForUpdate()->findOrFail($issue->id);

            if (! $issue->canBePosted()) {
                throw new DomainException('Only draft stock issues can be posted.');
            }

            $issue->load('items');
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($issue->material_requisition_id);

            if ($issue->items->isEmpty()) {
                throw new DomainException('A stock issue must contain at least one item.');
            }

            $lines = $issue->items->map(fn (StockIssueItem $item) => new StockPostingLineData(
                itemId: $item->item_id,
                unitOfMeasureId: $item->unit_of_measure_id,
                stockDimensionId: $item->stock_dimension_id,
                quantity: (string) $item->issued_quantity,
                batchId: $item->batch_id,
                serialNumberId: $item->serial_number_id,
                reference: 'stock_issue_item:'.$item->id,
                reservationItemId: $item->stock_reservation_item_id,
            ))->all();

            $transaction = $this->posting->post(new StockPostingData(
                movementType: StockMovementType::Issue,
                source: $issue,
                operation: 'issue',
                warehouseId: $issue->warehouse_id,
                postedBy: $actor->id,
                lines: $lines,
                metadata: [
                    'material_requisition_id' => $requisition->id,
                    'issued_to' => $issue->issued_to,
                ],
            ));

            $transaction->loadMissing('items');

            foreach ($issue->items as $index => $item) {
                $transactionItem = $transaction->items->get($index);

                if ($transactionItem !== null) {
                    $item->forceFill([
                        'stock_transaction_item_id' => $transactionItem->id,
                        'unit_cost' => $transactionItem->unit_cost,
                        'total_cost' => $transactionItem->total_cost,
                    ])->save();
                }

                $requisitionItem = $requisition->items()->lockForUpdate()->find($item->material_requisition_item_id);

                if ($requisitionItem === null) {
                    continue;
                }

                $issued = Decimal::add($requisitionItem->issued_quantity, $item->issued_quantity, Decimal::QUANTITY_SCALE);
                $reserved = Decimal::subtract(
                    $requisitionItem->reserved_quantity,
                    $item->issued_quantity,
                    Decimal::QUANTITY_SCALE,
                );

                if (Decimal::isNegative($reserved)) {
                    $reserved = '0';
                }

                $requisitionItem->forceFill([
                    'issued_quantity' => $issued,
                    'reserved_quantity' => $reserved,
                    'outstanding_quantity' => Decimal::subtract(
                        $requisitionItem->approved_quantity,
                        $issued,
                        Decimal::QUANTITY_SCALE,
                    ),
                ])->save();
            }

            $issue->forceFill([
                'status' => IssueStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor->id,
                'stock_transaction_id' => $transaction->id,
                'updated_by' => $actor->id,
            ])->save();

            $this->refreshRequisitionStatus($requisition);

            return $issue->refresh()->load('items');
        });
    }

    public function reverse(StockIssue $issue, User $actor, string $reason): StockIssue
    {
        if (blank($reason)) {
            throw new DomainException('A reversal reason is required.');
        }

        return DB::transaction(function () use ($issue, $actor, $reason): StockIssue {
            $issue = StockIssue::query()->lockForUpdate()->findOrFail($issue->id);

            if (! $issue->canBeReversed()) {
                throw new DomainException('Only posted stock issues can be reversed.');
            }

            $transaction = $issue->transaction;

            if ($transaction === null) {
                throw new DomainException('This issue has no stock transaction to reverse.');
            }

            $this->posting->reverse($transaction, $actor, $reason);

            $issue->load('items');
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($issue->material_requisition_id);

            foreach ($issue->items as $item) {
                $requisitionItem = $requisition->items()->lockForUpdate()->find($item->material_requisition_item_id);

                if ($requisitionItem === null) {
                    continue;
                }

                $issued = Decimal::subtract(
                    $requisitionItem->issued_quantity,
                    $item->issued_quantity,
                    Decimal::QUANTITY_SCALE,
                );

                if (Decimal::isNegative($issued)) {
                    $issued = '0';
                }

                $requisitionItem->forceFill([
                    'issued_quantity' => $issued,
                    'outstanding_quantity' => Decimal::subtract(
                        $requisitionItem->approved_quantity,
                        $issued,
                        Decimal::QUANTITY_SCALE,
                    ),
                ])->save();
            }

            $issue->forceFill([
                'status' => IssueStatus::Reversed,
                'updated_by' => $actor->id,
            ])->save();

            $this->refreshRequisitionStatus($requisition);

            return $issue->refresh()->load('items');
        });
    }

    public function cancel(StockIssue $issue, User $actor, string $reason): StockIssue
    {
        if (! $issue->canBeCancelled()) {
            throw new DomainException('Only draft stock issues can be cancelled.');
        }

        if (blank($reason)) {
            throw new DomainException('A cancellation reason is required.');
        }

        $issue->forceFill([
            'status' => IssueStatus::Cancelled,
            'updated_by' => $actor->id,
        ])->save();

        activity('distribution')
            ->causedBy($actor)
            ->performedOn($issue)
            ->withProperties(['reason' => $reason])
            ->log('stock issue cancelled');

        return $issue->refresh();
    }

    public function acknowledge(
        StockIssue $issue,
        User $actor,
        ?string $comment = null,
        ?string $signaturePath = null,
        ?string $ipAddress = null,
    ): IssueAcknowledgement {
        return DB::transaction(function () use ($issue, $actor, $comment, $signaturePath, $ipAddress): IssueAcknowledgement {
            $issue = StockIssue::query()->lockForUpdate()->findOrFail($issue->id);

            if ($issue->status !== IssueStatus::Posted) {
                throw new DomainException('Only posted issues can be acknowledged.');
            }

            if ($issue->acknowledgement()->exists()) {
                throw new DomainException('This issue has already been acknowledged.');
            }

            $canOverride = $actor->can(Permissions::WORKFLOW_OVERRIDE);

            if ((int) $issue->issued_to !== (int) $actor->id && ! $canOverride) {
                throw new DomainException('Only the recipient can acknowledge this issue.');
            }

            if ((int) $issue->issued_by === (int) $actor->id && ! $canOverride) {
                throw new DomainException('The issuer cannot acknowledge an issue on behalf of the recipient.');
            }

            $acknowledgement = IssueAcknowledgement::create([
                'stock_issue_id' => $issue->id,
                'acknowledged_by' => $actor->id,
                'acknowledged_at' => now(),
                'signature_path' => $signaturePath,
                'comment' => $comment,
                'ip_address' => $ipAddress,
            ]);

            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($issue->material_requisition_id);
            $this->refreshRequisitionStatus($requisition);

            return $acknowledgement->refresh();
        });
    }

    public function delete(StockIssue $issue): void
    {
        if (! $issue->canBeEdited()) {
            throw new DomainException('Only draft stock issues can be deleted.');
        }

        $issue->delete();
    }

    /**
     * Posted consumption grouped per item for a project.
     *
     * @return array{quantity: string, value: string, lines: list<array<string, mixed>>}
     */
    public function projectConsumption(int $projectId, ?string $from = null, ?string $to = null): array
    {
        $query = StockIssueItem::query()
            ->join('stock_issues', 'stock_issues.id', '=', 'stock_issue_items.stock_issue_id')
            ->join('material_requisitions', 'material_requisitions.id', '=', 'stock_issues.material_requisition_id')
            ->join('items', 'items.id', '=', 'stock_issue_items.item_id')
            ->where('material_requisitions.project_id', $projectId)
            ->where('stock_issues.status', IssueStatus::Posted->value)
            ->select([
                'stock_issue_items.item_id',
                'items.code as item_code',
                'items.name as item_name',
                'stock_issue_items.issued_quantity',
                'stock_issue_items.total_cost',
            ]);

        if ($from !== null) {
            $query->whereDate('stock_issues.issue_date', '>=', Carbon::parse($from)->toDateString());
        }

        if ($to !== null) {
            $query->whereDate('stock_issues.issue_date', '<=', Carbon::parse($to)->toDateString());
        }

        $rows = $query->get();

        $quantity = '0';
        $value = '0';
        $grouped = [];

        foreach ($rows as $row) {
            $quantity = Decimal::add($quantity, $row->issued_quantity, Decimal::QUANTITY_SCALE);
            $value = Decimal::add($value, $row->total_cost, Decimal::MONEY_SCALE);

            $key = (int) $row->item_id;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'item_id' => $key,
                    'item_code' => $row->item_code,
                    'item_name' => $row->item_name,
                    'quantity' => '0',
                    'value' => '0.00',
                ];
            }

            $grouped[$key]['quantity'] = Decimal::add(
                $grouped[$key]['quantity'],
                $row->issued_quantity,
                Decimal::QUANTITY_SCALE,
            );
            $grouped[$key]['value'] = Decimal::add(
                $grouped[$key]['value'],
                $row->total_cost,
                Decimal::MONEY_SCALE,
            );
        }

        return [
            'quantity' => $quantity,
            'value' => $value,
            'lines' => array_values($grouped),
        ];
    }

    private function refreshRequisitionStatus(MaterialRequisition $requisition): void
    {
        $items = $requisition->items()->get();

        $outstanding = $items->reduce(
            fn (string $carry, $item): string => Decimal::add(
                $carry,
                $item->outstanding_quantity,
                Decimal::QUANTITY_SCALE,
            ),
            Decimal::normalize('0'),
        );

        $issued = $items->reduce(
            fn (string $carry, $item): string => Decimal::add(
                $carry,
                $item->issued_quantity,
                Decimal::QUANTITY_SCALE,
            ),
            Decimal::normalize('0'),
        );

        if (Decimal::isPositive($issued) && ! Decimal::isPositive($outstanding)) {
            $allAcknowledged = ! $requisition->issues()
                ->where('status', IssueStatus::Posted->value)
                ->whereDoesntHave('acknowledgement')
                ->exists();

            $requisition->forceFill([
                'status' => $allAcknowledged ? RequisitionStatus::Acknowledged : RequisitionStatus::Issued,
            ])->save();

            return;
        }

        if (Decimal::isPositive($issued)) {
            $requisition->forceFill(['status' => RequisitionStatus::PartiallyIssued])->save();
        }
    }
}
