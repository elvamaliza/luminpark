<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentScheme extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_KPR = 'KPR';
    public const TYPE_CASH_KERAS = 'cash keras';
    public const TYPE_CASH_BERTAHAP = 'cash bertahap';

    public const TYPES = [self::TYPE_KPR, self::TYPE_CASH_KERAS, self::TYPE_CASH_BERTAHAP];

    protected $table = 'payment_schemes';

    protected $fillable = [
        'scheme_name',
        'description',
        'scheme_type',
        'down_payment_percent',
        'installment_months',
        'interest_rate',
        'is_active',
    ];

    protected $casts = [
        'down_payment_percent' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'installment_months' => 'integer',
        'is_active' => 'boolean',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'payment_scheme_id');
    }

    /**
     * Menghitung uang muka, angsuran per bulan, dan total bunga dari harga unit.
     * Bunga dihitung sederhana (flat) atas pokok setelah uang muka selama masa tenor.
     */
    public function calculateBreakdown(float $totalPrice): array
    {
        $downPayment = round($totalPrice * ((float) $this->down_payment_percent / 100), 2);
        $principal = max(0, round($totalPrice - $downPayment, 2));
        $months = max(0, (int) $this->installment_months);

        if ($months === 0) {
            return [
                'down_payment_amount' => $downPayment,
                'installment_amount' => $principal,
                'installment_months' => 0,
                'interest_total' => 0.0,
            ];
        }

        $interestTotal = round($principal * ((float) $this->interest_rate / 100) * ($months / 12), 2);

        return [
            'down_payment_amount' => $downPayment,
            'installment_amount' => round(($principal + $interestTotal) / $months, 2),
            'installment_months' => $months,
            'interest_total' => $interestTotal,
        ];
    }
}
