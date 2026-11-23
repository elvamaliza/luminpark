<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Notification;
use App\Models\User;

/**
 * Pemberitahuan internal untuk alur kerja sales ↔ Super Admin (booking baru,
 * pembayaran menunggu/lewat verifikasi, pembatalan konsumen).
 *
 * Monitoring Manajemen (admin_sales) sengaja tidak ikut menerima siaran ini:
 * pemberitahuan yang ditujukan ke sales atau ke super admin bukan ranahnya.
 * Admin Sales tetap menerima notifikasi yang memang diarahkan ke akunnya, lewat
 * Notification::createForUser().
 */
trait NotifiesOperationalUsers
{
    protected function notifyOperationalUsers(string $title, string $message, string $type, ?string $link): void
    {
        $recipients = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('status', User::STATUS_AKTIF)
            ->pluck('id');

        foreach ($recipients as $userId) {
            Notification::createForUser((int) $userId, $title, $message, $type, $link);
        }
    }
}
