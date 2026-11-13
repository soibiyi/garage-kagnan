<?php

namespace App\Notifications;

use App\Models\SecretaireTache;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Envoyée aux administrateurs quand une secrétaire termine une tâche. */
class SecretaireTacheTerminee extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SecretaireTache $tache,
        private readonly string $secretaireName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $dansLesDelais = $this->tache->terminee_at && $this->tache->echeance
            ? $this->tache->terminee_at->lte($this->tache->echeance)
            : true;

        return [
            'tache_id' => $this->tache->id,
            'secretaire_id' => $this->tache->secretaire_id,
            'titre' => $this->tache->titre,
            'dans_les_delais' => $dansLesDelais,
            'message' => "{$this->secretaireName} a terminé la tâche « {$this->tache->titre} »"
                . ($dansLesDelais ? '.' : ' (après le délai).'),
        ];
    }
}
