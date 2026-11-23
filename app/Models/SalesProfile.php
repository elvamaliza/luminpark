<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'sales_profiles';

    protected $fillable = [
        'user_id',
        'sales_code',
        'join_date',
        'birth_date',
        'status',
        'whatsapp_number',
    ];

    protected $casts = [
        'join_date'  => 'date',
        'birth_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function customerLeads()
    {
        return $this->hasMany(CustomerLead::class, 'sales_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'sales_id');
    }

    public function followUpHistories()
    {
        return $this->hasMany(FollowUpHistory::class, 'sales_id');
    }

    public function targets()
    {
        return $this->hasMany(SalesTarget::class, 'sales_id');
    }
}
