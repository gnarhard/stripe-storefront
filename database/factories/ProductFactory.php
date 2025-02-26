<?php

namespace Gnarhard\StripeStorefront\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Gnarhard\StripeStorefront\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['lessons', 'merch'];

        return [
            'name' => $this->faker->name,
            'slug' => $this->faker->slug,
            'description' => $this->faker->sentence,
            'price_id' => 'price_'.$this->faker->word(),
            'stripe_id' => 'prod_'.$this->faker->word(),
            'metadata' => [
                'category' => $this->faker->randomElement($types),
                'featured' => $this->faker->boolean,
                'short_description' => $this->faker->sentence,
            ],
        ];
    }
}
