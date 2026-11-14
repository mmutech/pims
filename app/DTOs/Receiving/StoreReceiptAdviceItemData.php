<?php

namespace App\DTOs\Receiving;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class StoreReceiptAdviceItemData
{
    /**
     * @param  list<string>  $serialNumbers
     */
    public function __construct(
        public int $purchaseOrderItemId,
        public string $expectedQuantity,
        public string $deliveredQuantity,
        public ?string $batchNumber = null,
        public array $serialNumbers = [],
        public ?string $conditionNotes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $serials = Arr::get($data, 'serial_numbers', []);

        return new self(
            purchaseOrderItemId: (int) Arr::get($data, 'purchase_order_item_id'),
            expectedQuantity: (string) Arr::get($data, 'expected_quantity', '0'),
            deliveredQuantity: (string) Arr::get($data, 'delivered_quantity', '0'),
            batchNumber: Input::stringOrNull(Arr::get($data, 'batch_number')),
            serialNumbers: is_array($serials) ? array_values(array_filter(array_map('strval', $serials))) : [],
            conditionNotes: Input::stringOrNull(Arr::get($data, 'condition_notes')),
        );
    }
}
