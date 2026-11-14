<?php

namespace App\DTOs\Procurement;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class QuotationData
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public int $requestForQuotationId,
        public int $supplierId,
        public string $currency = 'NGN',
        public ?string $validUntil = null,
        public ?int $leadTimeDays = null,
        public ?string $paymentTerms = null,
        public ?string $warrantyTerms = null,
        public array $items = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            requestForQuotationId: (int) Arr::get($data, 'request_for_quotation_id'),
            supplierId: (int) Arr::get($data, 'supplier_id'),
            currency: strtoupper((string) Arr::get($data, 'currency', 'NGN')),
            validUntil: Input::stringOrNull(Arr::get($data, 'valid_until')),
            leadTimeDays: Input::intOrNull(Arr::get($data, 'lead_time_days')),
            paymentTerms: Input::stringOrNull(Arr::get($data, 'payment_terms')),
            warrantyTerms: Input::stringOrNull(Arr::get($data, 'warranty_terms')),
            items: array_values((array) Arr::get($data, 'items', [])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_for_quotation_id' => $this->requestForQuotationId,
            'supplier_id' => $this->supplierId,
            'currency' => $this->currency,
            'valid_until' => $this->validUntil,
            'lead_time_days' => $this->leadTimeDays,
            'payment_terms' => $this->paymentTerms,
            'warranty_terms' => $this->warrantyTerms,
        ];
    }
}
