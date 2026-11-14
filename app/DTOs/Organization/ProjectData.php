<?php

namespace App\DTOs\Organization;

use App\Enums\ProjectStatus;
use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class ProjectData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?int $departmentId = null,
        public ?int $costCenterId = null,
        public ?int $managerId = null,
        public ?string $description = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ProjectStatus $status = ProjectStatus::Planned,
        public string $budgetAmount = '0.00',
        public string $currency = 'NGN',
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: strtoupper(trim((string) Arr::get($data, 'code'))),
            name: trim((string) Arr::get($data, 'name')),
            departmentId: Input::intOrNull(Arr::get($data, 'department_id')),
            costCenterId: Input::intOrNull(Arr::get($data, 'cost_center_id')),
            managerId: Input::intOrNull(Arr::get($data, 'manager_id')),
            description: Arr::get($data, 'description'),
            startDate: Arr::get($data, 'start_date'),
            endDate: Arr::get($data, 'end_date'),
            status: Arr::get($data, 'status') instanceof ProjectStatus
                ? Arr::get($data, 'status')
                : ProjectStatus::from((string) Arr::get($data, 'status', ProjectStatus::Planned->value)),
            budgetAmount: (string) Arr::get($data, 'budget_amount', '0.00'),
            currency: strtoupper((string) Arr::get($data, 'currency', 'NGN')),
            isActive: (bool) Arr::get($data, 'is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'department_id' => $this->departmentId,
            'cost_center_id' => $this->costCenterId,
            'manager_id' => $this->managerId,
            'description' => $this->description,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status->value,
            'budget_amount' => $this->budgetAmount,
            'currency' => $this->currency,
            'is_active' => $this->isActive,
        ];
    }
}
