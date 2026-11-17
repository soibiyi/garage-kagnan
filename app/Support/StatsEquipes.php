<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\ClientPortalRequest;
use App\Models\Devis;
use App\Models\InteractionClient;
use App\Models\Intervention;
use App\Models\Paiement;
use App\Models\SecretaireTache;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Performance des équipes sur une période, par métier. */
class StatsEquipes
{
    /** Délai sous lequel un retour du même véhicule chez le même mécanicien compte comme une reprise. */
    public const JOURS_REPRISE = 30;

    public static function calculer(Periode $periode, ?string $siege = null): array
    {
        [$debut, $fin] = $periode->bornes();

        $users = User::query()
            ->where('is_superadmin', false)
            ->when($siege, fn ($q) => $q->where('siege', $siege))
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'siege', 'suspendu_at']);

        $parRole = fn (string ...$roles) => $users->filter(fn ($u) => in_array($u->role, $roles, true))->values();

        // Actions faites dans l'application (hors simples visites de pages)
        $actions = ActivityLog::query()
            ->whereBetween('created_at', [$debut, $fin])
            ->where('method', '!=', 'GET')
            ->selectRaw('user_id, COUNT(*) AS n')
            ->groupBy('user_id')
            ->pluck('n', 'user_id');

        $base = fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'siege' => $u->siege,
            'suspendu' => (bool) $u->suspendu_at,
            'actions' => (int) ($actions[$u->id] ?? 0),
        ];

        return [
            'mecaniciens' => self::mecaniciens($parRole('mecanicien'), $base, $debut, $fin),
            'receptionnistes' => self::receptionnistes($parRole('receptionniste'), $base, $debut, $fin),
            'administratifs' => self::administratifs($parRole('administratif', 'admin'), $base, $debut, $fin),
            'chauffeurs' => self::chauffeurs($parRole('chauffeur', 'depanneur', 'depaneur'), $base, $debut, $fin),
            'charges_clients' => self::chargesClients($parRole('charge_client'), $base, $debut, $fin),
            'secretaires' => self::secretaires($parRole('secretaire'), $base, $debut, $fin),
            'jours_reprise' => self::JOURS_REPRISE,
        ];
    }

    private static function mecaniciens(Collection $users, callable $base, $debut, $fin): array
    {
        if ($users->isEmpty()) {
            return [];
        }
        $ids = $users->pluck('id');
        $dateRef = DB::raw('COALESCE(interventions.date_reception, interventions.created_at)');

        $stats = Intervention::query()
            ->whereIn('mecanicien_id', $ids)
            ->whereBetween($dateRef, [$debut, $fin])
            ->selectRaw("mecanicien_id, COUNT(*) AS total, SUM(statut = 'livre') AS livrees")
            ->groupBy('mecanicien_id')->get()->keyBy('mecanicien_id');

        $enCours = Intervention::query()->whereIn('mecanicien_id', $ids)->where('statut', '!=', 'livre')
            ->selectRaw('mecanicien_id, COUNT(*) AS n')->groupBy('mecanicien_id')->pluck('n', 'mecanicien_id');

        // Délai réception → facture des dossiers facturés sur la période
        $delais = Intervention::query()
            ->join('factures', 'factures.intervention_id', '=', 'interventions.id')
            ->whereIn('interventions.mecanicien_id', $ids)
            ->whereBetween('factures.created_at', [$debut, $fin])
            ->selectRaw('interventions.mecanicien_id AS mid, AVG(TIMESTAMPDIFF(HOUR, COALESCE(interventions.date_reception, interventions.created_at), factures.created_at)) AS h')
            ->groupBy('interventions.mecanicien_id')->pluck('h', 'mid');

        // Reprises : le même véhicule revient chez le même mécanicien dans les N jours
        $reprises = DB::table('interventions AS i')
            ->join('interventions AS p', function ($j) {
                $j->on('p.vehicule_id', '=', 'i.vehicule_id')
                    ->on('p.mecanicien_id', '=', 'i.mecanicien_id')
                    ->on('p.id', '<', 'i.id');
            })
            ->whereIn('i.mecanicien_id', $ids)
            ->whereBetween(DB::raw('COALESCE(i.date_reception, i.created_at)'), [$debut, $fin])
            ->whereRaw('TIMESTAMPDIFF(DAY, COALESCE(p.date_reception, p.created_at), COALESCE(i.date_reception, i.created_at)) BETWEEN 0 AND ?', [self::JOURS_REPRISE])
            ->selectRaw('i.mecanicien_id AS mid, COUNT(DISTINCT i.id) AS n')
            ->groupBy('i.mecanicien_id')->pluck('n', 'mid');

        return $users->map(function (User $u) use ($base, $stats, $enCours, $delais, $reprises) {
            $s = $stats->get($u->id);
            $total = (int) ($s->total ?? 0);
            $nbReprises = (int) ($reprises[$u->id] ?? 0);

            return $base($u) + [
                'dossiers' => $total,
                'livres' => (int) ($s->livrees ?? 0),
                'en_cours' => (int) ($enCours[$u->id] ?? 0),
                'delai_moyen_jours' => isset($delais[$u->id]) ? round($delais[$u->id] / 24, 1) : null,
                'reprises' => $nbReprises,
                'taux_reprise' => $total ? round($nbReprises / $total * 100) : null,
            ];
        })->all();
    }

    private static function receptionnistes(Collection $users, callable $base, $debut, $fin): array
    {
        $recus = Intervention::query()
            ->whereIn('receptionniste_id', $users->pluck('id'))
            ->whereBetween(DB::raw('COALESCE(date_reception, created_at)'), [$debut, $fin])
            ->selectRaw('receptionniste_id, COUNT(*) AS n')
            ->groupBy('receptionniste_id')->pluck('n', 'receptionniste_id');

        return $users->map(fn (User $u) => $base($u) + ['vehicules_recus' => (int) ($recus[$u->id] ?? 0)])->all();
    }

    private static function administratifs(Collection $users, callable $base, $debut, $fin): array
    {
        $ids = $users->pluck('id');

        $devis = Devis::query()->whereIn('createur_id', $ids)->whereBetween('created_at', [$debut, $fin])
            ->selectRaw("createur_id, COUNT(*) AS n, SUM(statut = 'accepte') AS acceptes, SUM(montant_total_ttc) AS montant")
            ->groupBy('createur_id')->get()->keyBy('createur_id');

        $paiements = Paiement::query()->whereIn('enregistre_par', $ids)->whereBetween('date_paiement', [$debut, $fin])
            ->selectRaw('enregistre_par, COUNT(*) AS n, SUM(montant) AS montant')
            ->groupBy('enregistre_par')->get()->keyBy('enregistre_par');

        return $users
            ->map(function (User $u) use ($base, $devis, $paiements) {
                $d = $devis->get($u->id);
                $p = $paiements->get($u->id);

                return $base($u) + [
                    'role' => $u->role,
                    'devis' => (int) ($d->n ?? 0),
                    'devis_acceptes' => (int) ($d->acceptes ?? 0),
                    'montant_devis' => (float) ($d->montant ?? 0),
                    'paiements' => (int) ($p->n ?? 0),
                    'montant_encaisse' => (float) ($p->montant ?? 0),
                ];
            })
            // Les administrateurs n'apparaissent que s'ils ont fait du devis / de l'encaissement
            ->filter(fn ($l) => $l['role'] !== 'admin' || $l['devis'] || $l['paiements'])
            ->values()->all();
    }

    private static function chauffeurs(Collection $users, callable $base, $debut, $fin): array
    {
        $ids = $users->pluck('id');

        $missions = ClientPortalRequest::query()
            ->whereIn('chauffeur_id', $ids)
            ->whereBetween('mission_completed_at', [$debut, $fin])
            ->selectRaw('chauffeur_id, COUNT(*) AS n, SUM(distance_parcourue_km) AS km,
                AVG(TIMESTAMPDIFF(MINUTE, COALESCE(mission_started_at, chauffeur_accepted_at), mission_completed_at)) AS duree,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, chauffeur_accepted_at)) AS reaction')
            ->groupBy('chauffeur_id')->get()->keyBy('chauffeur_id');

        $refus = DB::table('client_portal_request_rejections')
            ->whereIn('chauffeur_id', $ids)->whereBetween('created_at', [$debut, $fin])
            ->selectRaw('chauffeur_id, COUNT(*) AS n')->groupBy('chauffeur_id')->pluck('n', 'chauffeur_id');

        return $users->map(function (User $u) use ($base, $missions, $refus) {
            $m = $missions->get($u->id);

            return $base($u) + [
                'missions' => (int) ($m->n ?? 0),
                'km' => round((float) ($m->km ?? 0), 1),
                'duree_moyenne_min' => $m && $m->duree !== null ? (int) round($m->duree) : null,
                'reaction_moyenne_min' => $m && $m->reaction !== null ? (int) round($m->reaction) : null,
                'refus' => (int) ($refus[$u->id] ?? 0),
            ];
        })->all();
    }

    private static function chargesClients(Collection $users, callable $base, $debut, $fin): array
    {
        $ids = $users->pluck('id');

        $interactions = InteractionClient::query()->contacts()->whereIn('user_id', $ids)->whereBetween('created_at', [$debut, $fin])
            ->selectRaw('user_id, COUNT(*) AS n, COUNT(DISTINCT client_id) AS clients')
            ->groupBy('user_id')->get()->keyBy('user_id');

        // Relances prévues sur la période, arrivées à échéance
        $relances = InteractionClient::query()->whereIn('user_id', $ids)
            ->whereBetween('date_relance_prevue', [$debut->toDateString(), min($fin, now())->toDateString()])
            ->selectRaw('user_id, COUNT(*) AS n')->groupBy('user_id')->pluck('n', 'user_id');

        // Conversion : client contacté sur la période qui a ensuite déposé un véhicule au garage
        $conversions = DB::table('interaction_clients AS ic')
            ->join('vehicules AS v', 'v.client_id', '=', 'ic.client_id')
            ->join('interventions AS i', 'i.vehicule_id', '=', 'v.id')
            ->whereIn('ic.user_id', $ids)
            ->where('ic.type', '!=', InteractionClient::TYPE_ATTRIBUTION)
            ->whereBetween('ic.created_at', [$debut, $fin])
            ->whereColumn(DB::raw('COALESCE(i.date_reception, i.created_at)'), '>=', 'ic.created_at')
            ->selectRaw('ic.user_id AS uid, COUNT(DISTINCT ic.client_id) AS n')
            ->groupBy('ic.user_id')->pluck('n', 'uid');

        return $users->map(function (User $u) use ($base, $interactions, $relances, $conversions) {
            $i = $interactions->get($u->id);
            $clients = (int) ($i->clients ?? 0);
            $conv = (int) ($conversions[$u->id] ?? 0);

            return $base($u) + [
                'interactions' => (int) ($i->n ?? 0),
                'clients_contactes' => $clients,
                'relances_echues' => (int) ($relances[$u->id] ?? 0),
                'conversions' => $conv,
                'taux_conversion' => $clients ? round($conv / $clients * 100) : null,
            ];
        })->all();
    }

    private static function secretaires(Collection $users, callable $base, $debut, $fin): array
    {
        $ids = $users->pluck('id');

        $taches = SecretaireTache::query()->whereIn('secretaire_id', $ids)->whereBetween('created_at', [$debut, $fin])
            ->selectRaw("secretaire_id, COUNT(*) AS n, SUM(statut = 'terminee') AS terminees,
                SUM(statut = 'terminee' AND terminee_at > echeance) AS terminees_en_retard")
            ->groupBy('secretaire_id')->get()->keyBy('secretaire_id');

        // En retard à ce jour (toutes périodes)
        $enRetard = SecretaireTache::query()->whereIn('secretaire_id', $ids)
            ->where('statut', '!=', 'terminee')->where('echeance', '<', now())
            ->selectRaw('secretaire_id, COUNT(*) AS n')->groupBy('secretaire_id')->pluck('n', 'secretaire_id');

        return $users->map(function (User $u) use ($base, $taches, $enRetard) {
            $t = $taches->get($u->id);
            $terminees = (int) ($t->terminees ?? 0);

            return $base($u) + [
                'taches' => (int) ($t->n ?? 0),
                'terminees' => $terminees,
                'terminees_en_retard' => (int) ($t->terminees_en_retard ?? 0),
                'en_retard' => (int) ($enRetard[$u->id] ?? 0),
                'taux_a_l_heure' => $terminees ? round(($terminees - (int) $t->terminees_en_retard) / $terminees * 100) : null,
            ];
        })->all();
    }
}
