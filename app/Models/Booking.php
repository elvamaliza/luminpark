<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_MENUNGGU_DP = 'menunggu dp';
    public const STATUS_DP_LUNAS = 'dp lunas';
    public const STATUS_CICILAN = 'cicilan';
    public const STATUS_LUNAS = 'lunas';

    public const STATUSES = [
        self::STATUS_MENUNGGU_DP,
        self::STATUS_DP_LUNAS,
        self::STATUS_CICILAN,
        self::STATUS_LUNAS,
    ];

    protected $table = 'bookings';

    protected $fillable = [
        'booking_code',
        'lead_id',
        'sales_id',
        'unit_id',
        'payment_scheme_id',
        'booking_date',
        'total_price',
        'down_payment_amount',
        'installment_amount',
        'remaining_amount',
        'status',
        'delivery_status',
        'notes',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'total_price' => 'decimal:2',
        'down_payment_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    public function customerLead()
    {
        return $this->belongsTo(CustomerLead::class, 'lead_id');
    }

    public function salesProfile()
    {
        return $this->belongsTo(SalesProfile::class, 'sales_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function paymentScheme()
    {
        return $this->belongsTo(PaymentScheme::class, 'payment_scheme_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'booking_id');
    }

    /**
     * Status pelunasan diturunkan dari total pembayaran terverifikasi, bukan dari input pengguna.
     */
    public function settlementStatus(): string
    {
        if ((float) $this->remaining_amount <= 0) {
            return self::STATUS_LUNAS;
        }

        $verified = (float) $this->payments()->where('verification_status', 'verified')->sum('amount');
        $downPayment = (float) $this->down_payment_amount;

        if ($verified <= 0) {
            return self::STATUS_MENUNGGU_DP;
        }

        return abs($verified - $downPayment) < 0.01 ? self::STATUS_DP_LUNAS : self::STATUS_CICILAN;
    }
}
