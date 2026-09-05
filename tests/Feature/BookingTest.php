<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\AvailabilityService;
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

    public function test_guest_cannot_create_booking(): void
    {
        $room = Room::factory()->create(['capacity' => 10]);

        $day = now()->addDays(7);

        $this->postJson('/api/bookings', $this->payload(
            $room,
            $day->copy()->setTime(11, 0),
            $day->copy()->setTime(12, 0)
        ))->assertStatus(401);

        $this->assertDatabaseCount('bookings', 0);
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

    public function test_user_cannot_view_another_users_booking()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $booking = Booking::factory()->create([
            'user_id' => $userB->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($userA)->getJson("/api/bookings/{$booking->id}")->assertStatus(403);
    }

    public function test_user_cannot_update_another_users_booking()
    {

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $booking = Booking::factory()->create([
            'user_id' => $userB->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($userA)->patchJson("/api/bookings/{$booking->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);
    }

    public function test_user_cannot_cancel_another_users_booking()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $booking = Booking::factory()->create([
            'user_id' => $userB->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($userA)->deleteJson("/api/bookings/{$booking->id}", ['status' => 'cancelled'])
            ->assertStatus(403);
    }

    public function test_admin_can_cancel_another_users_booking()
    {
        $admin = User::factory()->admin()->create();
        $userB = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $booking = Booking::factory()->create([
            'user_id' => $userB->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($admin)->deleteJson("/api/bookings/{$booking->id}", ['status' => 'cancelled'])
            ->assertStatus(204);
    }

    public function test_admin_cannot_update_another_users_booking()
    {
        $admin = User::factory()->admin()->create();
        $userB = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $booking = Booking::factory()->create([
            'user_id' => $userB->id,
            'room_id' => $room->id,
        ]);

        $this->actingAs($admin)->patchJson("/api/bookings/{$booking->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);
    }

    public function test_index_returns_only_own_bookings()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $bookingA = Booking::factory()->count(2)->create([
            'user_id' => $userA->id,
            'room_id' => $room->id,
        ]);
        $bookingB = Booking::factory()->create([
            'user_id' => $userB->id,
            'room_id' => $room->id,
        ]);
        $this->actingAs($userA)
            ->getJson('/api/bookings')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $bookingB->id]);
    }

    public function test_cancel_sets_status_and_keeps_the_row()
    {
        // Arrange — a booking that belongs to this user
        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);

        // Act — cancel it
        $this->actingAs($user)
            ->deleteJson("/api/bookings/{$booking->id}")
            ->assertStatus(204);

        // Assert — the row is STILL THERE, with the new status
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancelled_booking_frees_the_slot(): void
    {
        $date = now()->addDays(7)->setTime(0, 0);

        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $room->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'starts_at' => $date->copy()->setTime(11, 0),
            'ends_at' => $date->copy()->setTime(12, 0),
            'status' => 'confirmed',
        ]);

        // Before: the booking splits the day in two
        $this->assertEquals(
            ['09:00-11:00', '12:00-18:00'],
            $this->freeSlots($room, $date)
        );

        $this->actingAs($user)
            ->deleteJson("/api/bookings/{$booking->id}")
            ->assertStatus(204);

        // After: the slot is bookable again, with no extra logic —
        // AvailabilityService filters on status = confirmed.
        $this->assertEquals(
            ['09:00-18:00'],
            $this->freeSlots($room, $date)
        );
    }

    private function freeSlots(Room $room, $date): array
    {
        return collect((new AvailabilityService)->getFreeSlots($room, $date))
            ->map(fn ($s) => $s['start']->format('H:i').'-'.$s['end']->format('H:i'))
            ->all();
    }

    public function test_updating_only_the_title_succeeds(): void
    {
        $date = now()->addDays(7);

        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'starts_at' => $date->copy()->setTime(11, 0),
            'ends_at' => $date->copy()->setTime(12, 0),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/bookings/{$booking->id}", ['title' => 'Renamed'])
            ->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'title' => 'Renamed',
        ]);
    }

    public function test_rescheduling_to_a_free_slot_succeeds(): void
    {
        $date = now()->addDays(7);

        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'starts_at' => $date->copy()->setTime(11, 0),
            'ends_at' => $date->copy()->setTime(12, 0),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/bookings/{$booking->id}", [
                'starts_at' => $date->copy()->setTime(14, 0)->toDateTimeString(),
                'ends_at' => $date->copy()->setTime(15, 0)->toDateTimeString(),
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'starts_at' => $date->copy()->setTime(14, 0)->toDateTimeString(),
        ]);
    }

    public function test_rescheduling_onto_another_booking_is_rejected(): void
    {
        $date = now()->addDays(7);

        $user = User::factory()->create();
        $room = Room::factory()->create(['capacity' => 10]);

        $mine = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'starts_at' => $date->copy()->setTime(11, 0),
            'ends_at' => $date->copy()->setTime(12, 0),
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'starts_at' => $date->copy()->setTime(15, 0),
            'ends_at' => $date->copy()->setTime(16, 0),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/bookings/{$mine->id}", [
                'starts_at' => $date->copy()->setTime(15, 0)->toDateTimeString(),
                'ends_at' => $date->copy()->setTime(16, 0)->toDateTimeString(),
            ])
            ->assertStatus(409);

        // Original times unchanged
        $this->assertDatabaseHas('bookings', [
            'id' => $mine->id,
            'starts_at' => $date->copy()->setTime(11, 0)->toDateTimeString(),
        ]);
    }
}
