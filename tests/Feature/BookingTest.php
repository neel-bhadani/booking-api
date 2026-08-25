<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_booking(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $start = now()->addDays(7)->setTime(11, 0);
        $end = now()->addDays(7)->setTime(12, 0);

        $this->actingAs($user)
            ->postJson('/api/bookings', $this->payload($room, $start, $end))
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Test meeting');

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_overlapping_booking_is_rejected(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $start = now()->addDays(7)->setTime(11, 0);
        $end = now()->addDays(7)->setTime(12, 0);

        // first booking succeeds
        $this->actingAs($user)
            ->postJson('/api/bookings', $this->payload($room, $start, $end))
            ->assertStatus(201);

        // same slot again
        $this->actingAs($user)
            ->postJson('/api/bookings', $this->payload($room, $start, $end))
            ->assertStatus(409);

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_touching_booking_is_allowed(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $day = now()->addDays(7);

        // 11:00 – 12:00
        $this->actingAs($user)
            ->postJson('/api/bookings', $this->payload(
                $room,
                $day->copy()->setTime(11, 0),
                $day->copy()->setTime(12, 0)
            ))
            ->assertStatus(201);

        // 12:00 – 13:00, starts exactly when the first ends
        $this->actingAs($user)
            ->postJson('/api/bookings', $this->payload(
                $room,
                $day->copy()->setTime(12, 0),
                $day->copy()->setTime(13, 0)
            ))
            ->assertStatus(201);

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_partially_overlapping_booking_is_rejected(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $day = now()->addDays(7);

        $this->actingAs($user)
            ->postJson('/api/bookings', $this->payload(
                $room,
                $day->copy()->setTime(11, 0),
                $day->copy()->setTime(12, 0)
            ))
            ->assertStatus(201);

        // starts inside the first booking
        $this->actingAs($user)
            ->postJson('/api/bookings', $this->payload(
                $room,
                $day->copy()->setTime(11, 30),
                $day->copy()->setTime(12, 30)
            ))
            ->assertStatus(409);

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_booking_in_the_past_is_rejected(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $day = now()->subDays(7);

        $this->actingAs($user)
            ->postJson('/api/bookings', $this->payload(
                $room,
                $day->copy()->setTime(11, 0),
                $day->copy()->setTime(12, 0)
            ))
            ->assertStatus(422)
            ->assertJsonValidationErrors('starts_at');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_exceeding_room_capacity_is_rejected(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 5]);

        $day = now()->addDays(7);

        $this->actingAs($user)
            ->postJson('/api/bookings', [
                'room_id' => $room->id,
                'starts_at' => $day->copy()->setTime(11, 0),
                'ends_at' => $day->copy()->setTime(12, 0),
                'title' => 'Too many people',
                'attendee_count' => 10,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_user_id_comes_from_token_not_request_body(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $day = now()->addDays(7);

        $payload = $this->payload(
            $room,
            $day->copy()->setTime(11, 0),
            $day->copy()->setTime(12, 0)
        );

        $payload['user_id'] = $otherUser->id;   // attacker tries to book as someone else

        $this->actingAs($user)
            ->postJson('/api/bookings', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('bookings', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('bookings', ['user_id' => $otherUser->id]);
    }

    private function payload(Room $room, string $start, string $end): array
    {
        return [
            'room_id' => $room->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'title' => 'Test meeting',
            'attendee_count' => 2,
        ];
    }
}
