<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    /**
     * Kunci yang boleh dibaca publik (Beranda, Kontak, Tentang Kami).
     * Gambar hero dikirim sebagai URL terpisah oleh SettingController::publicSettings.
     */
    public const PUBLIC_KEYS = [
        'estate_name',
        'estate_address',
        'estate_phone',
        'estate_email',
        'estate_hours',
        'about_text',
        'min_dp_percent',
        'booking_validity_days',
        'public_announcement',
        'public_hero_eyebrow',
        'public_hero_title',
        'public_hero_tagline',
        'public_featured_count',
        'public_show_unggulan',
        'public_show_skema',
        'public_show_peta',
        'public_show_news',
        'public_show_gallery',
        'public_show_sales',
        'public_footer_note',
    ];

    public static function value(string $key, ?string $default = null): ?string
    {
        $row = static::where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function put(string $key, ?string $value, string $group = 'umum'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    public function group(): string
    {
        return $this->group;
    }
}
