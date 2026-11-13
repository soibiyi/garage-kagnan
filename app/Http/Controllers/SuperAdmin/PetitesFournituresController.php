<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\Paiement;
use App\Support\PetiteFourniture;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Récapitulatif des petites fournitures (super administrateur).
 *
 * - Facturé : dossier dont le devis est accepté (même règle que la page Factures).
 *   Date retenue : date d'acceptation du devis (dernière mise à jour du devis).
 * - Soldé : facture entièrement payée. Date retenue : date du paiement qui l'a soldée.
 * - Montant : petite fourniture des lignes acceptées (3 % automatique ou montant saisi),
 *   calculée exactement comme sur la facture (App\Support\PetiteFourniture).
 */
class PetitesFournituresController extends Controller
{
    private const NB_MOIS = 12;
    private const NB_SEMAINES = 12;

    public function index(Request $request)
    {
        $request->validate(['siege' => ['nullable', Rule::in(array_keys(config('sieges')))]]);
        $siege = $request->input('siege');

        $dossiers = $this->dossiers($siege);

        $total = $dossiers->sum('petite_fourniture');
        $soldees = $dossiers->where('soldee', true);

        return Inertia::render('SuperAdmin/PetitesFournitures', [
            'siege' => $siege,
            'resume' => [
                'total' => $total,
                'soldees' => $soldees->sum('petite_fourniture'),
                'non_soldees' => $total - $soldees->sum('petite_fourniture'),
                'nb_factures' => $dossiers->count(),
                'nb_soldees' => $soldees->count(),
                'mois_en_cours' => $this->agreger($dossiers, CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth()),
                'semaine_en_cours' => $this->agreger($dossiers, CarbonImmutable::now()->startOfWeek(), CarbonImmutable::now()->endOfWeek()),
            ],
            'parMois' => $this->periodes($dossiers, 'mois'),
            'parSemaine' => $this->periodes($dossiers, 'semaine'),
            // Détail des dossiers (les plus récents) pour la liste filtrable côté page
            'dossiers' => $dossiers->sortByDesc('date_facturation')->take(400)->values(),
        ]);
    }

    /** Tous les dossiers facturés avec leur petite fourniture, état de paiement et dates. */
    private function dossiers(?string $siege): Collection
    {
        $interventions = Intervention::query()
            ->where('statut', 'accepte')
            ->when($siege, fn ($q) => $q->where('siege', $siege))
            ->whereHas('devis')
            ->with(['devis.lignes:id,devis_id,montant_ttc,is_accepted', 'vehicule:id,client_id,immatriculation,marque,modele', 'vehicule.client:id,nom,prenom'])
            ->get();

        $factures = Facture::whereIn('intervention_id', $interventions->pluck('id'))->get()->keyBy('intervention_id');

        // Date du paiement qui a soldé la facture = dernier paiement d'une facture soldée
        $derniersPaiements = Paiement::query()
            ->whereIn('facture_id', $factures->pluck('id'))
            ->selectRaw('facture_id, MAX(date_paiement) as dernier')
            ->groupBy('facture_id')
            ->pluck('dernier', 'facture_id');

        return $interventions
            ->map(function (Intervention $d) use ($factures, $derniersPaiements) {
                $brut = (float) $d->devis->lignes->where('is_accepted', true)->sum('montant_ttc');
                $total = PetiteFourniture::totalFinal($d->devis, $brut);
                $petiteFourniture = PetiteFourniture::montant($d->devis, $brut);
                $facture = $factures->get($d->id);
                $paye = (int) round((float) optional($facture)->montant_paye);
                $soldee = $total > 0 && $paye >= $total;
                $dernierPaiement = $facture ? ($derniersPaiements[$facture->id] ?? null) : null;
                $client = $d->vehicule?->client;
                // Même règle que PetiteFourniture : null = active
                $actif = $d->devis->petite_fourniture_active === null || (bool) $d->devis->petite_fourniture_active;

                return [
                    'id' => $d->id,
                    'numero_ot' => $d->numero_ot,
                    'siege' => $d->siege,
                    'client' => $client ? trim("{$client->prenom} {$client->nom}") : null,
                    'vehicule' => $d->vehicule ? trim("{$d->vehicule->marque} {$d->vehicule->modele} · {$d->vehicule->immatriculation}") : null,
                    'total_ttc' => $total,
                    'petite_fourniture' => $petiteFourniture,
                    'mode' => $actif === false
                        ? 'desactivee'
                        : ($d->devis->petite_fourniture_montant !== null ? 'saisie' : 'auto'),
                    'montant_paye' => $paye,
                    'soldee' => $soldee,
                    'date_facturation' => $d->devis->updated_at?->toIso8601String(),
                    'date_solde' => $soldee && $dernierPaiement ? Carbon::parse($dernierPaiement)->toIso8601String() : null,
                ];
            })
            ->filter(fn ($d) => $d['total_ttc'] > 0)
            ->values();
    }

    /**
     * Agrégat d'une période :
     *  - total : petites fournitures facturées dans la période (soldées ou non)
     *  - soldees : petites fournitures des factures soldées dans la période (date du solde)
     */
    private function agreger(Collection $dossiers, CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $dans = fn (?string $date) => $date && ($c = CarbonImmutable::parse($date))->gte($debut) && $c->lte($fin);

        $factures = $dossiers->filter(fn ($d) => $dans($d['date_facturation']));
        $soldees = $dossiers->filter(fn ($d) => $d['soldee'] && $dans($d['date_solde']));

        return [
            'total' => $factures->sum('petite_fourniture'),
            'non_soldees' => $factures->where('soldee', false)->sum('petite_fourniture'),
            'soldees' => $soldees->sum('petite_fourniture'),
            'nb_factures' => $factures->count(),
            'nb_soldees' => $soldees->count(),
        ];
    }

    private function periodes(Collection $dossiers, string $type): array
    {
        $maintenant = CarbonImmutable::now()->locale('fr');
        $nb = $type === 'mois' ? self::NB_MOIS : self::NB_SEMAINES;

        return collect(range(0, $nb - 1))
            ->map(function (int $i) use ($dossiers, $type, $maintenant) {
                if ($type === 'mois') {
                    $debut = $maintenant->subMonthsNoOverflow($i)->startOfMonth();
                    $fin = $debut->endOfMonth();
                    $label = ucfirst($debut->translatedFormat('F Y'));
                    $court = ucfirst($debut->translatedFormat('M y'));
                } else {
                    $debut = $maintenant->subWeeks($i)->startOfWeek();
                    $fin = $debut->endOfWeek();
                    $label = 'Semaine ' . $debut->isoWeek() . ' · ' . $debut->translatedFormat('d M') . ' – ' . $fin->translatedFormat('d M Y');
                    $court = 'S' . $debut->isoWeek();
                }

                return [
                    'cle' => $debut->toDateString(),
                    'label' => $label,
                    'court' => $court,
                    'debut' => $debut->toIso8601String(),
                    'fin' => $fin->toIso8601String(),
                    ...$this->agreger($dossiers, $debut, $fin),
                ];
            })
            ->all();
    }
}
