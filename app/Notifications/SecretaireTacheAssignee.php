<?php

namespace App\Notifications;

use App\Models\SecretaireTache;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Envoyée à la secrétaire quand l'administrateur lui confie (ou modifie) une tâche. */
class SecretaireTacheAssignee extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SecretaireTache $tache,
        private readonly bool $modification = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tache_id' => $this->tache->id,
            'titre' => $this->tache->titre,
            'echeance' => $this->tache->echeance?->toIso8601String(),
            'priorite' => $this->tache->priorite,
            'message' => $this->modification
                ? "L'administrateur a modifié la tâche « {$this->tache->titre} »."
                : "Nouvelle tâche de l'administrateur : « {$this->tache->titre} ».",
        ];
    }
}
