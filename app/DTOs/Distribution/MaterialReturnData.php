<?php

namespace App\DTOs\Distribution;

use App\Enums\MaterialReturnType;
use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class MaterialReturnData
{
    /**
     * @param  list<MaterialReturnItemData>  $items
     */
    public function __construct(
        public MaterialReturnType $returnType,
        public int $warehouseId,
        public int $returnedBy,
        public string $reason,
        public array $items,
        public ?int $originalIssueId = null,
        public ?int $originalGrnId = null,
        public ?int $supplierId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof MaterialReturnItemData
                ? $item
                : MaterialReturnItemData::fromArray((array) $item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            returnType: MaterialReturnType::from((string) Arr::get($data, 'return_type', MaterialReturnType::Project->value)),
            warehouseId: (int) Arr::get($data, 'warehouse_id'),
            returnedBy: (int) Arr::get($data, 'returned_by'),
            reason: (string) Arr::get($data, 'reason', ''),
            items: $items,
            originalIssueId: Input::intOrNull(Arr::get($data, 'original_issue_id')),
            originalGrnId: Input::intOrNull(Arr::get($data, 'original_grn_id')),
            supplierId: Input::intOrNull(Arr::get($data, 'supplier_id')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'return_type' => $this->returnType,
            'warehouse_id' => $this->warehouseId,
            'returned_by' => $this->returnedBy,
            'reason' => $this->reason,
            'original_issue_id' => $this->originalIssueId,
            'original_grn_id' => $this->originalGrnId,
            'supplier_id' => $this->supplierId,
        ];
    }
}
