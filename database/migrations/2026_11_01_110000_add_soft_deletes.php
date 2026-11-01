<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penghapusan data dari layar tidak lagi menghapus baris dari database.
 * Kolom deleted_at ditambah pada tabel yang punya tombol Hapus, dan indeks unik
 * yang nilainya mungkin dipakai ulang (kode unit, kode sales, email, nama skema)
 * diturunkan menjadi indeks biasa agar baris lama yang masih tersimpan tidak
 * menghalangi pembuatan data baru dengan kode yang sama.
 */
return new class extends Migration
{
    /** Tabel yang ikut lunak saat dihapus. */
    private const SOFT_TABLES = [
        'users', 'units', 'sales_profiles', 'customer_leads', 'bookings', 'payments', 'payment_schemes', 'public_posts',
    ];

    /** Indeks unik yang dilepas: [tabel, nama indeks, kolom pengganti jadi indeks biasa]. */
    private const RELAXED = [
        ['users', 'users_email_unique', 'email'],
        ['units', 'units_unit_code_unique', 'unit_code'],
        ['sales_profiles', 'sales_profiles_sales_code_unique', 'sales_code'],
        ['payment_schemes', 'payment_schemes_scheme_name_unique', 'scheme_name'],
    ];

    public function up(): void
    {
        foreach (self::SOFT_TABLES as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->softDeletes());
            }
        }

        foreach (self::RELAXED as [$table, $index, $column]) {
            if ($this->indexExists($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($index));
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index([$column], $table . '_' . $column . '_indexed'));
        }
    }

    public function down(): void
    {
        foreach (self::RELAXED as [$table, $index, $column]) {
            if ($this->indexExists($table, $table . '_' . $column . '_indexed')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($table . '_' . $column . '_indexed'));
            }

            if (! $this->indexExists($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($column, $index));
            }
        }

        foreach (self::SOFT_TABLES as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropSoftDeletes());
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) DB::select('show indexes from ' . $table . ' where Key_name = ?', [$index]);
    }
};
