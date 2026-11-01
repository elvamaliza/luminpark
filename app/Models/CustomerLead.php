<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_COLD = 'cold';
    public const STATUS_WARM = 'warm';
    public const STATUS_HOT = 'hot';
    public const STATUS_BOOKING = 'booking';

    public const STATUSES = [self::STATUS_COLD, self::STATUS_WARM, self::STATUS_HOT, self::STATUS_BOOKING];

    protected $table = 'customer_leads';

    protected $fillable = [
        'sales_id',
        'unit_id',
        'name',
        'phone',
        'email',
        'address',
        'status',
        'interested_unit',
        'budget',
        'note',
        'last_follow_up',
        'source',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'last_follow_up' => 'date',
    ];

    public function salesProfile()
    {
        return $this->belongsTo(SalesProfile::class, 'sales_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function followUpHistories()
    {
        return $this->hasMany(FollowUpHistory::class, 'lead_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'lead_id');
    }
}
