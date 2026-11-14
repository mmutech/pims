<?php

namespace App\DTOs\Catalog;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class CategoryData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?int $parentId = null,
        public ?string $description = null,
        public bool $trackBatches = false,
        public bool $trackSerials = false,
        public bool $requiresQualityInspection = true,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: strtoupper(trim((string) Arr::get($data, 'code'))),
            name: trim((string) Arr::get($data, 'name')),
            parentId: Input::intOrNull(Arr::get($data, 'parent_id')),
            description: Arr::get($data, 'description'),
            trackBatches: (bool) Arr::get($data, 'track_batches', false),
            trackSerials: (bool) Arr::get($data, 'track_serials', false),
            requiresQualityInspection: (bool) Arr::get($data, 'requires_quality_inspection', true),
            isActive: (bool) Arr::get($data, 'is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'parent_id' => $this->parentId,
            'description' => $this->description,
            'track_batches' => $this->trackBatches,
            'track_serials' => $this->trackSerials,
            'requires_quality_inspection' => $this->requiresQualityInspection,
            'is_active' => $this->isActive,
        ];
    }
}
