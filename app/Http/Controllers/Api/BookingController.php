<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $bookings = $request->user()
            ->bookings()
            ->with('room')
            ->when($request->upcoming, fn ($q) => $q->where('starts_at', '>', now()))
            ->orderBy('starts_at')
            ->paginate(15);

        return BookingResource::collection($bookings);
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
    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);

        $booking->load('room');

        return new BookingResource($booking);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        // Fall back to the stored values when a field isn't being changed,
        // so the overlap check always has a complete time window.
        $startsAt = $request->input('starts_at', $booking->starts_at);
        $endsAt = $request->input('ends_at', $booking->ends_at);
        $attendeeCount = $request->input('attendee_count', $booking->attendee_count);

        if ($attendeeCount > $booking->room->capacity) {
            return response()->json([
                'message' => "This room holds {$booking->room->capacity} people.",
            ], 422);
        }

        return DB::transaction(function () use ($request, $booking, $startsAt, $endsAt) {

            $overlaps = Booking::where('room_id', $booking->room_id)
                ->where('status', 'confirmed')
                ->where('id', '!=', $booking->id)   // don't conflict with itself
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->lockForUpdate()
                ->exists();

            if ($overlaps) {
                return response()->json([
                    'message' => 'This room is already booked for that time.',
                ], 409);
            }

            $booking->update($request->validated());

            return new BookingResource($booking->fresh());
        });
    }

    public function destroy(Booking $booking)
    {
        $this->authorize('delete', $booking);

        $booking->update(['status' => 'cancelled']);

        return response()->noContent();
    }
}
