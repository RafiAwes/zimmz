<?php

namespace Database\Factories;

use App\Models\Ferry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ferry>
 */
class FerryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'island_id' => $this->faker->numberBetween(1, 10),
            'name' => 'Ferry ' . $this->faker->unique()->word(),
            'days' => $this->faker->randomElements(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], $this->faker->numberBetween(1, 7)),
            'times' => $this->faker->randomElements(['08:00', '09:30', '11:00', '13:00', '15:30', '18:00'], $this->faker->numberBetween(1, 5)),
        ];
    }
}
