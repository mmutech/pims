<?php

namespace App\DTOs\Warehouse;

use App\Enums\WarehouseAccessLevel;
use Illuminate\Support\Arr;

final readonly class AccessGrantData
{
    public function __construct(
        public int $userId,
        public WarehouseAccessLevel $accessLevel = WarehouseAccessLevel::Operate,
        public bool $isPrimary = false,
    ) {}

    public static function fromArray(array $data): self
    {
        $level = Arr::get($data, 'access_level', WarehouseAccessLevel::Operate->value);

        return new self(
            userId: (int) Arr::get($data, 'user_id'),
            accessLevel: $level instanceof WarehouseAccessLevel ? $level : WarehouseAccessLevel::from((string) $level),
            isPrimary: (bool) Arr::get($data, 'is_primary', false),
        );
    }
}
