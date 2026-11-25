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
            'dossiers' => $dossiers->sortByDesc('date_facturation')->take(400)->values(),
        ]);
    }

    /** Dossiers réellement facturés : devis accepté + facture existante en base. */
    private function dossiers(?string $siege): Collection
    {
        $interventions = Intervention::query()
            ->where('statut', 'accepte')
            ->when($siege, fn ($q) => $q->where('siege', $siege))
            ->whereHas('devis', fn ($q) => $q->where('statut', 'accepte'))
            ->whereHas('facture')
            ->with([
                'devis' => fn ($q) => $q->where('statut', 'accepte'),
                'devis.lignes' => fn ($q) => $q->select('id', 'devis_id', 'montant_ttc', 'is_accepted')->where('is_accepted', true),
                'vehicule:id,client_id,immatriculation,marque,modele',
                'vehicule.client:id,nom,prenom',
            ])
            ->get();

        $factures = Facture::whereIn('intervention_id', $interventions->pluck('id'))
            ->get()
            ->keyBy('intervention_id');

        $factureIds = $factures->pluck('id');

        $paiementsParFacture = Paiement::query()
            ->whereIn('facture_id', $factureIds)
            ->selectRaw('facture_id, COALESCE(SUM(montant), 0) as total_paye, MAX(date_paiement) as dernier_paiement')
            ->groupBy('facture_id')
            ->get()
            ->keyBy('facture_id');

        return $interventions
            ->map(function (Intervention $d) use ($factures, $paiementsParFacture) {
                $facture = $factures->get($d->id);

                // Si la facture n'existe plus en base : aucune petite fourniture ni statut soldé
                if (!$facture || !$d->devis) {
                    return null;
                }

                $brut = (float) $d->devis->lignes->sum('montant_ttc');
                $total = (float) PetiteFourniture::totalFinal($d->devis, $brut);
                $petiteFourniture = (float) PetiteFourniture::montant($d->devis, $brut);

                $statsPaiement = $paiementsParFacture->get($facture->id);
                $paye = $statsPaiement
                    ? (int) round((float) $statsPaiement->total_paye)
                    : (int) round((float) $facture->montant_paye);

                $soldee = $total > 0 && $paye >= $total;
                $dernierPaiement = $statsPaiement?->dernier_paiement;
                $client = $d->vehicule?->client;
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
            ->filter(fn ($d) => $d !== null && $d['total_ttc'] > 0)
            ->values();
    }

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