<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\GarageMissionArrived;
use Illuminate\Http\Request;

class MissionNotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->unreadNotifications()
            ->where('type', GarageMissionArrived::class)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'created_at' => $notification->created_at,
            ]);

        return response()->json(['notifications' => $notifications]);
    }

    public function markRead(Request $request, string $notification)
    {
        $record = $request->user()
            ->unreadNotifications()
            ->where('type', GarageMissionArrived::class)
            ->whereKey($notification)
            ->firstOrFail();
        $record->markAsRead();

        return response()->noContent();
    }
}