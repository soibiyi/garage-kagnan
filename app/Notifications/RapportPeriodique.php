<?php

namespace App\Notifications;

use App\Models\Rapport;
use App\Support\Parametres;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Rapport quotidien / hebdomadaire envoyé aux super administrateurs (cloche + e-mail). */
class RapportPeriodique extends Notification
{
    use Queueable;

    public function __construct(private readonly Rapport $rapport) {}

    public function via(object $notifiable): array
    {
        return Parametres::get('rapport_email') ? ['database', 'mail'] : ['database'];
    }

    private function titre(): string
    {
        return $this->rapport->periode === 'semaine'
            ? 'Rapport de la semaine (' . $this->rapport->debut->format('d/m') . ' – ' . $this->rapport->fin->format('d/m') . ')'
            : 'Rapport du ' . $this->rapport->debut->format('d/m/Y');
    }

    public function toArray(object $notifiable): array
    {
        $d = $this->rapport->donnees;

        return [
            'categorie' => 'rapport',
            'rapport_id' => $this->rapport->id,
            'message' => $this->titre() . ' : ' . self::fcfa($d['argent']['ca']) . ' facturés, '
                . $d['actions_sensibles']['total'] . ' action(s) sensible(s), '
                . $d['erreurs']['nouvelles'] . ' nouvelle(s) erreur(s).',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->rapport->donnees;

        $mail = (new MailMessage)
            ->subject('Garage Kagnan — ' . $this->titre())
            ->greeting($this->titre())
            ->line('**Chiffre d\'affaires facturé :** ' . self::fcfa($d['argent']['ca']) . ' (' . $d['argent']['nb_factures'] . ' facture(s))')
            ->line('**Encaissé :** ' . self::fcfa($d['argent']['encaisse']))
            ->line('**Impayés à ce jour :** ' . self::fcfa($d['argent']['impayes']))
            ->line('**Véhicules reçus :** ' . $d['activite']['vehicules_recus'] . ' · en cours : ' . $d['activite']['en_cours'])
            ->line('**Nouvelles erreurs :** ' . $d['erreurs']['nouvelles'] . ' · ouvertes : ' . $d['erreurs']['ouvertes'])
            ->line('**Actions sensibles :** ' . $d['actions_sensibles']['total'])
            ->line('**Connexions :** ' . $d['securite']['connexions'] . ' · échecs : ' . $d['securite']['echecs'] . ' · hors horaires : ' . $d['securite']['hors_horaires'])
            ->line('**Pièces en stock bas :** ' . count($d['stocks_bas']['liste']));

        return $mail->action('Voir le rapport complet', route('superadmin.rapports.show', $this->rapport));
    }

    private static function fcfa(float $n): string
    {
        return number_format($n, 0, ',', ' ') . ' FCFA';
    }
}
