<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FoodDelivery>
 */
class FoodDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => \App\Models\Order::factory(),
            'restaurant_id' => \App\Models\Restaurant::factory(),
            'food_cost' => $this->faker->randomFloat(2, 5, 200),
            'special_instructions' => $this->faker->sentence(),
            'ready_now' => $this->faker->boolean(),
            'minutes_until_ready' => $this->faker->numberBetween(5, 60),
            'files' => null,
            'delivery_fee' => $this->faker->randomFloat(2, 1, 20),
            'service_fee' => $this->faker->randomFloat(2, 1, 10),
        ];
    }
}
