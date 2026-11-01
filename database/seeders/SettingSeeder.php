<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'estate_name'           => ['Perumahan Lumin Park', 'identitas'],
            'estate_address'        => [
                'Jalan Lubuk Minturun, Kelurahan Koto Panjang Ikua Koto, Kecamatan Koto Tangah, '
                . 'Kota Padang, Sumatera Barat',
                'identitas',
            ],
            'estate_phone'          => ['(0751) 550100', 'identitas'],
            'estate_email'          => ['info@luminpark.co.id', 'identitas'],
            'estate_hours'          => ['Senin - Sabtu, 08.00 - 17.00 WIB', 'identitas'],
            'about_text'            => [
                'Lumin Park (Lubuk Minturun Park) adalah pengembang perumahan di Kota Padang yang mengusung '
                . 'konsep hunian minimalis, asri, dan islami. Tersedia beberapa tipe rumah dan cluster dengan '
                . 'skema pembayaran KPR, cash keras, serta cash bertahap.',
                'identitas',
            ],
            'min_dp_percent'        => ['10', 'penjualan'],
            'booking_validity_days' => ['7', 'penjualan'],
            'session_timeout_minutes' => ['1440', 'sistem'],
            'google_login_enabled'  => ['0', 'integrasi'],
            'google_client_id'      => [null, 'integrasi'],
            'google_client_secret'  => [null, 'integrasi'],
            'public_announcement'   => [null, 'publik'],
            'public_hero_eyebrow'   => ['Hunian Minimalis, Asri, dan Islami', 'publik'],
            'public_hero_title'     => [null, 'publik'],
            'public_hero_tagline'   => [
                'Jelajahi unit rumah yang tersedia, bandingkan skema pembayaran, dan hubungi tim sales kami '
                . 'tanpa perlu membuat akun.',
                'publik',
            ],
            'public_hero_image'     => [null, 'publik'],
            'public_featured_count'    => ['3', 'publik'],
            'public_featured_unit_ids' => [null, 'publik'],
            'public_show_unggulan'  => ['1', 'publik'],
            'public_show_skema'     => ['1', 'publik'],
            'public_show_news'      => ['1', 'publik'],
            'public_show_gallery'   => ['1', 'publik'],
            'public_show_sales'     => ['1', 'publik'],
            'public_footer_note'    => [null, 'publik'],
        ];

        foreach ($defaults as $key => [$value, $group]) {
            // Nilai yang sudah diubah pengguna tidak ditimpa saat seeding ulang.
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
    }
}
