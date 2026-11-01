<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_MANAGER = 'manager';
    public const ROLE_ADMIN_SALES = 'admin_sales';
    public const ROLE_SALES = 'sales';
    public const ROLE_CONSUMER = 'consumer';

    /** Peran yang berhak memakai sistem internal; konsumen hanya berinteraksi lewat halaman publik. */
    public const ROLE_INTERNAL = [self::ROLE_MANAGER, self::ROLE_ADMIN_SALES, self::ROLE_SALES];

    public const STATUS_AKTIF = 'aktif';
    public const STATUS_NONAKTIF = 'nonaktif';

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'role',
        'phone',
        'address',
        'avatar',
        'status',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'avatar',
        'google_id',
    ];

    protected $appends = ['avatar_url', 'google_linked'];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'password'           => 'hashed',
        'must_change_password' => 'boolean',
    ];

    public function salesProfile(): HasOne
    {
        return $this->hasOne(SalesProfile::class, 'user_id', 'id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function loginSessions(): HasMany
    {
        return $this->hasMany(LoginSession::class, 'user_id');
    }

    public function systemLogs(): HasMany
    {
        return $this->hasMany(SystemLog::class, 'user_id');
    }

    public function verifiedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'verified_by');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . ltrim($this->avatar, '/')) : null;
    }

    public function getGoogleLinkedAttribute(): bool
    {
        return $this->google_id !== null;
    }

    public function isOperational(): bool
    {
        return in_array($this->role, [self::ROLE_MANAGER, self::ROLE_ADMIN_SALES], true);
    }
}
