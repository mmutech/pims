<?php

namespace App\DTOs\Warehouse;

use App\Enums\WarehouseLocationType;
use Illuminate\Support\Arr;

final readonly class LocationData
{
    public function __construct(
        public string $code,
        public string $name,
        public WarehouseLocationType $type = WarehouseLocationType::Storage,
        public ?string $description = null,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        $type = Arr::get($data, 'type', WarehouseLocationType::Storage->value);

        return new self(
            code: strtoupper(trim((string) Arr::get($data, 'code'))),
            name: trim((string) Arr::get($data, 'name')),
            type: $type instanceof WarehouseLocationType ? $type : WarehouseLocationType::from((string) $type),
            description: Arr::get($data, 'description'),
            isActive: (bool) Arr::get($data, 'is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
