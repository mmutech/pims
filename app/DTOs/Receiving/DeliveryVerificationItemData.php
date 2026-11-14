<?php

namespace App\DTOs\Receiving;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class DeliveryVerificationItemData
{
    public function __construct(
        public int $sraItemId,
        public string $verifiedQuantity,
        public string $damagedQuantity = '0',
        public string $shortQuantity = '0',
        public ?string $condition = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sraItemId: (int) Arr::get($data, 'sra_item_id'),
            verifiedQuantity: (string) Arr::get($data, 'verified_quantity', '0'),
            damagedQuantity: (string) Arr::get($data, 'damaged_quantity', '0'),
            shortQuantity: (string) Arr::get($data, 'short_quantity', '0'),
            condition: Input::stringOrNull(Arr::get($data, 'condition')),
            notes: Input::stringOrNull(Arr::get($data, 'notes')),
        );
    }
}
