<?php

namespace App\DTOs\Administration;

use App\Enums\UserStatus;
use App\Support\Input;
use Illuminate\Support\Arr;

final readonly class UserData
{
    /**
     * @param  list<string>  $roles
     * @param  list<int>  $warehouseIds
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
        public ?string $employeeNumber = null,
        public ?string $phone = null,
        public ?string $jobTitle = null,
        public ?int $departmentId = null,
        public ?int $costCenterId = null,
        public UserStatus $status = UserStatus::Active,
        public array $roles = [],
        public array $warehouseIds = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $status = Arr::get($data, 'status', UserStatus::Active->value);

        return new self(
            name: trim((string) Arr::get($data, 'name')),
            email: strtolower(trim((string) Arr::get($data, 'email'))),
            password: Arr::get($data, 'password'),
            employeeNumber: Arr::get($data, 'employee_number'),
            phone: Arr::get($data, 'phone'),
            jobTitle: Arr::get($data, 'job_title'),
            departmentId: Input::intOrNull(Arr::get($data, 'department_id')),
            costCenterId: Input::intOrNull(Arr::get($data, 'cost_center_id')),
            status: $status instanceof UserStatus ? $status : UserStatus::from((string) $status),
            roles: array_values((array) Arr::get($data, 'roles', [])),
            warehouseIds: array_values(array_map('intval', (array) Arr::get($data, 'warehouse_ids', []))),
        );
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'employee_number' => $this->employeeNumber,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'department_id' => $this->departmentId,
            'cost_center_id' => $this->costCenterId,
            'status' => $this->status->value,
        ];

        if ($this->password !== null && $this->password !== '') {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
