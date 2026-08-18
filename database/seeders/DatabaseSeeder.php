<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $user =  User::factory()->count(5)->create();

        Room::factory()
            ->count(6)
            ->create()
            ->each(function ($room) use ($user) {
                foreach ([1, 2, 3, 4, 5] as $day) {
                    $room->availabilityRules()->create([
                        'day_of_week' => $day,
                        'opens_at' => '09:00',
                        'closes_at' => '18:00',
                    ]);
                }

                Booking::factory()
                    ->count(3)
                    ->create([
                        'room_id' => $room->id,
                        'user_id' => $user->random()->id,
                    ]);
            });
    }
}
