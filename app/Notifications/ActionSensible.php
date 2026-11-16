<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Alerte du super administrateur : action sensible (contrôle) ou événement de sécurité
 * (connexion hors horaires, échecs de connexion répétés…).
 */
class ActionSensible extends Notification
{
    use Queueable;

    /** @param string $categorie controle | securite */
    public function __construct(
        private readonly string $categorie,
        private readonly string $message,
        private readonly array $contexte = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'categorie' => $this->categorie,
            'message' => $this->message,
            ...$this->contexte,
        ];
    }
}
