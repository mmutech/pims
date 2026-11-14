<?php

namespace Database\Factories;

use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    public function definition(): array
    {
        return [
            'department_id' => null,
            'code' => 'CC-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(2, true).' Cost Centre',
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department->id,
        ]);
    }
}
