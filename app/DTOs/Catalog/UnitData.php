<?php

namespace App\DTOs\Catalog;

use App\Enums\UnitOfMeasureType;
use Illuminate\Support\Arr;

final readonly class UnitData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $symbol,
        public UnitOfMeasureType $type = UnitOfMeasureType::Quantity,
        public int $decimalPlaces = 4,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        $type = Arr::get($data, 'type', UnitOfMeasureType::Quantity->value);

        return new self(
            code: strtoupper(trim((string) Arr::get($data, 'code'))),
            name: trim((string) Arr::get($data, 'name')),
            symbol: trim((string) Arr::get($data, 'symbol')),
            type: $type instanceof UnitOfMeasureType ? $type : UnitOfMeasureType::from((string) $type),
            decimalPlaces: (int) Arr::get($data, 'decimal_places', 4),
            isActive: (bool) Arr::get($data, 'is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'type' => $this->type->value,
            'decimal_places' => $this->decimalPlaces,
            'is_active' => $this->isActive,
        ];
    }
}
