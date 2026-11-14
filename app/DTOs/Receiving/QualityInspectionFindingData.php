<?php

namespace App\DTOs\Receiving;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class QualityInspectionFindingData
{
    public function __construct(
        public string $findingType,
        public string $severity,
        public string $description,
        public ?string $evidencePath = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            findingType: (string) Arr::get($data, 'finding_type', 'other'),
            severity: (string) Arr::get($data, 'severity', 'minor'),
            description: (string) Arr::get($data, 'description', ''),
            evidencePath: Input::stringOrNull(Arr::get($data, 'evidence_path')),
        );
    }
}
