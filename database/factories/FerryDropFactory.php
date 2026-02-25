<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FerryDrop>
 */
class FerryDropFactory extends Factory
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
            'pickup_location' => $this->faker->address(),
            'ferry_id' => \App\Models\Ferry::factory(),
            'island_id' => \App\Models\Island::factory(),
            'drop_fee' => $this->faker->randomFloat(2, 5, 50),
            'package_fee' => $this->faker->randomFloat(2, 2, 20),
        ];
    }
}
