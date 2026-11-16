<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Connexion;
use App\Models\ErrorLog;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\Paiement;
use App\Models\Rapport;
use App\Models\StockSiege;
use App\Models\User;
use App\Notifications\RapportPeriodique;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Rapport quotidien / hebdomadaire : argent, activité, erreurs, actions sensibles, sécurité, stocks bas. */
class RapportBuilder
{
    /**
     * @param string $periode jour | semaine
     * @param bool   $envoyer notifier les super administrateurs (cloche + e-mail si activé)
     */
    public static function generer(string $periode = 'jour', ?int $genereParId = null, bool $envoyer = true): Rapport
    {
        $now = CarbonImmutable::now();
        // Rapport quotidien = la journée en cours ; hebdomadaire = les 7 derniers jours
        [$debut, $fin] = $periode === 'semaine'
            ? [$now->subDays(6)->startOfDay(), $now]
            : [$now->startOfDay(), $now];

        $rapport = Rapport::create([
            'periode' => $periode,
            'debut' => $debut,
            'fin' => $fin,
            'donnees' => self::donnees($debut, $fin),
            'genere_par' => $genereParId,
        ]);

        if ($envoyer) {
            $superAdmins = User::query()->where('role', 'admin')->where('is_superadmin', true)->get();
            try {
                if ($superAdmins->isNotEmpty()) {
                    Notification::send($superAdmins, new RapportPeriodique($rapport));
                }
            } catch (\Throwable $e) {
                // E-mail mal configuré, etc. : le rapport reste consultable dans l'application
                report($e);
            }
        }

        return $rapport;
    }

    public static function donnees(CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $seuil = (int) Parametres::get('seuil_stock');
        $sieges = StatsSieges::calculer(new Periode('perso', $debut, $fin))['sieges'];

        $stocksBas = StockSiege::query()
            ->join('stocks', 'stocks.id', '=', 'stock_sieges.stock_id')
            ->where('stock_sieges.quantite_attribuee', '>', 0)
            ->whereRaw('stock_sieges.quantite_attribuee - stock_sieges.sortie <= ?', [$seuil])
            ->orderByRaw('stock_sieges.quantite_attribuee - stock_sieges.sortie')
            ->limit(15)
            ->get(['stocks.designation_piece', 'stocks.reference', 'stock_sieges.siege', DB::raw('GREATEST(stock_sieges.quantite_attribuee - stock_sieges.sortie, 0) AS reste')])
            ->map(fn ($s) => ['piece' => $s->designation_piece ?: '—', 'reference' => $s->reference, 'siege' => $s->siege, 'reste' => (int) $s->reste])
            ->all();

        $actionsSensibles = AuditLog::query()
            ->whereNotNull('alerte')
            ->whereBetween('created_at', [$debut, $fin])
            ->with('user:id,name')
            ->latest('id')
            ->get();

        return [
            'argent' => [
                'ca' => (float) Facture::whereBetween('created_at', [$debut, $fin])->sum('montant_total'),
                'nb_factures' => Facture::whereBetween('created_at', [$debut, $fin])->count(),
                'encaisse' => (float) Paiement::whereBetween('date_paiement', [$debut, $fin])->sum('montant'),
                'impayes' => (float) Facture::whereColumn('montant_paye', '<', 'montant_total')->sum(DB::raw('montant_total - montant_paye')),
            ],
            'activite' => [
                'vehicules_recus' => Intervention::whereBetween(DB::raw('COALESCE(date_reception, created_at)'), [$debut, $fin])->count(),
                'en_cours' => Intervention::where('statut', '!=', 'livre')->count(),
            ],
            'sieges' => array_map(fn ($s) => [
                'code' => $s['code'],
                'ca' => $s['ca'],
                'encaisse' => $s['encaisse'],
                'interventions' => $s['interventions'],
                'ruptures' => $s['ruptures'],
            ], $sieges),
            'erreurs' => [
                'nouvelles' => ErrorLog::whereBetween('premiere_fois', [$debut, $fin])->count(),
                'ouvertes' => ErrorLog::whereNull('resolue_at')->count(),
                'plus_frequentes' => ErrorLog::whereNull('resolue_at')->orderByDesc('occurrences')->limit(5)
                    ->get(['type', 'message', 'occurrences'])
                    ->map(fn ($e) => ['type' => class_basename($e->type), 'message' => mb_strimwidth($e->message, 0, 120, '…'), 'occurrences' => $e->occurrences])->all(),
            ],
            'actions_sensibles' => [
                'total' => $actionsSensibles->count(),
                'liste' => $actionsSensibles->take(15)->map(fn (AuditLog $a) => [
                    'alerte' => $a->alerte,
                    'auteur' => $a->user?->name ?? 'Système',
                    'siege' => $a->siege,
                    'date' => $a->created_at?->toIso8601String(),
                ])->values()->all(),
            ],
            'securite' => [
                'connexions' => Connexion::where('reussie', true)->whereBetween('created_at', [$debut, $fin])->count(),
                'echecs' => Connexion::where('reussie', false)->whereBetween('created_at', [$debut, $fin])->count(),
                'hors_horaires' => Connexion::where('reussie', true)->where('hors_horaires', true)->whereBetween('created_at', [$debut, $fin])->count(),
                'comptes_suspendus' => User::whereNotNull('suspendu_at')->count(),
            ],
            'stocks_bas' => [
                'seuil' => $seuil,
                'liste' => $stocksBas,
            ],
        ];
    }
}
