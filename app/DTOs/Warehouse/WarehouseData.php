<?php

namespace App\DTOs\Warehouse;

use App\Enums\WarehouseType;
use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class WarehouseData
{
    public function __construct(
        public int $businessUnitId,
        public string $code,
        public string $name,
        public WarehouseType $type = WarehouseType::Central,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $country = null,
        public ?string $postalCode = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?int $managerId = null,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        $type = Arr::get($data, 'type', WarehouseType::Central->value);

        return new self(
            businessUnitId: (int) Arr::get($data, 'business_unit_id'),
            code: strtoupper(trim((string) Arr::get($data, 'code'))),
            name: trim((string) Arr::get($data, 'name')),
            type: $type instanceof WarehouseType ? $type : WarehouseType::from((string) $type),
            address: Arr::get($data, 'address'),
            city: Arr::get($data, 'city'),
            state: Arr::get($data, 'state'),
            country: Arr::get($data, 'country'),
            postalCode: Arr::get($data, 'postal_code'),
            phone: Arr::get($data, 'phone'),
            email: Arr::get($data, 'email'),
            managerId: Input::intOrNull(Arr::get($data, 'manager_id')),
            isActive: (bool) Arr::get($data, 'is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'business_unit_id' => $this->businessUnitId,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
            'phone' => $this->phone,
            'email' => $this->email,
            'manager_id' => $this->managerId,
            'is_active' => $this->isActive,
        ];
    }
}
