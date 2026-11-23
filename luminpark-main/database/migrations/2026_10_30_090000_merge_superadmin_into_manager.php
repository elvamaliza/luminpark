<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan memakai empat aktor: Manajer/Super Admin, Admin Sales, Sales, Konsumen.
     * Peran 'superadmin' dilebur ke 'manager', dan kolom pendukung login Google
     * serta kata sandi awal sales ditambahkan.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'manager']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id', 64)->nullable()->unique()->after('password');
            $table->boolean('must_change_password')->default(false)->after('status');
        });

        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('manager','admin_sales','sales','consumer') NOT NULL DEFAULT 'consumer'"
        );

        Schema::table('sales_profiles', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('join_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales_profiles', function (Blueprint $table) {
            $table->dropColumn('birth_date');
        });

        DB::statement(
            "ALTER TABLE users MODIFY role ENUM('superadmin','manager','admin_sales','sales','consumer') NOT NULL DEFAULT 'consumer'"
        );

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'must_change_password']);
        });
    }
};
