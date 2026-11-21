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

    // Cara uang berpindah dari konsumen ke pengembang, terpisah dari payment_type
    // yang menyimpan skema pembiayaannya.
    public const METHOD_TRANSFER = 'transfer bank';
    public const METHOD_TUNAI = 'tunai';
    public const METHOD_QRIS = 'qris';
    public const METHOD_VIRTUAL_ACCOUNT = 'virtual account';
    public const METHOD_CHEQUE = 'cek/giro';

    public const METHODS = [
        self::METHOD_TRANSFER,
        self::METHOD_TUNAI,
        self::METHOD_QRIS,
        self::METHOD_VIRTUAL_ACCOUNT,
        self::METHOD_CHEQUE,
    ];

    public const VERIFY_PENDING = 'pending';
    public const VERIFY_VERIFIED = 'verified';
    public const VERIFY_REJECTED = 'rejected';

    public const VERIFY_STATUSES = [self::VERIFY_PENDING, self::VERIFY_VERIFIED, self::VERIFY_REJECTED];

    // Revisi 43: kategori menjawab "uang ini dibayarkan untuk apa", berbeda dari
    // payment_type (skema pembiayaan) dan payment_method (cara uang berpindah).
    public const CATEGORY_DP = 'dp';
    public const CATEGORY_CICILAN = 'cicilan';
    public const CATEGORY_BIAYA_TAMBAHAN = 'biaya tambahan';

    public const CATEGORIES = [self::CATEGORY_DP, self::CATEGORY_CICILAN, self::CATEGORY_BIAYA_TAMBAHAN];

    // Label kategori untuk tampilan dan pemberitahuan; baris lama tanpa kategori
    // ditampilkan sebagai pembayaran harga unit biasa.
    public const CATEGORY_LABELS = [
        self::CATEGORY_DP             => 'DP',
        self::CATEGORY_CICILAN        => 'Cicilan',
        self::CATEGORY_BIAYA_TAMBAHAN => 'Biaya Tambahan',
    ];

    // Hanya kategori ini yang mengurangi harga utama unit. Baris lama tanpa kategori
    // ikut dihitung karena sebelum revisi 43 seluruh pembayaran adalah uang muka
    // atau angsuran.
    public const KATEGORI_HARGA_UNIT = [self::CATEGORY_DP, self::CATEGORY_CICILAN];

    protected $table = 'payments';

    protected $fillable = [
        'booking_id',
        'payment_type',
        'payment_category',
        'payment_method',
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

    /** Pembayaran yang ikut mengurangi harga utama unit (revisi 43). */
    public function scopeMengurangiHargaUnit($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNull('payment_category')->orWhereIn('payment_category', self::KATEGORI_HARGA_UNIT);
        });
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->payment_category] ?? 'Pembayaran unit';
    }
}
