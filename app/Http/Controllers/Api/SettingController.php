<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    /**
     * Aturan validasi per kunci pengaturan. Kunci di luar daftar ini ditolak.
     */
    public const RULES = [
        'estate_name'             => 'required|string|max:120',
        'estate_address'          => 'required|string|max:255',
        'estate_phone'            => 'required|string|max:20',
        'estate_email'            => 'required|email|max:120',
        'estate_hours'            => 'nullable|string|max:120',
        'about_text'              => 'nullable|string|max:2000',
        'min_dp_percent'          => 'required|numeric|min:0|max:100',
        'booking_validity_days'   => 'required|integer|min:1|max:365',
        'session_timeout_minutes' => 'required|integer|min:15|max:10080',
        'google_login_enabled'    => 'nullable|in:0,1',
        'google_client_id'        => 'nullable|string|max:255',
        'google_client_secret'    => 'nullable|string|max:255',
        'public_announcement'      => 'nullable|string|max:200',
        'public_hero_eyebrow'      => 'nullable|string|max:120',
        'public_hero_title'        => 'nullable|string|max:150',
        'public_hero_tagline'      => 'nullable|string|max:500',
        'public_featured_count'    => 'nullable|integer|min:1|max:12',
        'public_featured_unit_ids' => 'nullable|string|max:255',
        'public_show_unggulan'     => 'nullable|in:0,1',
        'public_show_skema'        => 'nullable|in:0,1',
        'public_show_news'         => 'nullable|in:0,1',
        'public_show_gallery'      => 'nullable|in:0,1',
        'public_show_sales'        => 'nullable|in:0,1',
        'public_footer_note'       => 'nullable|string|max:200',
    ];

    /** Penyamaran yang dikirim ke klien sebagai pengganti rahasia tersimpan. */
    private const SECRET_MASK = '******** (tersimpan)';

    public function index()
    {
        $settings = Setting::orderBy('group')->orderBy('key')->get();
        $values = $settings->pluck('value', 'key');

        if (! empty($values['google_client_secret'])) {
            $values['google_client_secret'] = self::SECRET_MASK;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'items'    => $settings,
                'values'   => $values,
                'editable' => array_keys(self::RULES),
                'google'   => [
                    'secret_configured' => ! empty(Setting::value('google_client_secret')),
                    'client_configured' => ! empty(Setting::value('google_client_id')),
                    'active'            => GoogleAuthController::enabled(),
                    'redirect_uri'      => GoogleAuthController::redirectUri(),
                ],
                'public'   => [
                    'hero_image_url'    => self::publicHeroUrl(),
                    'featured_unit_ids' => self::featuredUnitIds(),
                ],
            ],
        ], 200);
    }

    /**
     * Unggah gambar hero halaman publik; nilainya bukan kunci yang boleh ditulis lewat update().
     */
    public function updatePublicImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'hapus' => 'nullable|in:1,true',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Gambar hero tidak valid. Gunakan JPG/PNG maksimal 2MB.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $lama = Setting::value('public_hero_image');

        if ($request->hasFile('image')) {
            Setting::put('public_hero_image', $request->file('image')->store('publik', 'public'), 'publik');
        } elseif ($request->input('hapus')) {
            Setting::put('public_hero_image', null, 'publik');
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Kirim berkas gambar atau tandai penghapusan.',
            ], 422);
        }

        if ($lama && ($request->hasFile('image') || $request->input('hapus'))) {
            Storage::disk('public')->delete($lama);
        }

        return response()->json([
            'success' => true,
            'message' => $request->hasFile('image')
                ? 'Gambar hero halaman publik tersimpan.'
                : 'Gambar hero halaman publik dihapus.',
            'data'    => ['hero_image_url' => self::publicHeroUrl()],
        ], 200);
    }

    public function update(Request $request)
    {
        $payload = $request->input('settings', []);

        if (! is_array($payload) || $payload === []) {
            return response()->json([
                'success' => false,
                'message' => 'Kirim pasangan kunci dan nilai pada body settings.',
            ], 422);
        }

        $unknown = array_diff(array_keys($payload), array_keys(self::RULES));
        if ($unknown !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Kunci pengaturan tidak dikenali: ' . implode(', ', $unknown),
            ], 422);
        }

        // Aturan berlaku hanya untuk kunci yang dikirim, sehingga pembaruan sebagian dimungkinkan.
        $validator = Validator::make($payload, array_map(
            fn (string $rule) => 'sometimes|' . $rule,
            self::RULES
        ));
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengaturan tidak valid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Saklar hanya boleh menyala bila kredensial sudah lengkap, termasuk yang dikirim pada permintaan yang sama.
        if (($payload['google_login_enabled'] ?? null) === '1') {
            $clientId = $payload['google_client_id'] ?? Setting::value('google_client_id');
            $clientSecret = array_key_exists('google_client_secret', $payload)
                && $payload['google_client_secret'] !== ''
                && $payload['google_client_secret'] !== self::SECRET_MASK
                ? $payload['google_client_secret']
                : Setting::value('google_client_secret');

            if (!$clientId || !$clientSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masuk dengan Google belum dapat diaktifkan. Isi Client ID dan Client Secret lebih dahulu.',
                    'errors'  => ['google_login_enabled' => ['Kredensial Google belum lengkap.']],
                ], 422);
            }
        }

        foreach ($payload as $key => $value) {
            // Kolom rahasia yang dikirim ulang apa adanya (penyamaran) atau dikosongkan tidak menimpa nilai lama.
            if ($key === 'google_client_secret' && ($value === '' || $value === null || $value === self::SECRET_MASK)) {
                continue;
            }

            Setting::put((string) $key, is_scalar($value) ? (string) $value : null, self::groupOf((string) $key));
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan berhasil disimpan.',
            'data'    => Setting::whereIn('key', array_keys($payload))->pluck('value', 'key'),
        ], 200);
    }

    /** Kelompok tampilan kunci; dipertahankan saat nilai diperbarui agar halaman Pengaturan stabil. */
    public static function groupOf(string $key): string
    {
        if (str_starts_with($key, 'public_')) {
            return 'publik';
        }

        if (str_starts_with($key, 'google_')) {
            return 'integrasi';
        }

        if (in_array($key, ['session_timeout_minutes'], true)) {
            return 'sistem';
        }

        return in_array($key, ['min_dp_percent', 'booking_validity_days'], true) ? 'penjualan' : 'identitas';
    }

    /** Lama berlaku token login (menit); dipakai setiap kali sesi baru dibuat. */
    public static function sessionTimeoutMinutes(): int
    {
        $menit = (int) Setting::value('session_timeout_minutes', (string) (config('sanctum.expiration') ?: 1440));

        return $menit >= 15 ? $menit : 1440;
    }

    /** Daftar id unit yang dipin pada seksi Unit Unggulan; kosong berarti urutan otomatis. */
    public static function featuredUnitIds(): array
    {
        $raw = (string) Setting::value('public_featured_unit_ids');

        return array_values(array_filter(array_map('intval', array_map('trim', explode(',', $raw)))));
    }

    public static function publicHeroUrl(): ?string
    {
        $path = Setting::value('public_hero_image');

        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    /**
     * Hanya kunci yang boleh tampil di halaman publik (Beranda, Kontak & Tentang Kami).
     */
    public function publicSettings()
    {
        $values = Setting::whereIn('key', Setting::PUBLIC_KEYS)->pluck('value', 'key');

        $heroUrl = self::publicHeroUrl();
        if ($heroUrl !== null) {
            $values['public_hero_image_url'] = $heroUrl;
        }

        return response()->json([
            'success' => true,
            'data'    => $values,
        ], 200);
    }
}
