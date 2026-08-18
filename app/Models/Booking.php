<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{

    use HasFactory;

    protected $table = 'bookings';

    protected $fillable = [
        'room_id',
        'user_id',
        'starts_at',
        'ends_at',
        'status',
        'title',
        'attendee_count'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
