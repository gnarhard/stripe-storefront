<?php


namespace Gnarhard\StripeStorefront\Database\Factories;

use Gnarhard\StripeStorefront\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @template TModel of \Gnarhard\StripeStorefront\Customer
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'    => $this->faker->name,
            'email'   => $this->faker->email,
            'phone'   => $this->faker->phoneNumber,
            'address' => [
                'line1'       => $this->faker->streetAddress,
                'line2'       => $this->faker->secondaryAddress,
                'city'        => $this->faker->city,
                'state'       => $this->faker->state,
                'postal_code' => $this->faker->postcode,
                'country'     => $this->faker->countryCode,
            ],
        ];
    }
}
