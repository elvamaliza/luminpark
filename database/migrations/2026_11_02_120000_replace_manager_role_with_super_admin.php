<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aktor Manajer sudah tidak dipakai sebagai akun tersendiri: seluruh fungsinya berjalan pada akun
 * Super Admin. Nilai role 'manager' karenanya digantikan 'super_admin' agar tidak ada lagi peran
 * manajer di basis data, middleware, maupun antarmuka.
 */
return new class extends Migration
{
    public const BARU = 'super_admin';
    public const LAMA = 'manager';

    private const SEMUA = "ENUM('super_admin','admin_sales','sales','consumer')";

    public function up(): void
    {
        // Enum dilebarkan dulu supaya baris bernilai 'manager' masih valid saat pemindahan berlangsung.
        DB::statement('ALTER TABLE users MODIFY role ENUM('
            . "'super_admin','manager','admin_sales','sales','consumer') NOT NULL DEFAULT 'consumer'");

        DB::table('users')->where('role', self::LAMA)->update(['role' => self::BARU]);

        DB::statement('ALTER TABLE users MODIFY role ' . self::SEMUA . " NOT NULL DEFAULT 'consumer'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY role ENUM('
            . "'manager','super_admin','admin_sales','sales','consumer') NOT NULL DEFAULT 'consumer'");

        DB::table('users')->where('role', self::BARU)->update(['role' => self::LAMA]);

        DB::statement("ALTER TABLE users MODIFY role ENUM('manager','admin_sales','sales','consumer') "
            . "NOT NULL DEFAULT 'consumer'");
    }
};
