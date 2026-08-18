<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Room' . fake()->randomLetter(),
            'location' => 'Floor' . fake()->numberBetween(1, 5),
            'capacity' => fake()->numberBetween(6, 20),
            'is_active' => true,
        ];
    }
}
