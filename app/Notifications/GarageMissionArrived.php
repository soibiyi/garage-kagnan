<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GarageMissionArrived extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $missionId,
        private readonly string $siege,
        private readonly string $arrivedAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_arrivee_garage',
            'mission_id' => $this->missionId,
            'siege' => $this->siege,
            'message' => 'Un véhicule est arrivé au Garage Kagnan.',
            'arrived_at' => $this->arrivedAt,
        ];
    }
}