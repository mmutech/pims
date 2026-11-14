<?php

namespace App\DTOs\Receiving;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class StoreReceiptAdviceData
{
    /**
     * @param  list<StoreReceiptAdviceItemData>  $items
     */
    public function __construct(
        public int $purchaseOrderId,
        public int $receivedBy,
        public array $items,
        public ?string $expectedDeliveryDate = null,
        public ?string $vehicleNumber = null,
        public ?string $driverName = null,
        public ?string $driverPhone = null,
        public ?string $deliveryNoteNumber = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof StoreReceiptAdviceItemData
                ? $item
                : StoreReceiptAdviceItemData::fromArray((array) $item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            purchaseOrderId: (int) Arr::get($data, 'purchase_order_id'),
            receivedBy: (int) Arr::get($data, 'received_by'),
            items: $items,
            expectedDeliveryDate: Input::stringOrNull(Arr::get($data, 'expected_delivery_date')),
            vehicleNumber: Input::stringOrNull(Arr::get($data, 'vehicle_number')),
            driverName: Input::stringOrNull(Arr::get($data, 'driver_name')),
            driverPhone: Input::stringOrNull(Arr::get($data, 'driver_phone')),
            deliveryNoteNumber: Input::stringOrNull(Arr::get($data, 'delivery_note_number')),
        );
    }
}
