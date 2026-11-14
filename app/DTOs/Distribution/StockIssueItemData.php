<?php

namespace App\DTOs\Distribution;

use Illuminate\Support\Arr;

final readonly class StockIssueItemData
{
    public function __construct(
        public int $materialRequisitionItemId,
        public int $reservationItemId,
        public string $quantity,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            materialRequisitionItemId: (int) Arr::get($data, 'material_requisition_item_id'),
            reservationItemId: (int) Arr::get($data, 'stock_reservation_item_id'),
            quantity: (string) Arr::get($data, 'quantity', '0'),
        );
    }
}
