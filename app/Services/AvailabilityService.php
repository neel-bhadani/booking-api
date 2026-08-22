<?php

namespace App\Services;

use App\Models\Blackout;
use App\Models\Room;
use Carbon\Carbon;

class AvailabilityService
{
    public function getFreeSlots(Room $room, Carbon $date): array
    {
        // STEP 1 — the room's rule for this weekday
        $rule = $room->availabilityRules()
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (! $rule) {
            return [];
        }

        // STEP 2 — the day's opening window
        $freeBlocks = [[
            'start' => $date->copy()->setTimeFromTimeString($rule->opens_at),
            'end' => $date->copy()->setTimeFromTimeString($rule->closes_at),
        ]];

        // STEP 3 — collect busy periods
        $busy = $this->getBusyPeriods($room, $date);

        // STEP 4 — subtract each busy period
        foreach ($busy as $period) {
            $freeBlocks = $this->subtract($freeBlocks, $period);
        }

        return $freeBlocks;
    }

    public function getBusyPeriods(Room $room, Carbon $date): array
    {
        $bookings = $room->bookings()
            ->where('status', 'confirmed')
            ->whereDate('starts_at', $date->toDateString())
            ->get()
            ->map(fn ($b) => ['start' => $b->starts_at, 'end' => $b->ends_at])
            ->all();

        $blackouts = Blackout::query()
            ->where(function ($q) use ($room) {
                $q->where('room_id', $room->id)->orWhereNull('room_id');
            })
            ->whereDate('starts_at', $date->toDateString())
            ->get()
            ->map(fn ($b) => ['start' => $b->starts_at, 'end' => $b->ends_at])
            ->all();

        $periods = array_merge($bookings, $blackouts);

        usort($periods, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $periods;
    }

    private function subtract(array $freeBlocks, array $busy): array
    {
        $result = [];

        foreach ($freeBlocks as $block) {
            if ($busy['end']->lte($block['start']) || $busy['start']->gte($block['end'])) {
                $result[] = $block;

                continue;
            }

            if ($busy['start']->gt($block['start'])) {
                $result[] = [
                    'start' => $block['start'],
                    'end' => $busy['start'],
                ];
            }

            if ($busy['end']->lt($block['end'])) {
                $result[] = [
                    'start' => $busy['end'],
                    'end' => $block['end'],
                ];
            }
        }

        return $result;
    }
}
