<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
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
        return [
            'sku'   => $this->faker->unique()->bothify('SKU-#####'),
            'name'  => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'stock' => $this->faker->numberBetween(0, 500),
            'price' => $this->faker->randomFloat(2, 1, 299),
        ];
    }

}
