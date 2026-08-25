<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBookingRequest $request)
    {
        return DB::transaction(function () use ($request) {

            $room = Room::findOrFail($request->room_id);

            // 1. Capacity check
            if ($request->attendee_count > $room->capacity) {
                return response()->json([
                    'message' => "This room holds {$room->capacity} people.",
                ], 422);
            }

            // 2. Overlap check
            $overlaps = Booking::where('room_id', $request->room_id)
                ->where('status', 'confirmed')
                ->where('starts_at', '<', $request->ends_at)
                ->where('ends_at', '>', $request->starts_at)
                ->lockForUpdate()
                ->exists();

            if ($overlaps) {
                return response()->json([
                    'message' => 'This room is already booked for that time.',
                ], 409);
            }

            $booking = Booking::create([
                'room_id' => $request->room_id,
                'starts_at' => $request->starts_at,
                'ends_at' => $request->ends_at,
                'title' => $request->title,
                'attendee_count' => $request->attendee_count,
                'user_id' => $request->user()->id,
                'status' => 'confirmed',
            ]);

            // 3. Return 201, not 200
            return (new BookingResource($booking))
                ->response()
                ->setStatusCode(201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
