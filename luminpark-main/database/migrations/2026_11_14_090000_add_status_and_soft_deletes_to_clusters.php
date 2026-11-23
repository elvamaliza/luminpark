<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengelolaan cluster dilengkapi: cluster boleh diubah, diarsipkan, dan dihapus
 * tanpa menghilangkan barisnya (mengikuti pola soft delete modul lain). Indeks
 * unik clusters(project_id, name) diturunkan menjadi indeks biasa karena nama
 * cluster yang sudah diarsipkan masih tersimpan di tabel dan tidak boleh
 * menghalangi pembuatan cluster baru dengan nama sama. MySQL menolak menjatuhkan
 * indeks yang dipakai foreign key, jadi indeks kolom project_id dibuat lebih dulu.
 */
return new class extends Migration
{
    private const INDEKS_UNIK = 'clusters_project_id_name_unique';
    private const INDEKS_PROYEK = 'clusters_project_id_indexed';
    private const INDEKS_NAMA = 'clusters_project_name_indexed';

    public function up(): void
    {
        if (! Schema::hasColumn('clusters', 'status')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->string('status', 20)->default('aktif')->after('description');
            });
        }

        if (! Schema::hasColumn('clusters', 'deleted_at')) {
            Schema::table('clusters', fn (Blueprint $table) => $table->softDeletes());
        }

        if ($this->indeksAda(self::INDEKS_UNIK)) {
            if (! $this->indeksAda(self::INDEKS_PROYEK)) {
                Schema::table('clusters', fn (Blueprint $table) => $table->index('project_id', self::INDEKS_PROYEK));
            }

            Schema::table('clusters', fn (Blueprint $table) => $table->dropUnique(self::INDEKS_UNIK));
        }

        if (! $this->indeksAda(self::INDEKS_NAMA)) {
            Schema::table('clusters', fn (Blueprint $table) => $table->index(['project_id', 'name'], self::INDEKS_NAMA));
        }

        DB::statement("UPDATE clusters SET status = 'aktif' WHERE status IS NULL OR status = ''");
    }

    public function down(): void
    {
        if (! $this->indeksAda(self::INDEKS_UNIK)) {
            Schema::table('clusters', fn (Blueprint $table) => $table->unique(['project_id', 'name'], self::INDEKS_UNIK));
        }

        foreach ([self::INDEKS_NAMA, self::INDEKS_PROYEK] as $nama) {
            if ($this->indeksAda($nama)) {
                Schema::table('clusters', fn (Blueprint $table) => $table->dropIndex($nama));
            }
        }

        if (Schema::hasColumn('clusters', 'deleted_at')) {
            Schema::table('clusters', fn (Blueprint $table) => $table->dropSoftDeletes());
        }

        if (Schema::hasColumn('clusters', 'status')) {
            Schema::table('clusters', fn (Blueprint $table) => $table->dropColumn('status'));
        }
    }

    private function indeksAda(string $nama): bool
    {
        return (bool) DB::select('show indexes from clusters where Key_name = ?', [$nama]);
    }
};
