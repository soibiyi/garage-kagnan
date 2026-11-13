<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminInstructionReceived extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $consigneId,
        private readonly int $clientId,
        private readonly string $clientName,
        private readonly string $consigne,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'consigne_id' => $this->consigneId,
            'client_id' => $this->clientId,
            'client_name' => $this->clientName,
            'consigne' => $this->consigne,
            'message' => "Vous venez de recevoir une instruction de la part de l'administrateur.",
        ];
    }
}
