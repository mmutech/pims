<?php

namespace App\DTOs\Receiving;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class QualityInspectionItemData
{
    /**
     * @param  list<QualityInspectionFindingData>  $findings
     */
    public function __construct(
        public int $sraItemId,
        public string $inspectedQuantity,
        public string $acceptedQuantity = '0',
        public string $rejectedQuantity = '0',
        public string $quarantinedQuantity = '0',
        public ?string $result = null,
        public ?string $notes = null,
        public array $findings = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $findings = array_map(
            fn ($finding) => $finding instanceof QualityInspectionFindingData
                ? $finding
                : QualityInspectionFindingData::fromArray((array) $finding),
            (array) Arr::get($data, 'findings', []),
        );

        return new self(
            sraItemId: (int) Arr::get($data, 'sra_item_id'),
            inspectedQuantity: (string) Arr::get($data, 'inspected_quantity', '0'),
            acceptedQuantity: (string) Arr::get($data, 'accepted_quantity', '0'),
            rejectedQuantity: (string) Arr::get($data, 'rejected_quantity', '0'),
            quarantinedQuantity: (string) Arr::get($data, 'quarantined_quantity', '0'),
            result: Input::stringOrNull(Arr::get($data, 'result')),
            notes: Input::stringOrNull(Arr::get($data, 'notes')),
            findings: $findings,
        );
    }
}
