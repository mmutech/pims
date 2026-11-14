<?php

namespace App\DTOs\Procurement;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class PurchaseRequisitionData
{
    /**
     * @param  list<PurchaseRequisitionItemData>  $items
     */
    public function __construct(
        public int $requestingDepartmentId,
        public int $businessUnitId,
        public int $requestedBy,
        public string $justification,
        public ?int $costCenterId = null,
        public ?int $projectId = null,
        public ?int $suggestedSupplierId = null,
        public ?string $requiredBy = null,
        public string $currency = 'NGN',
        public array $items = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof PurchaseRequisitionItemData ? $item : PurchaseRequisitionItemData::fromArray($item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            requestingDepartmentId: (int) Arr::get($data, 'requesting_department_id'),
            businessUnitId: (int) Arr::get($data, 'business_unit_id'),
            requestedBy: (int) Arr::get($data, 'requested_by'),
            justification: trim((string) Arr::get($data, 'justification')),
            costCenterId: Input::intOrNull(Arr::get($data, 'cost_center_id')),
            projectId: Input::intOrNull(Arr::get($data, 'project_id')),
            suggestedSupplierId: Input::intOrNull(Arr::get($data, 'suggested_supplier_id')),
            requiredBy: Input::stringOrNull(Arr::get($data, 'required_by')),
            currency: strtoupper((string) Arr::get($data, 'currency', 'NGN')),
            items: $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requesting_department_id' => $this->requestingDepartmentId,
            'business_unit_id' => $this->businessUnitId,
            'requested_by' => $this->requestedBy,
            'justification' => $this->justification,
            'cost_center_id' => $this->costCenterId,
            'project_id' => $this->projectId,
            'suggested_supplier_id' => $this->suggestedSupplierId,
            'required_by' => $this->requiredBy,
            'currency' => $this->currency,
        ];
    }
}
