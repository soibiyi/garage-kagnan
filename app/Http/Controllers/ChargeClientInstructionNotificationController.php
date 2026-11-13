<?php

namespace App\Http\Controllers;

use App\Notifications\AdminInstructionReceived;
use Illuminate\Http\Request;

class ChargeClientInstructionNotificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'charge_client', 403);

        $dateExpiration = now()->subHours(72);

        $request->user()
            ->notifications()
            ->where('type', AdminInstructionReceived::class)
            ->whereNotNull('read_at')
            ->where('read_at', '<=', $dateExpiration)
            ->delete();

        $notifications = $request->user()
            ->notifications()
            ->where('type', AdminInstructionReceived::class)
            ->where(fn ($query) => $query
                ->whereNull('read_at')
                ->orWhere('read_at', '>', $dateExpiration))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'created_at' => $notification->created_at,
                'read_at' => $notification->read_at,
            ]);

        $unreadCount = $request->user()
            ->unreadNotifications()
            ->where('type', AdminInstructionReceived::class)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        abort_unless($request->user()->role === 'charge_client', 403);

        $record = $request->user()
            ->notifications()
            ->where('type', AdminInstructionReceived::class)
            ->whereKey($notification)
            ->firstOrFail();
        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return response()->noContent();
    }
}
