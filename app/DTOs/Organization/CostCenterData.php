<?php

namespace App\DTOs\Organization;

use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class CostCenterData
{
    public function __construct(
        public ?int $departmentId,
        public string $code,
        public string $name,
        public ?string $description = null,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            departmentId: Input::intOrNull(Arr::get($data, 'department_id')),
            code: strtoupper(trim((string) Arr::get($data, 'code'))),
            name: trim((string) Arr::get($data, 'name')),
            description: Arr::get($data, 'description'),
            isActive: (bool) Arr::get($data, 'is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'department_id' => $this->departmentId,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
