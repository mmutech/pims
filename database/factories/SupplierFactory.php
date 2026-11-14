<?php

namespace Database\Factories;

use App\Domain\Supplier\Models\Supplier;
use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'code' => 'SUP-'.fake()->unique()->numerify('#####'),
            'name' => fake()->unique()->company(),
            'legal_name' => fake()->company().' Limited',
            'tax_identification_number' => 'TIN-'.fake()->unique()->numerify('##########'),
            'registration_number' => 'RC-'.fake()->unique()->numerify('######'),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('+234 80# ### ####'),
            'website' => fake()->url(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'Nigeria',
            'postal_code' => fake()->postcode(),
            'contact_person' => fake()->name(),
            'status' => SupplierStatus::Pending,
            'is_approved' => false,
            'approved_at' => null,
            'approved_by' => null,
            'created_by' => null,
            'rating' => null,
            'notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierStatus::Active,
            'is_approved' => true,
            'approved_at' => now(),
            'rating' => '4.00',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierStatus::Suspended,
        ]);
    }

    public function blacklisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierStatus::Blacklisted,
        ]);
    }
}
