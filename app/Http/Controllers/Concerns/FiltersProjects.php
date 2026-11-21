<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CustomerLead;

/**
 * Pembatas query statistik dan laporan ke satu proyek.
 *
 * Nama tabel induknya di basis data tetap clusters; penyebutan "proyek" dipakai
 * di seluruh antarmuka (revisi 4) sehingga parameter API ikut memakai cluster_id.
 */
trait FiltersProjects
{
    /**
     * Id proyek dari permintaan, atau null bila permintaan tidak menyaring.
     */
    private function clusterIdDari($request): ?int
    {
        return $request->filled('cluster_id') ? (int) $request->cluster_id : null;
    }

    /** Query pada tabel yang memiliki kolom cluster_id langsung (units). */
    private function unitProyek($query, ?int $clusterId)
    {
        return $clusterId ? $query->where('cluster_id', $clusterId) : $query;
    }

    /** Query pada tabel yang menjangkau units lewat relasi. */
    private function padaProyek($query, ?int $clusterId, string $lewat = 'booking.unit')
    {
        if (!$clusterId) {
            return $query;
        }

        $relasi = $lewat === 'unit' ? 'unit' : $lewat;

        return $query->whereHas($relasi, fn ($u) => $u->where('cluster_id', $clusterId));
    }

    /**
     * Lead dikaitkan dengan proyek melalui unit yang diminatinya atau unit dari
     * pemesanan yang pernah ia buat.
     */
    private function leadProyek($query, ?int $clusterId)
    {
        if (!$clusterId) {
            return $query;
        }

        return $query->where(function ($q) use ($clusterId) {
            $q->whereHas('unit', fn ($u) => $u->where('cluster_id', $clusterId))
                ->orWhereHas('bookings.unit', fn ($u) => $u->where('cluster_id', $clusterId));
        });
    }

    /** Kategori konsumen/prospek per baris, tanpa membawa isi relasi bookings. */
    private function denganKategoriLead($rows)
    {
        $rows->each(function (CustomerLead $lead) {
            $lead->setAttribute('category', $lead->category());
            $lead->makeHidden('bookings');
        });

        return $rows;
    }
}
