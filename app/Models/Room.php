<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'capacity',
        'is_active',
    ];


    public function availabilityRules()
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    public function blackouts()
    {
        return $this->hasMany(Blackout::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
