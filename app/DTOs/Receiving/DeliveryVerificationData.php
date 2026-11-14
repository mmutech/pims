<?php

namespace App\DTOs\Receiving;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class DeliveryVerificationData
{
    /**
     * @param  list<DeliveryVerificationItemData>  $items
     */
    public function __construct(
        public int $sraId,
        public int $verifiedBy,
        public array $items,
        public ?string $discrepancySummary = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof DeliveryVerificationItemData
                ? $item
                : DeliveryVerificationItemData::fromArray((array) $item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            sraId: (int) Arr::get($data, 'sra_id'),
            verifiedBy: (int) Arr::get($data, 'verified_by'),
            items: $items,
            discrepancySummary: Input::stringOrNull(Arr::get($data, 'discrepancy_summary')),
        );
    }
}
