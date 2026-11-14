<?php

namespace App\DTOs\Procurement;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class RfqData
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<int>  $supplierIds
     */
    public function __construct(
        public int $businessUnitId,
        public string $title,
        public string $closingDate,
        public ?int $purchaseRequisitionId = null,
        public ?string $description = null,
        public array $items = [],
        public array $supplierIds = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            businessUnitId: (int) Arr::get($data, 'business_unit_id'),
            title: trim((string) Arr::get($data, 'title')),
            closingDate: (string) Arr::get($data, 'closing_date'),
            purchaseRequisitionId: Input::intOrNull(Arr::get($data, 'purchase_requisition_id')),
            description: Input::stringOrNull(Arr::get($data, 'description')),
            items: array_values((array) Arr::get($data, 'items', [])),
            supplierIds: array_values(array_map('intval', (array) Arr::get($data, 'supplier_ids', []))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'business_unit_id' => $this->businessUnitId,
            'title' => $this->title,
            'closing_date' => $this->closingDate,
            'purchase_requisition_id' => $this->purchaseRequisitionId,
            'description' => $this->description,
        ];
    }
}
