<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema untuk daftar revisi sistem.
 *
 * - Tanggal chat awal konsumen (revisi 2): kolom baru pada customer_leads.
 * - Status konsumen dibedakan dari pemesanan (revisi 7): prospek hanya boleh
 *   berstatus cold/warm/hot. Nilai booking lama dipindah menjadi hot karena
 *   kepastian membeli dibaca dari keberadaan baris pada tabel bookings, bukan
 *   dari kolom status lead.
 * - Pembatalan pemesanan (revisi 5): status baru pada bookings beserta waktu dan
 *   alasan pembatalan. Baris booking dan pembayaran tidak dihapus sehingga
 *   riwayat pembelian tetap dapat dilihat.
 * - Metode pembayaran (revisi 3): kolom baru pada payments, terpisah dari
 *   payment_type yang menyimpan skema pembiayaan.
 * - Lokasi proyek untuk peta beranda (revisi 12): kolom alamat dan koordinat
 *   pada clusters.
 *
 * Semua langkah ditest lebih dahulu supaya migrasi dapat dijalankan berulang
 * pada basis data yang sudah berisi pekerjaan pengguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_leads', 'first_chat_date')) {
            Schema::table('customer_leads', function (Blueprint $table) {
                $table->date('first_chat_date')->nullable()->after('phone');
            });
        }

        if (! Schema::hasColumn('payments', 'payment_method')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_method', 30)->nullable()->after('payment_type');
            });
        }

        foreach ([
            'address'   => fn (Blueprint $t) => $t->string('address', 255)->nullable()->after('description'),
            'latitude'  => fn (Blueprint $t) => $t->decimal('latitude', 10, 7)->nullable()->after('address'),
            'longitude' => fn (Blueprint $t) => $t->decimal('longitude', 10, 7)->nullable()->after('latitude'),
        ] as $kolom => $definisi) {
            if (! Schema::hasColumn('clusters', $kolom)) {
                Schema::table('clusters', fn (Blueprint $t) => $definisi($t));
            }
        }

        $this->perbaruiStatusLead();
        $this->perbaruiStatusBooking();
    }

    public function down(): void
    {
        if ($this->enumSudah('customer_leads', 'status', ['cold', 'warm', 'hot'])) {
            DB::statement("ALTER TABLE customer_leads MODIFY status ENUM('cold','warm','hot','booking') NOT NULL DEFAULT 'cold'");
        }

        foreach (['status_before', 'status_after'] as $kolom) {
            if ($this->enumSudah('follow_up_histories', $kolom, ['cold', 'warm', 'hot'])) {
                DB::statement("ALTER TABLE follow_up_histories MODIFY {$kolom} ENUM('cold','warm','hot','booking') NOT NULL");
            }
        }

        DB::statement('UPDATE bookings SET status = \'menunggu dp\' WHERE status = \'dibatalkan\'');

        if ($this->enumSudah('bookings', 'status', ['menunggu dp', 'dp lunas', 'cicilan', 'lunas', 'dibatalkan'])) {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('menunggu dp','dp lunas','cicilan','lunas') NOT NULL DEFAULT 'menunggu dp'");
        }

        foreach (['longitude', 'latitude', 'address'] as $kolom) {
            if (Schema::hasColumn('clusters', $kolom)) {
                Schema::table('clusters', fn (Blueprint $t) => $t->dropColumn($kolom));
            }
        }

        if (Schema::hasColumn('payments', 'payment_method')) {
            Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('payment_method'));
        }

        foreach (['cancelled_at', 'cancellation_reason'] as $kolom) {
            if (Schema::hasColumn('bookings', $kolom)) {
                Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn($kolom));
            }
        }

        if (Schema::hasColumn('customer_leads', 'first_chat_date')) {
            Schema::table('customer_leads', fn (Blueprint $t) => $t->dropColumn('first_chat_date'));
        }
    }

    /**
     * Menyempitkan status prospek menjadi cold/warm/hot. Pemindahan nilai lama
     * harus terjadi sebelum MODIFY supaya MySQL tidak membuang baris.
     */
    private function perbaruiStatusLead(): void
    {
        $kolom = $this->enumSudah('customer_leads', 'status', ['cold', 'warm', 'hot']);

        if (! $kolom) {
            DB::statement("UPDATE customer_leads SET status = 'hot' WHERE status = 'booking'");
            DB::statement("ALTER TABLE customer_leads MODIFY status ENUM('cold','warm','hot') NOT NULL DEFAULT 'cold'");
        }

        foreach (['status_before', 'status_after'] as $nama) {
            if (Schema::hasColumn('follow_up_histories', $nama) && ! $this->enumSudah('follow_up_histories', $nama, ['cold', 'warm', 'hot'])) {
                DB::statement("UPDATE follow_up_histories SET {$nama} = 'hot' WHERE {$nama} = 'booking'");
                DB::statement("ALTER TABLE follow_up_histories MODIFY {$nama} ENUM('cold','warm','hot') NOT NULL");
            }
        }
    }

    private function perbaruiStatusBooking(): void
    {
        foreach (['cancelled_at', 'cancellation_reason'] as $kolom) {
            if (! Schema::hasColumn('bookings', $kolom)) {
                Schema::table('bookings', function (Blueprint $table) use ($kolom) {
                    if ($kolom === 'cancelled_at') {
                        $table->timestamp('cancelled_at')->nullable()->after('delivery_status');
                    } else {
                        $table->string('cancellation_reason', 255)->nullable()->after('cancelled_at');
                    }
                });
            }
        }

        if (! $this->enumSudah('bookings', 'status', ['menunggu dp', 'dp lunas', 'cicilan', 'lunas', 'dibatalkan'])) {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('menunggu dp','dp lunas','cicilan','lunas','dibatalkan') NOT NULL DEFAULT 'menunggu dp'");
        }
    }

    /**
     * True bila kolom enum sudah tepat berisi daftar nilai yang diharapkan.
     */
    private function enumSudah(string $tabel, string $kolom, array $nilai): bool
    {
        $baris = DB::select(
            'select column_type as tipe from information_schema.columns
             where table_schema = database() and table_name = ? and column_name = ?',
            [$tabel, $kolom]
        )[0] ?? null;

        if (! $baris) {
            return false;
        }

        $aktual = array_map('trim', explode(',', str_replace(["enum('", "')"], '', $baris->tipe)));
        $diharapkan = array_map(fn (string $n) => str_replace("'", "''", $n), $nilai);

        return $aktual === $diharapkan;
    }
};
