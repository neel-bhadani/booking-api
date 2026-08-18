<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+10 days');
        $end = (clone $start)->modify('+1 hour');

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => 'confirmed',
            'title' => fake()->sentence(3),
            'attendee_count' => fake()->numberBetween(1, 8),
        ];
    }
}
