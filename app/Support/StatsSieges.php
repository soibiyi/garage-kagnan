<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientPortalRequest;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\Paiement;
use App\Models\StockSiege;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Comparatif des sièges : activité, argent, stock, portail client, sur une période. */
class StatsSieges
{
    public static function calculer(Periode $periode): array
    {
        $sieges = config('sieges');
        [$debut, $fin] = $periode->bornes();
        $seuil = (int) Parametres::get('seuil_stock');

        // Interventions reçues sur la période, par siège
        $interventions = Intervention::query()
            ->whereBetween(DB::raw('COALESCE(date_reception, created_at)'), [$debut, $fin])
            ->selectRaw("siege, COUNT(*) AS total, SUM(statut = 'livre') AS livrees")
            ->groupBy('siege')->get()->keyBy('siege');

        $enCours = Intervention::query()->where('statut', '!=', 'livre')
            ->selectRaw('siege, COUNT(*) AS n')->groupBy('siege')->pluck('n', 'siege');

        // Factures émises sur la période (CA facturé, délai réception → facture)
        $factures = Facture::query()
            ->join('interventions', 'interventions.id', '=', 'factures.intervention_id')
            ->whereBetween('factures.created_at', [$debut, $fin])
            ->selectRaw('interventions.siege AS siege, COUNT(*) AS nb, SUM(factures.montant_total) AS ca,
                AVG(TIMESTAMPDIFF(HOUR, COALESCE(interventions.date_reception, interventions.created_at), factures.created_at)) AS delai_h')
            ->groupBy('interventions.siege')->get()->keyBy('siege');

        // Encaissements sur la période
        $encaisse = Paiement::query()
            ->join('factures', 'factures.id', '=', 'paiements.facture_id')
            ->join('interventions', 'interventions.id', '=', 'factures.intervention_id')
            ->whereBetween('paiements.date_paiement', [$debut, $fin])
            ->selectRaw('interventions.siege AS siege, SUM(paiements.montant) AS total')
            ->groupBy('interventions.siege')->pluck('total', 'siege');

        // Impayés à ce jour (toutes périodes confondues)
        $impayes = Facture::query()
            ->join('interventions', 'interventions.id', '=', 'factures.intervention_id')
            ->whereColumn('factures.montant_paye', '<', 'factures.montant_total')
            ->selectRaw('interventions.siege AS siege, COUNT(*) AS nb, SUM(factures.montant_total - factures.montant_paye) AS reste')
            ->groupBy('interventions.siege')->get()->keyBy('siege');

        // Devis de la période : taux d'acceptation
        $devis = Devis::query()
            ->join('interventions', 'interventions.id', '=', 'devis.intervention_id')
            ->whereBetween('devis.created_at', [$debut, $fin])
            ->selectRaw("interventions.siege AS siege, COUNT(*) AS total, SUM(devis.statut = 'accepte') AS acceptes")
            ->groupBy('interventions.siege')->get()->keyBy('siege');

        // Stock par siège (état actuel)
        $stock = StockSiege::query()
            ->join('stocks', 'stocks.id', '=', 'stock_sieges.stock_id')
            ->where('stock_sieges.quantite_attribuee', '>', 0)
            ->selectRaw('stock_sieges.siege AS siege,
                COUNT(*) AS references_,
                SUM(stock_sieges.quantite_attribuee - stock_sieges.sortie <= 0) AS ruptures,
                SUM(stock_sieges.quantite_attribuee - stock_sieges.sortie > 0 AND stock_sieges.quantite_attribuee - stock_sieges.sortie <= ?) AS sous_seuil,
                SUM(GREATEST(stock_sieges.quantite_attribuee - stock_sieges.sortie, 0) * COALESCE(stocks.prix_achat, 0)) AS valeur', [$seuil])
            ->groupBy('stock_sieges.siege')->get()->keyBy('siege');

        // Demandes du portail client
        $portail = ClientPortalRequest::query()
            ->whereBetween('created_at', [$debut, $fin])
            ->selectRaw("siege, COUNT(*) AS total, SUM(statut = 'terminee') AS terminees, SUM(statut = 'refuse') AS refusees,
                AVG(CASE WHEN traite_le IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, traite_le) END) AS delai_min")
            ->groupBy('siege')->get()->keyBy('siege');

        $nouveauxClients = Client::query()->whereBetween('created_at', [$debut, $fin])
            ->selectRaw('siege, COUNT(*) AS n')->groupBy('siege')->pluck('n', 'siege');

        $effectifs = User::query()->whereNotNull('siege')->where('is_superadmin', false)
            ->selectRaw('siege, COUNT(*) AS n')->groupBy('siege')->pluck('n', 'siege');

        $lignes = [];
        foreach ($sieges as $code => $nom) {
            $f = $factures->get($code);
            $d = $devis->get($code);
            $p = $portail->get($code);
            $s = $stock->get($code);
            $i = $interventions->get($code);

            $lignes[] = [
                'code' => $code,
                'nom' => $nom,
                'interventions' => (int) ($i->total ?? 0),
                'livrees' => (int) ($i->livrees ?? 0),
                'en_cours' => (int) ($enCours[$code] ?? 0),
                'delai_moyen_jours' => $f && $f->delai_h !== null ? round($f->delai_h / 24, 1) : null,
                'nb_factures' => (int) ($f->nb ?? 0),
                'ca' => (float) ($f->ca ?? 0),
                'panier_moyen' => $f && $f->nb ? round($f->ca / $f->nb) : 0,
                'encaisse' => (float) ($encaisse[$code] ?? 0),
                'impayes' => (float) ($impayes->get($code)->reste ?? 0),
                'nb_impayes' => (int) ($impayes->get($code)->nb ?? 0),
                'devis' => (int) ($d->total ?? 0),
                'devis_acceptes' => (int) ($d->acceptes ?? 0),
                'taux_acceptation' => $d && $d->total ? round($d->acceptes / $d->total * 100) : null,
                'stock_references' => (int) ($s->references_ ?? 0),
                'ruptures' => (int) ($s->ruptures ?? 0),
                'sous_seuil' => (int) ($s->sous_seuil ?? 0),
                'valeur_stock' => (float) ($s->valeur ?? 0),
                'demandes_portail' => (int) ($p->total ?? 0),
                'demandes_terminees' => (int) ($p->terminees ?? 0),
                'demandes_refusees' => (int) ($p->refusees ?? 0),
                'delai_traitement_min' => $p && $p->delai_min !== null ? (int) round($p->delai_min) : null,
                'nouveaux_clients' => (int) ($nouveauxClients[$code] ?? 0),
                'effectif' => (int) ($effectifs[$code] ?? 0),
            ];
        }

        return [
            'sieges' => $lignes,
            'evolution' => self::evolution(),
            'seuil_stock' => $seuil,
        ];
    }

    /** CA facturé des 6 derniers mois, par siège (pour le graphique). */
    private static function evolution(): array
    {
        $debut = CarbonImmutable::now()->subMonthsNoOverflow(5)->startOfMonth();

        $brut = Facture::query()
            ->join('interventions', 'interventions.id', '=', 'factures.intervention_id')
            ->where('factures.created_at', '>=', $debut)
            ->selectRaw("interventions.siege AS siege, DATE_FORMAT(factures.created_at, '%Y-%m') AS mois, SUM(factures.montant_total) AS ca")
            ->groupBy('siege', 'mois')->get();

        $mois = collect(range(0, 5))->map(fn ($i) => $debut->addMonthsNoOverflow($i));

        return [
            'mois' => $mois->map(fn ($m) => ucfirst($m->locale('fr')->isoFormat('MMM YY')))->all(),
            'series' => collect(config('sieges'))->map(fn ($nom, $code) => [
                'code' => $code,
                'valeurs' => $mois->map(fn ($m) => (float) ($brut->first(fn ($r) => $r->siege === $code && $r->mois === $m->format('Y-m'))->ca ?? 0))->all(),
            ])->values()->all(),
        ];
    }
}
