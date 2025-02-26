<?php

namespace Gnarhard\StripeStorefront\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Gnarhard\StripeStorefront\Models\Price;

/**
 * @template TModel of \Gnarhard\StripeStorefront\Price
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class PriceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = Price::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_id'        => 'price_' . $this->faker->uuid,
            'unit_amount'     => $this->faker->numberBetween(1000, 10000),
            'type'            => 'one_time',
            'payment_link_id' => 'plink_' . $this->faker->uuid,
            'payment_link'    => 'https://example.com/payment/' . $this->faker->uuid,
            'currency'        => 'usd',
        ];
    }
}
