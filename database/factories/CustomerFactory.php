<?php

namespace Gnarhard\StripeStorefront\Database\Factories;

use Gnarhard\StripeStorefront\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Customer>
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'phone' => $this->faker->phoneNumber,
            'address' => [
                'line1' => $this->faker->streetAddress,
                'line2' => 'Apt '.$this->faker->buildingNumber(),
                'city' => $this->faker->city,
                'state' => $this->faker->randomElement(['CO', 'CA', 'NY', 'TX']),
                'postal_code' => $this->faker->postcode,
                'country' => $this->faker->countryCode,
            ],
        ];
    }
}
