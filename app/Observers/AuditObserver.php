<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Menulis jejak audit ke tabel activity_logs sesuai rancangan ERD.
 * Dipasang pada model transaksi utama lewat AppServiceProvider sehingga aksi
 * create/update/delete dari layar apa pun tercatat tanpa menyebut auditor di kode.
 */
class AuditObserver
{
    /** [kelas model => [label Indonesia, kolom identitas yang dipakai dalam keterangan]] */
    private const MODEL = [
        \App\Models\Booking::class => ['pemesanan', 'booking_code'],
        \App\Models\Cluster::class => ['proyek', 'name'],
        \App\Models\CustomerLead::class => ['calon konsumen', 'name'],
        \App\Models\Payment::class => ['pembayaran', 'id'],
        \App\Models\PaymentScheme::class => ['skema pembayaran', 'scheme_name'],
        \App\Models\Project::class => ['perumahan', 'name'],
        \App\Models\SalesProfile::class => ['data sales', 'sales_code'],
        \App\Models\Unit::class => ['unit', 'unit_code'],
        \App\Models\User::class => ['akun', 'email'],
    ];

    private const KEJADIAN = [
        'created' => 'Menambahkan',
        'updated' => 'Memperbarui',
        'deleted' => 'Menghapus',
        'restored' => 'Mengembalikan',
    ];

    public function created(Model $model): void
    {
        $this->catat($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->catat($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->catat($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->catat($model, 'restored');
    }

    private function catat(Model $model, string $kejadian): void
    {
        $userId = Auth::id();

        // Baris hasil seeder, migrasi, dan pemanggilan konsol tidak punya penulis,
        // sedangkan activity_logs mewajibkan user_id.
        if (! $userId || ! isset(self::MODEL[$model::class]) || app()->runningInConsole()) {
            return;
        }

        [$label, $identity] = self::MODEL[$model::class];
        $identitas = $model->getAttribute($identity) ?: ('#' . $model->getKey());
        $kata = self::KEJADIAN[$kejadian] ?? ucfirst($kejadian);

        $keterangan = $label . ' ' . $identitas;

        if ($kejadian === 'updated') {
            $terubah = array_values(array_filter(array_map(
                fn (string $key) => $this->label($key),
                array_keys($model->getChanges())
            )));

            if ($terubah) {
                $keterangan .= ' — kolom berubah: ' . implode(', ', $terubah);
            }
        }

        ActivityLog::create([
            'user_id' => (int) $userId,
            'action' => $kejadian . '_' . $label,
            'description' => $kata . ' ' . $keterangan,
            'ip_address' => request()?->ip(),
        ]);
    }

    /** Nama kolom versi manusia; kolom teknis dilewati agar keterangan tetap terbaca. */
    private function label(string $key): ?string
    {
        if (in_array($key, ['created_at', 'updated_at', 'deleted_at'], true)) {
            return null;
        }

        $terjemahan = __('validation.attributes.' . $key, [], app()->getLocale());

        return $terjemahan === 'validation.attributes.' . $key
            ? str_replace('_', ' ', $key)
            : $terjemahan;
    }
}
