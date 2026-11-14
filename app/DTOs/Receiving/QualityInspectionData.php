<?php

namespace App\DTOs\Receiving;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class QualityInspectionData
{
    /**
     * @param  list<QualityInspectionItemData>  $items
     */
    public function __construct(
        public int $sraId,
        public int $inspectorId,
        public array $items,
        public ?string $method = null,
        public ?string $sampleSize = null,
        public ?string $summary = null,
        public ?string $inspectionDate = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn ($item) => $item instanceof QualityInspectionItemData
                ? $item
                : QualityInspectionItemData::fromArray((array) $item),
            (array) Arr::get($data, 'items', []),
        );

        return new self(
            sraId: (int) Arr::get($data, 'sra_id'),
            inspectorId: (int) Arr::get($data, 'inspector_id'),
            items: $items,
            method: Input::stringOrNull(Arr::get($data, 'method')),
            sampleSize: Input::stringOrNull(Arr::get($data, 'sample_size')),
            summary: Input::stringOrNull(Arr::get($data, 'summary')),
            inspectionDate: Input::stringOrNull(Arr::get($data, 'inspection_date')),
        );
    }
}
