<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\ExportExcel;
use App\Support\Periode;
use App\Support\StatsEquipes;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Performance des équipes, métier par métier. */
class EquipesController extends Controller
{
    /** Colonnes de chaque métier (partagées avec l'export Excel). */
    public const METIERS = [
        'mecaniciens' => ['titre' => 'Mécaniciens', 'colonnes' => [
            'dossiers' => 'Dossiers', 'livres' => 'Livrés', 'en_cours' => 'En cours (auj.)',
            'delai_moyen_jours' => 'Délai moyen (j)', 'reprises' => 'Reprises', 'taux_reprise' => 'Taux de reprise (%)',
        ]],
        'receptionnistes' => ['titre' => 'Réceptionnistes', 'colonnes' => [
            'vehicules_recus' => 'Véhicules reçus',
        ]],
        'administratifs' => ['titre' => 'Administratif', 'colonnes' => [
            'devis' => 'Devis créés', 'devis_acceptes' => 'Devis acceptés', 'montant_devis' => 'Montant des devis (FCFA)',
            'paiements' => 'Paiements enregistrés', 'montant_encaisse' => 'Montant encaissé (FCFA)',
        ]],
        'chauffeurs' => ['titre' => 'Chauffeurs & dépanneurs', 'colonnes' => [
            'missions' => 'Missions terminées', 'km' => 'Km parcourus', 'duree_moyenne_min' => 'Durée moyenne (min)',
            'reaction_moyenne_min' => 'Délai d\'acceptation (min)', 'refus' => 'Refus',
        ]],
        'charges_clients' => ['titre' => 'Chargés clients', 'colonnes' => [
            'interactions' => 'Interactions', 'clients_contactes' => 'Clients contactés', 'relances_echues' => 'Relances échues',
            'conversions' => 'Clients revenus au garage', 'taux_conversion' => 'Taux de conversion (%)',
        ]],
        'secretaires' => ['titre' => 'Secrétariat', 'colonnes' => [
            'taches' => 'Tâches reçues', 'terminees' => 'Terminées', 'terminees_en_retard' => 'Terminées en retard',
            'en_retard' => 'En retard (auj.)', 'taux_a_l_heure' => 'À l\'heure (%)',
        ]],
    ];

    public function index(Request $request)
    {
        $periode = Periode::depuis($request->string('periode')->toString());
        $siege = $this->siege($request);

        return Inertia::render('SuperAdmin/Equipes', [
            'periode' => $periode->versTableau(),
            'siege' => $siege,
            'metiers' => self::METIERS,
            'stats' => StatsEquipes::calculer($periode, $siege),
        ]);
    }

    public function export(Request $request)
    {
        $periode = Periode::depuis($request->string('periode')->toString());
        $siege = $this->siege($request);
        $stats = StatsEquipes::calculer($periode, $siege);

        $feuilles = [];
        foreach (self::METIERS as $cle => $metier) {
            $feuilles[] = [
                'titre' => $metier['titre'],
                'colonnes' => ['name' => 'Nom', 'siege' => 'Siège'] + $metier['colonnes'] + ['actions' => 'Actions dans l\'application'],
                'lignes' => $stats[$cle],
            ];
        }

        return ExportExcel::telecharger('performance-equipes', $periode->libelle() . ($siege ? " · {$siege}" : ''), $feuilles);
    }

    private function siege(Request $request): ?string
    {
        $siege = $request->string('siege')->toString();

        return array_key_exists($siege, config('sieges')) ? $siege : null;
    }
}
