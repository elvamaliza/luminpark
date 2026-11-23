<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\CarbonInterface;

class Booking extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_MENUNGGU_DP = 'menunggu dp';
    public const STATUS_DP_LUNAS = 'dp lunas';
    public const STATUS_CICILAN = 'cicilan';
    public const STATUS_LUNAS = 'lunas';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    public const STATUSES = [
        self::STATUS_MENUNGGU_DP,
        self::STATUS_DP_LUNAS,
        self::STATUS_CICILAN,
        self::STATUS_LUNAS,
        self::STATUS_DIBATALKAN,
    ];

    /** Status pelunasan yang masih boleh dihitung ulang dari ledger pembayaran. */
    public const ACTIVE_STATUSES = [
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
        'addition_cost',
        'addition_description',
        'status',
        'delivery_status',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'cancelled_at' => 'datetime',
        'total_price' => 'decimal:2',
        'down_payment_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'addition_cost' => 'decimal:2',
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
        // Pemesanan yang dibatalkan keluar dari perhitungan pelunasan; pembatalan
        // hanya boleh diganti lewat pembaruan status biasa, bukan ledger pembayaran.
        if ($this->status === self::STATUS_DIBATALKAN) {
            return self::STATUS_DIBATALKAN;
        }

        if ((float) $this->remaining_amount <= 0) {
            return self::STATUS_LUNAS;
        }

        // Revisi 43: pembayaran berkategori biaya tambahan berada di luar harga
        // utama unit, jadi tidak ikut menentukan status pelunasan.
        $verified = (float) $this->payments()->verified()->mengurangiHargaUnit()->sum('amount');
        $downPayment = (float) $this->down_payment_amount;

        if ($verified <= 0) {
            return self::STATUS_MENUNGGU_DP;
        }

        return abs($verified - $downPayment) < 0.01 ? self::STATUS_DP_LUNAS : self::STATUS_CICILAN;
    }

    /**
     * Menutup pemesanan tanpa menghapus booking maupun ledger pembayarannya.
     * Unit selalu dikembalikan menjadi tersedia, termasuk bila konsumen sudah
     * lunas atau menempati unitnya.
     */
    public function batalkan(string $reason, ?CarbonInterface $tanggal = null): static
    {
        $this->update([
            'status'              => self::STATUS_DIBATALKAN,
            'cancelled_at'        => $tanggal ?? now(),
            'cancellation_reason' => $reason,
        ]);

        $unit = Unit::find($this->unit_id);

        if ($unit && $unit->status !== Unit::STATUS_AVAILABLE) {
            $unit->update(['status' => Unit::STATUS_AVAILABLE]);
        }

        return $this;
    }
}
