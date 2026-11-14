<?php

namespace App\Domain\Procurement\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\RfqItem;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Procurement\Models\SupplierQuotationItem;
use App\DTOs\Procurement\QuotationData;
use App\Enums\DocumentType;
use App\Enums\RfqSupplierStatus;
use App\Enums\SupplierQuotationStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class SupplierQuotationService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
    ) {}

    public function create(QuotationData $data, User $actor): SupplierQuotation
    {
        $rfq = RequestForQuotation::query()->findOrFail($data->requestForQuotationId);

        if (! $rfq->status->canReceiveQuotations()) {
            throw new DomainException('This RFQ is not open for quotations.');
        }

        $invited = $rfq->suppliers()->where('supplier_id', $data->supplierId)->exists();

        if (! $invited) {
            throw new DomainException('The supplier was not invited to this RFQ.');
        }

        if ($rfq->quotations()->where('supplier_id', $data->supplierId)->exists()) {
            throw new DomainException('A quotation already exists for this supplier and RFQ.');
        }

        $this->validateItems($rfq, $data);

        return DB::transaction(function () use ($data, $actor): SupplierQuotation {
            $quotation = SupplierQuotation::create([
                ...$data->toArray(),
                'document_number' => $this->sequences->nextNumber(DocumentType::SupplierQuotation),
                'status' => SupplierQuotationStatus::Draft,
                'subtotal' => '0',
                'tax_amount' => '0',
                'total_amount' => '0',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncItems($quotation, $data->items);
            $quotation->recalculateTotals();

            return $quotation->refresh();
        });
    }

    public function update(SupplierQuotation $quotation, QuotationData $data, User $actor): SupplierQuotation
    {
        if (! $quotation->canBeEdited()) {
            throw new DomainException('Only draft quotations can be edited.');
        }

        $this->validateItems($quotation->requestForQuotation, $data);

        return DB::transaction(function () use ($quotation, $data, $actor): SupplierQuotation {
            $quotation->update([
                ...$data->toArray(),
                'updated_by' => $actor->id,
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $data->items);
            $quotation->recalculateTotals();

            return $quotation->refresh();
        });
    }

    public function submit(SupplierQuotation $quotation, User $actor): SupplierQuotation
    {
        if (! $quotation->canBeSubmitted()) {
            throw new DomainException('This quotation cannot be submitted.');
        }

        if (! $quotation->items()->exists()) {
            throw new DomainException('A quotation must contain at least one line.');
        }

        return DB::transaction(function () use ($quotation, $actor): SupplierQuotation {
            $quotation->forceFill([
                'status' => SupplierQuotationStatus::Submitted,
                'submitted_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $quotation->requestForQuotation
                ->suppliers()
                ->where('supplier_id', $quotation->supplier_id)
                ->update([
                    'status' => RfqSupplierStatus::Responded->value,
                    'responded_at' => now(),
                ]);

            return $quotation->refresh();
        });
    }

    public function withdraw(SupplierQuotation $quotation, User $actor): SupplierQuotation
    {
        if (! $quotation->canBeWithdrawn()) {
            throw new DomainException('Only submitted quotations can be withdrawn.');
        }

        $quotation->forceFill([
            'status' => SupplierQuotationStatus::Withdrawn,
            'updated_by' => $actor->id,
        ])->save();

        $quotation->requestForQuotation
            ->suppliers()
            ->where('supplier_id', $quotation->supplier_id)
            ->update(['status' => RfqSupplierStatus::NoResponse->value]);

        return $quotation->refresh();
    }

    public function delete(SupplierQuotation $quotation): void
    {
        if (! $quotation->canBeEdited()) {
            throw new DomainException('Only draft quotations can be deleted.');
        }

        $quotation->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(SupplierQuotation $quotation, array $items): void
    {
        foreach ($items as $item) {
            $rfqItem = RfqItem::query()
                ->where('request_for_quotation_id', $quotation->request_for_quotation_id)
                ->find((int) ($item['rfq_item_id'] ?? 0));

            if ($rfqItem === null) {
                throw new DomainException('Quotation lines must reference items from the RFQ.');
            }

            $quantity = Decimal::normalize((string) ($item['quantity'] ?? $rfqItem->quantity), Decimal::QUANTITY_SCALE);
            $unitPrice = Decimal::normalize((string) ($item['unit_price'] ?? '0'), Decimal::QUANTITY_SCALE);

            if (Decimal::compare($quantity, '0') <= 0) {
                throw new DomainException('Quotation quantities must be greater than zero.');
            }

            if (Decimal::compare($unitPrice, '0') < 0) {
                throw new DomainException('Unit prices cannot be negative.');
            }

            SupplierQuotationItem::create([
                'supplier_quotation_id' => $quotation->id,
                'rfq_item_id' => $rfqItem->id,
                'item_id' => $rfqItem->item_id,
                'unit_of_measure_id' => $rfqItem->unit_of_measure_id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => (string) ($item['tax_rate'] ?? '0'),
                'discount_amount' => (string) ($item['discount_amount'] ?? '0'),
                'line_total' => Decimal::multiply($quantity, $unitPrice, Decimal::MONEY_SCALE),
                'lead_time_days' => isset($item['lead_time_days']) ? (int) $item['lead_time_days'] : null,
            ]);
        }
    }

    private function validateItems(RequestForQuotation $rfq, QuotationData $data): void
    {
        if ($data->items === []) {
            throw new DomainException('A quotation must contain at least one line.');
        }

        $validRfqItemIds = $rfq->items()->pluck('id')->all();

        foreach ($data->items as $item) {
            if (! in_array((int) ($item['rfq_item_id'] ?? 0), $validRfqItemIds, true)) {
                throw new DomainException('Quotation lines must reference items from the RFQ.');
            }
        }
    }
}
