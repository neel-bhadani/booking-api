<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'opens_at' => '09:00',
            'closes_at' => '18:00',
        ];
    }
}
