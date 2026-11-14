<?php

namespace App\DTOs\Warehouse;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class BinData
{
    public function __construct(
        public string $code,
        public ?string $name = null,
        public ?string $maxCapacity = null,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: strtoupper(trim((string) Arr::get($data, 'code'))),
            name: Arr::get($data, 'name'),
            maxCapacity: Input::stringOrNull(Arr::get($data, 'max_capacity')),
            isActive: (bool) Arr::get($data, 'is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'max_capacity' => $this->maxCapacity,
            'is_active' => $this->isActive,
        ];
    }
}
