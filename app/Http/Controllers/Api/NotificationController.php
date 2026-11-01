<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::where('user_id', $request->user()->id)->latest();

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        return response()->json([
            'success' => true,
            'data'    => $query->limit(50)->get(),
        ], 200);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => ['unread_count' => Notification::where('user_id', $request->user()->id)->unread()->count()],
        ], 200);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)->find($id);

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notifikasi tidak ditemukan'], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => 'Notifikasi ditandai sudah dibaca', 'data' => $notification], 200);
    }

    public function markAllAsRead(Request $request)
    {
        $updated = Notification::where('user_id', $request->user()->id)->unread()->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => 'Semua notifikasi ditandai sudah dibaca.', 'data' => ['updated' => $updated]], 200);
    }
}
