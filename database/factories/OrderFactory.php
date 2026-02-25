<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => $this->faker->name(),
            'status' => $this->faker->randomElement(['new', 'pending', 'completed']),
            'details' => $this->faker->sentence(),
            'time' => $this->faker->time(),
            'total_cost' => $this->faker->randomFloat(2, 10, 500),
            'drop_location' => $this->faker->address(),
            'type' => $this->faker->randomElement(['food_delivery', 'ferry_drops']),
        ];
    }
}
