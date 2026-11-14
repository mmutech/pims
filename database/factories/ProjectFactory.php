<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Project;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'code' => 'PRJ-'.fake()->unique()->numerify('#####'),
            'name' => fake()->unique()->catchPhrase().' Project',
            'description' => fake()->sentence(),
            'department_id' => null,
            'cost_center_id' => null,
            'manager_id' => null,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => ProjectStatus::Planned,
            'budget_amount' => '1000000.00',
            'currency' => 'NGN',
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Active,
        ]);
    }
}
