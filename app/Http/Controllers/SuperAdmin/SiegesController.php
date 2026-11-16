<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\ExportExcel;
use App\Support\Periode;
use App\Support\StatsSieges;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Comparatif des sièges côte à côte. */
class SiegesController extends Controller
{
    public function index(Request $request)
    {
        $periode = Periode::depuis($request->string('periode')->toString());

        return Inertia::render('SuperAdmin/Sieges', [
            'periode' => $periode->versTableau(),
            ...StatsSieges::calculer($periode),
        ]);
    }

    public function export(Request $request)
    {
        $periode = Periode::depuis($request->string('periode')->toString());
        $stats = StatsSieges::calculer($periode);

        return ExportExcel::telecharger('comparatif-sieges', $periode->libelle(), [[
            'titre' => 'Comparatif des sièges',
            'colonnes' => [
                'code' => 'Siège', 'nom' => 'Nom', 'interventions' => 'Véhicules reçus', 'livrees' => 'Livrés',
                'en_cours' => 'En cours (auj.)', 'delai_moyen_jours' => 'Délai réception→facture (j)',
                'nb_factures' => 'Factures', 'ca' => 'CA facturé (FCFA)', 'panier_moyen' => 'Panier moyen (FCFA)',
                'encaisse' => 'Encaissé (FCFA)', 'impayes' => 'Impayés à ce jour (FCFA)', 'devis' => 'Devis',
                'taux_acceptation' => 'Devis acceptés (%)', 'ruptures' => 'Ruptures de stock', 'sous_seuil' => 'Pièces sous le seuil',
                'valeur_stock' => 'Valeur du stock (FCFA)', 'demandes_portail' => 'Demandes portail',
                'demandes_terminees' => 'Demandes terminées', 'delai_traitement_min' => 'Délai de prise en charge (min)',
                'nouveaux_clients' => 'Nouveaux clients', 'effectif' => 'Effectif',
            ],
            'lignes' => $stats['sieges'],
        ]]);
    }
}
