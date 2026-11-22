<?php

namespace App\Http\Controllers;

use App\Services\BroadcastReadAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        protected BroadcastReadAnalyticsService $broadcastAnalytics
    ) {
    }

    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(20);

        foreach ($notifications as $notification) {
            $this->recordBroadcastNotificationRead($notification, $user);
        }

        return view('notifications.index', compact('notifications'));
    }

    public function markAllRead()
    {
        $user = Auth::user();
        $unread = $user->unreadNotifications;
        foreach ($unread as $notification) {
            $this->recordBroadcastNotificationRead($notification, $user);
        }
        $unread->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
            $this->recordBroadcastNotificationRead($notification, $user);
        }

        return response()->json(['success' => true]);
    }

    protected function recordBroadcastNotificationRead($notification, $user): void
    {
        $data = is_array($notification->data ?? null) ? $notification->data : [];
        if (($data['type'] ?? '') !== 'broadcast_announcement') {
            return;
        }

        $jobId = (int) ($data['broadcast_job_id'] ?? 0);
        if ($jobId <= 0) {
            return;
        }

        try {
            $this->broadcastAnalytics->recordRead(
                $jobId,
                (int) $user->id,
                $user->telegram_id ? (string) $user->telegram_id : null,
                'web_notif'
            );
        } catch (\Throwable $e) {
            // Never disrupt user notification UX if analytics write fails
        }
    }
}
