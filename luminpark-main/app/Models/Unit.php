<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_BOOKING = 'booking';
    public const STATUS_SOLD_OUT = 'sold_out';
    public const STATUS_DELIVERY_PROCESS = 'delivery_process';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_BOOKING,
        self::STATUS_SOLD_OUT,
        self::STATUS_DELIVERY_PROCESS,
    ];

    protected $fillable = [
        'cluster_id',
        'unit_code',
        'unit_type',
        'land_size',
        'building_size',
        'price',
        'status',
        'description',
        'image',
    ];

    protected $appends = ['image_url'];

    protected $casts = [
        'price' => 'decimal:2',
        'land_size' => 'integer',
        'building_size' => 'integer',
    ];

    public function cluster()
    {
        return $this->belongsTo(Cluster::class, 'cluster_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'unit_id');
    }

    public function interestedLeads()
    {
        return $this->hasMany(CustomerLead::class, 'unit_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . ltrim($this->image, '/')) : null;
    }
}
