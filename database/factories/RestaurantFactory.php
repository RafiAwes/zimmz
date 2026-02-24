<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;


class RestaurantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Restaurant',
            'lat' => (string) $this->faker->latitude(),
            'long' => (string) $this->faker->longitude(),
        ];
    }
}
