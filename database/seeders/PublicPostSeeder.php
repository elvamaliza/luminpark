<?php

namespace Database\Seeders;

use App\Models\PublicPost;
use Illuminate\Database\Seeder;

class PublicPostSeeder extends Seeder
{
    /**
     * Kabar terbaru bawaan halaman publik; seeding ulang tidak menduplikasi judul.
     */
    public function run(): void
    {
        $posts = [
            [
                'title'     => 'Promo Uang Muka Ringan Cluster Kenanga',
                'content'   => 'Uang muka mulai 10 persen dan tenor cash bertahap hingga 24 bulan untuk seluruh unit '
                    . 'tipe Modern Tropical. Berlaku selama persediaan unit masih ada.',
                'posted_on' => '2026-10-01',
            ],
            [
                'title'     => 'Progres Pembangunan Cluster A',
                'content'   => 'Pembangunan tahap satu berupa pondasi dan struktur rumah telah selesai. '
                    . 'Silakan jadwalkan kunjungan lokasi melalui kontak sales kami.',
                'posted_on' => '2026-10-05',
            ],
            [
                'title'     => 'Kawasan Hunian yang Asri dan Islami',
                'content'   => 'Taman lingkungan, mushola, dan sistem satu gerbang mulai beroperasi tahun depan.',
                'posted_on' => '2026-10-12',
            ],
        ];

        foreach ($posts as $post) {
            PublicPost::updateOrCreate(['title' => $post['title']], [
                'content'      => $post['content'],
                'posted_on'    => $post['posted_on'],
                'is_published' => true,
            ]);
        }
    }
}
