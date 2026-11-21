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

    public const STATUSES = [self::STATUS_COLD, self::STATUS_WARM, self::STATUS_HOT];

    // Kategori dibaca dari keberadaan pemesanan, bukan dari kolom status, karena
    // status cold/warm/hot hanya menggambarkan tahapan penawaran prospek.
    public const CATEGORY_PROSPEK = 'prospek';
    public const CATEGORY_KONSUMEN = 'konsumen';
    public const CATEGORY_DIBATALKAN = 'dibatalkan';
    // Bukan kategori baris, melainkan penapis tambahan pada Database Konsumen yang
    // menampilkan lead yang sudah berpindah (menjadi konsumen atau dibatalkan).
    public const CATEGORY_RIWAYAT = 'riwayat';
    public const CATEGORIES = [self::CATEGORY_PROSPEK, self::CATEGORY_KONSUMEN, self::CATEGORY_DIBATALKAN];

    protected $table = 'customer_leads';

    protected $fillable = [
        'sales_id',
        'unit_id',
        'name',
        'phone',
        'first_chat_date',
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
        'first_chat_date' => 'date',
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

    /**
     * Konsumen = memiliki pemesanan aktif yang pembayarannya sudah ada yang disetujui
     * super admin. Selama belum ada pembayaran terverifikasi, lead tetap tinggal di
     * Database Konsumen sebagai prospek, walau booking sudah dibuat.
     *
     * Pemesanan yang dibatalkan tidak menghapus riwayat, tetapi pemiliknya keluar dari
     * Data Konsumen dan dihitung sebagai pembatalan.
     *
     * Relasi bookings (beserta payments) sebaiknya sudah ikut dimuat pemanggilnya; tanpa
     * relasi terisi fungsi ini berjalan lewat beberapa query exists.
     */
    public function category(): string
    {
        if ($this->sudahBayarTerverifikasi()) {
            return self::CATEGORY_KONSUMEN;
        }

        return $this->pernahDibatalkan() ? self::CATEGORY_DIBATALKAN : self::CATEGORY_PROSPEK;
    }

    /** Ada pembayaran disetujui pada pemesanan yang belum dibatalkan. */
    public function sudahBayarTerverifikasi(): bool
    {
        if ($this->relationLoaded('bookings')) {
            foreach ($this->bookings->where('status', '!=', Booking::STATUS_DIBATALKAN) as $booking) {
                if ($booking->relationLoaded('payments')) {
                    if ($booking->payments->contains('verification_status', Payment::VERIFY_VERIFIED)) {
                        return true;
                    }

                    continue;
                }

                if (Payment::where('booking_id', $booking->id)
                    ->where('verification_status', Payment::VERIFY_VERIFIED)->exists()) {
                    return true;
                }
            }

            return false;
        }

        return $this->bookings()
            ->where('status', '!=', Booking::STATUS_DIBATALKAN)
            ->whereHas('payments', fn ($p) => $p->where('verification_status', Payment::VERIFY_VERIFIED))
            ->exists();
    }

    private function pernahDibatalkan(): bool
    {
        if ($this->relationLoaded('bookings')) {
            return $this->bookings->contains('status', Booking::STATUS_DIBATALKAN);
        }

        return $this->bookings()->where('status', Booking::STATUS_DIBATALKAN)->exists();
    }

    /**
     * Rincian pembatalan terakhir untuk halaman History: nomor pemesanan,
     * waktu, alasan, unit, dan total pembayaran yang sudah disetujui.
     */
    public function pembatalanTerakhir(): ?array
    {
        $booking = $this->bookingPembatalanTerakhir();

        return $booking ? $this->ringkasanBooking($booking) : null;
    }

    /** Booking pembatalan terbaru, atau null bila konsumen belum membatalkan. */
    public function bookingPembatalanTerakhir(): ?Booking
    {
        if (! $this->relationLoaded('bookings')) {
            $this->load(['bookings.payments', 'bookings.unit']);
        }

        return $this->bookings
            ->where('status', Booking::STATUS_DIBATALKAN)
            ->sortByDesc('cancelled_at')
            ->first();
    }

    /** Pemesanan yang belum dibatalkan; dipakai tombol Batal pada Data Konsumen. */
    public function bookingAktif(): ?Booking
    {
        if (! $this->relationLoaded('bookings')) {
            $this->load(['bookings.payments', 'bookings.unit']);
        }

        return $this->bookings
            ->where('status', '!=', Booking::STATUS_DIBATALKAN)
            ->sortByDesc('booking_date')
            ->first();
    }

    /**
     * Ringkasan satu pemesanan untuk halaman History. Selama konsumen masih
     * memegang unit aktif, booking aktif yang ditampilkan; setelah dibatalkan,
     * pembatalan terakhirnya yang menjadi rujukan.
     */
    public function riwayatRingkas(): ?array
    {
        return $this->ringkasanBooking($this->bookingAktif() ?? $this->bookingPembatalanTerakhir());
    }

    private function ringkasanBooking(?Booking $booking): ?array
    {
        if (! $booking) {
            return null;
        }

        if (! $booking->relationLoaded('payments')) {
            $booking->load('payments');
        }

        $terverifikasi = $booking->payments
            ->where('verification_status', Payment::VERIFY_VERIFIED)
            ->sum('amount');

        return [
            'booking_id'        => $booking->id,
            'booking_code'      => $booking->booking_code,
            'booking_date'      => $booking->booking_date,
            'booking_status'    => $booking->status,
            'cancelled_at'      => $booking->cancelled_at,
            'reason'            => $booking->cancellation_reason,
            'unit_code'         => optional($booking->unit)->unit_code,
            'total_price'       => $booking->total_price,
            'addition_cost'     => (float) $booking->addition_cost,
            'delivery_status'   => $booking->delivery_status,
            'verified_amount'   => (float) $terverifikasi,
        ];
    }

    public function isKonsumen(): bool
    {
        return $this->category() === self::CATEGORY_KONSUMEN;
    }

    /** Lead yang belum beralih menjadi konsumen maupun tercatat dibatalkan (cold/warm/hot). */
    public function scopeProspects($query)
    {
        return $query
            ->whereDoesntHave('bookings', fn ($b) => $b
                ->where('status', '!=', Booking::STATUS_DIBATALKAN)
                ->whereHas('payments', fn ($p) => $p->where('verification_status', Payment::VERIFY_VERIFIED)))
            ->whereDoesntHave('bookings', fn ($b) => $b->where('status', Booking::STATUS_DIBATALKAN));
    }

    /** Lead yang masih memiliki pemesanan aktif dengan pembayaran terverifikasi. */
    public function scopeCustomers($query)
    {
        return $query->whereHas('bookings', fn ($b) => $b
            ->where('status', '!=', Booking::STATUS_DIBATALKAN)
            ->whereHas('payments', fn ($p) => $p->where('verification_status', Payment::VERIFY_VERIFIED)));
    }

    /** Lead yang seluruh pemesanannya sudah dibatalkan; riwayat pembeliannya tetap ada. */
    public function scopeCancelled($query)
    {
        return $query->has('bookings')
            ->whereDoesntHave('bookings', fn ($b) => $b->where('status', '!=', Booking::STATUS_DIBATALKAN));
    }

    /** Lead yang pernah memesan, aktif maupun sudah dibatalkan. */
    public function scopeEverBooked($query)
    {
        return $query->has('bookings');
    }

    /**
     * Riwayat Database Konsumen: lead yang sudah berpindah dari daftar prospek,
     * baik menjadi konsumen maupun tercatat pembatalan.
     */
    public function scopeHistory($query)
    {
        return $query->where(fn ($q) => $q->customers()->orWhere(fn ($r) => $r->cancelled()));
    }
}
