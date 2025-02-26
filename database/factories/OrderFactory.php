<?php

namespace Gnarhard\StripeStorefront\Database\Factories;

use Gnarhard\StripeStorefront\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @template TModel of \Gnarhard\StripeStorefront\Order
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stripe_session_id' => 'cs_test_'.$this->faker->uuid,
            'email' => $this->faker->email,
            'total' => $this->faker->numberBetween(1000, 10000),
        ];
    }
}
