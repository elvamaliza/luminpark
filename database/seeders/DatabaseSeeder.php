<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Urutan pemaketan mengikuti dependensi relasi antar tabel.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SettingSeeder::class,
            PublicPostSeeder::class,
            UnitSeeder::class,
            PaymentSchemeSeeder::class,
            DemoFlowSeeder::class,
        ]);
    }
}
