<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya tambahan konstruksi pada pemesanan (revisi 15 butir 3).
 *
 * Permintaan pekerjaan tambahan dari konsumen dicatat di luar harga utama unit,
 * sehingga rincian uang muka dan angsuran yang dihitung dari harga unit tidak
 * berubah. Nilai ini hanya ikut tampil pada booking dan laporan pembayarannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'addition_cost')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->decimal('addition_cost', 15, 2)->default(0)->after('remaining_amount');
            });
        }

        if (! Schema::hasColumn('bookings', 'addition_description')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('addition_description', 255)->nullable()->after('addition_cost');
            });
        }
    }

    public function down(): void
    {
        foreach (['addition_description', 'addition_cost'] as $kolom) {
            if (Schema::hasColumn('bookings', $kolom)) {
                Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn($kolom));
            }
        }
    }
};
