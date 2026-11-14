<?php

namespace App\DTOs\Receiving;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class SupplierReturnData
{
    /**
     * @param  list<SupplierReturnItemData>  $items
     */
    public function __construct(
        public int $supplierId,
        public int $warehouseId,
        public int $createdBy,
        public array $items,
        public string $reason,
        public ?string $returnDate = null,
        public ?int $grnId = null,
        public ?string $creditNoteNumber = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof SupplierReturnItemData
                ? $item
                : SupplierReturnItemData::fromArray((array) $item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            supplierId: (int) Arr::get($data, 'supplier_id'),
            warehouseId: (int) Arr::get($data, 'warehouse_id'),
            createdBy: (int) Arr::get($data, 'created_by'),
            items: $items,
            reason: (string) Arr::get($data, 'reason', ''),
            returnDate: Input::stringOrNull(Arr::get($data, 'return_date')),
            grnId: Input::intOrNull(Arr::get($data, 'grn_id')),
            creditNoteNumber: Input::stringOrNull(Arr::get($data, 'credit_note_number')),
        );
    }
}
