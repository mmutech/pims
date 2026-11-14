<?php

namespace App\DTOs\Distribution;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class StockIssueData
{
    /**
     * @param  list<StockIssueItemData>  $items
     */
    public function __construct(
        public int $materialRequisitionId,
        public int $warehouseId,
        public int $issuedTo,
        public int $issuedBy,
        public array $items,
        public ?string $issueDate = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof StockIssueItemData
                ? $item
                : StockIssueItemData::fromArray((array) $item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            materialRequisitionId: (int) Arr::get($data, 'material_requisition_id'),
            warehouseId: (int) Arr::get($data, 'warehouse_id'),
            issuedTo: (int) Arr::get($data, 'issued_to'),
            issuedBy: (int) Arr::get($data, 'issued_by'),
            items: $items,
            issueDate: Input::stringOrNull(Arr::get($data, 'issue_date')),
        );
    }
}
