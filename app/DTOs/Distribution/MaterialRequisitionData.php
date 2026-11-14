<?php

namespace App\DTOs\Distribution;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class MaterialRequisitionData
{
    /**
     * @param  list<MaterialRequisitionItemData>  $items
     */
    public function __construct(
        public int $requestingDepartmentId,
        public int $requestedBy,
        public int $destinationWarehouseId,
        public string $purpose,
        public array $items,
        public ?int $costCenterId = null,
        public ?int $projectId = null,
        public ?string $requiredBy = null,
        public string $priority = 'normal',
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof MaterialRequisitionItemData
                ? $item
                : MaterialRequisitionItemData::fromArray((array) $item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            requestingDepartmentId: (int) Arr::get($data, 'requesting_department_id'),
            requestedBy: (int) Arr::get($data, 'requested_by'),
            destinationWarehouseId: (int) Arr::get($data, 'destination_warehouse_id'),
            purpose: (string) Arr::get($data, 'purpose', ''),
            items: $items,
            costCenterId: Input::intOrNull(Arr::get($data, 'cost_center_id')),
            projectId: Input::intOrNull(Arr::get($data, 'project_id')),
            requiredBy: Input::stringOrNull(Arr::get($data, 'required_by')),
            priority: (string) Arr::get($data, 'priority', 'normal'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requesting_department_id' => $this->requestingDepartmentId,
            'cost_center_id' => $this->costCenterId,
            'project_id' => $this->projectId,
            'requested_by' => $this->requestedBy,
            'destination_warehouse_id' => $this->destinationWarehouseId,
            'purpose' => $this->purpose,
            'required_by' => $this->requiredBy,
            'priority' => $this->priority,
        ];
    }
}
