<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un ou plusieurs clients viennent d'être attribués au chargé client par l'administrateur.
 * Une seule notification par attribution (même groupée), affichée dans la bulle des instructions.
 */
class ClientsAttribues extends Notification
{
    use Queueable;

    /** Nombre de noms de clients conservés dans la notification (le reste est résumé). */
    private const NOMS_MAX = 5;

    /**
     * @param  array<int, array{id:int, nom:string}>  $clients
     */
    public function __construct(
        private readonly array $clients,
        private readonly string $adminName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $nb = count($this->clients);
        $premier = $this->clients[0] ?? null;

        return [
            'kind' => 'attribution',
            'nb' => $nb,
            'client_id' => $nb === 1 ? $premier['id'] : null,
            'client_name' => $nb === 1
                ? $premier['nom']
                : $nb . ' nouveaux clients',
            'client_names' => array_column(array_slice($this->clients, 0, self::NOMS_MAX), 'nom'),
            'admin_name' => $this->adminName,
            'message' => $nb === 1
                ? "{$this->adminName} vous a attribué un nouveau client."
                : "{$this->adminName} vous a attribué {$nb} nouveaux clients.",
        ];
    }
}
