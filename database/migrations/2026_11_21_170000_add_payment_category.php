<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori pembayaran (revisi 43).
 *
 * Jenis pembayaran yang sudah ada menyimpan skema pembiayaannya (cash bertahap,
 * cash keras, KPR). Kolom baru ini menjawab pertanyaan yang berbeda: uang ini
 * dibayarkan untuk apa — uang muka, angsuran, atau biaya tambahan konstruksi.
 * Pembayaran berkategori biaya tambahan berada di luar harga utama unit sehingga
 * tidak ikut mengurangi sisa pembayaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'payment_category')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_category', 20)->nullable()->after('payment_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'payment_category')) {
            Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('payment_category'));
        }
    }
};
