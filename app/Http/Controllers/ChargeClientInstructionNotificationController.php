<?php

namespace App\Http\Controllers;

use App\Models\InteractionClient;
use App\Models\Vehicule;
use App\Notifications\AdminInstructionReceived;
use Illuminate\Http\Request;

class ChargeClientInstructionNotificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'charge_client' || ($request->user() instanceof \App\Models\User && $request->user()->estSuperAdmin()), 403);

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
                'url' => $this->urlSuivi($request->user()->id, $notification->data),
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

    /**
     * Page « Suivi des relances / interactions » du véhicule concerné : le dernier véhicule
     * sur lequel ce chargé client a interagi avec le client, sinon le premier véhicule du client.
     */
    private function urlSuivi(int $userId, array $data): ?string
    {
        $clientId = $data['client_id'] ?? null;
        if (! $clientId) {
            return null;
        }

        $vehiculeId = InteractionClient::query()
            ->where('client_id', $clientId)
            ->where('user_id', $userId)
            ->whereNotNull('vehicule_id')
            ->latest()
            ->value('vehicule_id')
            ?? Vehicule::query()->where('client_id', $clientId)->orderBy('id')->value('id');

        return $vehiculeId
            ? route('charge_client.vehicules.relances', $vehiculeId)
            : route('charge_client.clients.show', $clientId);
    }

    public function markRead(Request $request, string $notification)
    {
        abort_unless($request->user()->role === 'charge_client' || ($request->user() instanceof \App\Models\User && $request->user()->estSuperAdmin()), 403);

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
