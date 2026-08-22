<?php

namespace Tests\Feature;

use App\Models\Blackout;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function roomOpenNineToSix(Carbon $date): Room
    {
        $room = Room::factory()->create();

        $room->availabilityRules()->create([
            'day_of_week' => $date->dayOfWeek,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
        ]);

        return $room;
    }

    public function test_returns_full_window_when_nothing_is_booked(): void
    {
        $date = Carbon::parse('2026-09-01');      // fixed date, not now()
        $room = $this->roomOpenNineToSix($date);

        $slots = (new AvailabilityService)->getFreeSlots($room, $date);

        $this->assertCount(1, $slots);
        $this->assertEquals('09:00', $slots[0]['start']->format('H:i'));
        $this->assertEquals('18:00', $slots[0]['end']->format('H:i'));
    }

    public function test_splits_around_a_booking(): void
    {
        $date = Carbon::parse('2026-09-01');
        $room = $this->roomOpenNineToSix($date);
        $user = User::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'starts_at' => $date->copy()->setTime(11, 0),
            'ends_at' => $date->copy()->setTime(12, 0),
            'status' => 'confirmed',
        ]);

        $slots = (new AvailabilityService)->getFreeSlots($room, $date);

        $this->assertCount(2, $slots);
    }

    public function test_trims_when_booking_covers_the_opening(): void
    {
        $date = Carbon::parse('2026-09-01');
        $room = $this->roomOpenNineToSix($date);
        $user = User::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'starts_at' => $date->copy()->setTime(8, 0),
            'ends_at' => $date->copy()->setTime(10, 0),
            'status' => 'confirmed',
        ]);

        $this->assertEquals(['10:00-18:00'], $this->formatted($room, $date));
    }

    public function test_returns_empty_when_fully_booked(): void
    {
        $date = Carbon::parse('2026-09-01');
        $room = $this->roomOpenNineToSix($date);
        $user = User::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'starts_at' => $date->copy()->setTime(8, 0),
            'ends_at' => $date->copy()->setTime(19, 0),
            'status' => 'confirmed',
        ]);

        $this->assertEmpty($this->formatted($room, $date));
    }

    public function test_returns_empty_when_no_rule_for_that_weekday(): void
    {
        $tuesday = Carbon::parse('2026-09-01');
        $room = $this->roomOpenNineToSix($tuesday);

        $wednesday = Carbon::parse('2026-09-02');

        $this->assertEmpty($this->formatted($room, $wednesday));
    }

    public function test_no_zero_length_gap_between_adjacent_bookings(): void
    {
        $date = Carbon::parse('2026-09-01');
        $room = $this->roomOpenNineToSix($date);
        $user = User::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'starts_at' => $date->copy()->setTime(11, 0),
            'ends_at' => $date->copy()->setTime(12, 0),
            'status' => 'confirmed',
        ]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'starts_at' => $date->copy()->setTime(12, 0),
            'ends_at' => $date->copy()->setTime(13, 0),
            'status' => 'confirmed',
        ]);

        $this->assertEquals(
            ['09:00-11:00', '13:00-18:00'],
            $this->formatted($room, $date)
        );
    }

    public function test_subtracts_a_room_specific_blackout(): void
    {
        $date = Carbon::parse('2026-09-01');
        $room = $this->roomOpenNineToSix($date);

        Blackout::create([
            'room_id' => $room->id,
            'starts_at' => $date->copy()->setTime(14, 0),
            'ends_at' => $date->copy()->setTime(15, 0),
            'reason' => 'Maintenance',
        ]);

        $this->assertEquals(
            ['09:00-14:00', '15:00-18:00'],
            $this->formatted($room, $date)
        );
    }

    public function test_subtracts_a_global_blackout(): void
    {
        $date = Carbon::parse('2026-09-01');
        $room = $this->roomOpenNineToSix($date);

        Blackout::create([
            'room_id' => null,
            'starts_at' => $date->copy()->setTime(14, 0),
            'ends_at' => $date->copy()->setTime(15, 0),
            'reason' => 'Building closed',
        ]);

        $this->assertEquals(
            ['09:00-14:00', '15:00-18:00'],
            $this->formatted($room, $date)
        );
    }

    public function test_combines_bookings_and_blackouts_in_order(): void
    {
        $date = Carbon::parse('2026-09-01');
        $room = $this->roomOpenNineToSix($date);
        $user = User::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'starts_at' => $date->copy()->setTime(11, 0),
            'ends_at' => $date->copy()->setTime(12, 0),
            'status' => 'confirmed',
        ]);

        Blackout::create([
            'room_id' => $room->id,
            'starts_at' => $date->copy()->setTime(14, 0),
            'ends_at' => $date->copy()->setTime(15, 0),
            'reason' => 'Maintenance',
        ]);

        $this->assertEquals(
            ['09:00-11:00', '12:00-14:00', '15:00-18:00'],
            $this->formatted($room, $date)
        );
    }

    private function formatted(Room $room, Carbon $date): array
    {
        return collect((new AvailabilityService)->getFreeSlots($room, $date))
            ->map(fn ($s) => $s['start']->format('H:i').'-'.$s['end']->format('H:i'))
            ->all();
    }
}
