<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_CASH_BERTAHAP = 'cash bertahap';
    public const TYPE_CASH_KERAS = 'cash keras';
    public const TYPE_KPR = 'KPR';

    public const TYPES = [self::TYPE_CASH_BERTAHAP, self::TYPE_CASH_KERAS, self::TYPE_KPR];

    public const VERIFY_PENDING = 'pending';
    public const VERIFY_VERIFIED = 'verified';
    public const VERIFY_REJECTED = 'rejected';

    public const VERIFY_STATUSES = [self::VERIFY_PENDING, self::VERIFY_VERIFIED, self::VERIFY_REJECTED];

    protected $table = 'payments';

    protected $fillable = [
        'booking_id',
        'payment_type',
        'amount',
        'payment_date',
        'bank_name',
        'account_number',
        'account_holder',
        'transfer_receipt',
        'verification_status',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', self::VERIFY_VERIFIED);
    }
}
