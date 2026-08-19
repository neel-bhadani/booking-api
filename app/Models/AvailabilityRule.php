<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailabilityRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'day_of_week',
        'opens_at',
        'closes_at',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
