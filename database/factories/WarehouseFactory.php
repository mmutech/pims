<?php

namespace Database\Factories;

use App\Domain\Organization\Models\BusinessUnit;
use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\WarehouseType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'business_unit_id' => BusinessUnit::factory(),
            'code' => 'WH-'.fake()->unique()->numerify('#####'),
            'name' => fake()->unique()->company().' Warehouse',
            'type' => WarehouseType::Central,
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'Nigeria',
            'postal_code' => fake()->postcode(),
            'phone' => fake()->numerify('+234 80# ### ####'),
            'email' => fake()->unique()->companyEmail(),
            'manager_id' => null,
            'is_active' => true,
        ];
    }

    public function forBusinessUnit(BusinessUnit $businessUnit): static
    {
        return $this->state(fn (array $attributes) => [
            'business_unit_id' => $businessUnit->id,
        ]);
    }

    public function ofType(WarehouseType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
