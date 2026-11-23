<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Cluster;
use App\Models\CustomerLead;

/**
 * Pengelompokan baris laporan dan dasbor per proyek (revisi 32).
 *
 * Permintaan pengguna: angka yang ditampilkan adalah milik masing-masing proyek,
 * bukan gabungan seluruh proyek. Karena itu rangkuman disusun dari baris yang
 * sudah dimuat sehingga selalu cocok dengan tabel detail di bawahnya.
 */
trait PerProjectSummary
{
    /** Peta id proyek => nama, dibaca sekali per permintaan. */
    private $namaProyekCache = null;

    private function namaProyek(): array
    {
        return $this->namaProyekCache ??= Cluster::withTrashed()->pluck('name', 'id')->all();
    }

    /**
     * Bagi baris menjadi satu kelompok per proyek lalu rangkum tiap kelompok.
     *
     * $proyekId mengembalikan cluster_id baris atau null bila barisnya belum
     * punya proyek; kelompok tanpa proyek tetap tampil paling belakang supaya
     * datanya tidak hilang dari laporan.
     */
    private function rangkumPerProyek(iterable $rows, callable $proyekId, callable $metrik): array
    {
        $kelompok = [];

        foreach ($rows as $row) {
            $id = $proyekId($row);
            $kelompok[$id === null ? 'tanpa' : (string) $id][] = $row;
        }

        if (!$kelompok) {
            return [];
        }

        $nama = $this->namaProyek();
        $hasil = [];

        foreach ($kelompok as $key => $baris) {
            $id = $key === 'tanpa' ? null : (int) $key;

            $hasil[] = array_merge([
                'cluster_id' => $id,
                'name'       => $this->labelProyek($id, $nama),
            ], $metrik($baris));
        }

        usort($hasil, fn (array $a, array $b) => [
            $a['cluster_id'] === null ? 1 : 0,
            mb_strtolower($a['name']),
        ] <=> [
            $b['cluster_id'] === null ? 1 : 0,
            mb_strtolower($b['name']),
        ]);

        return $hasil;
    }

    /**
     * Pasang kunci "project" pada tiap baris agar tabel detail laporan dapat
     * menunjukkan proyek tempat baris itu berada, sama seperti rincian di atasnya.
     */
    private function tandaiProyek($rows, callable $proyekId)
    {
        $nama = $this->namaProyek();

        $rows->each(function ($row) use ($nama, $proyekId) {
            $id = $proyekId($row);
            $row->setAttribute('project', $this->labelProyek($id === null ? null : (int) $id, $nama));
        });

        return $rows;
    }

    private function labelProyek(?int $clusterId, array $nama): string
    {
        if ($clusterId === null) {
            return '(belum ada proyek)';
        }

        return $nama[$clusterId] ?? '(proyek arsip)';
    }

    /**
     * Proyek tempat seorang lead dihitung: unit pemesanan lebih dulu, baru unit
     * yang diminati. Urutan ini mengikuti cara penapis proyek pada laporan
     * konsumen menerima lead, sehingga baris laporan dan rinciannya tidak saling
     * bertentangan.
     */
    private function proyekDariLead(CustomerLead $lead): ?int
    {
        foreach ($lead->bookings as $booking) {
            if ($id = optional($booking->unit)->cluster_id) {
                return (int) $id;
            }
        }

        return optional($lead->unit)->cluster_id ? (int) $lead->unit->cluster_id : null;
    }
}
