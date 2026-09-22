<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Vehicule;
use App\Models\Intervention;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ReceptionController extends Controller
{
    // Affiche le formulaire de nouvelle réception
    public function create()
    {
        return Inertia::render('Reception/Create', [
            'clients' => Client::with('vehicules')->orderBy('nom')->get()
        ]);
    }

    // Enregistre le client, le véhicule et l'intervention initiale
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'nom' => 'nullable|required_without:client_id|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'telephone' => 'nullable|required_without:client_id|string|max:50',
            'email' => 'nullable|email|max:255',
            'adresse' => 'nullable|string',
            'type_client' => 'nullable|string',

            'immatriculation' => 'required|string|max:50',
            'marque' => 'nullable|string|max:100',
            'modele' => 'nullable|string|max:100',
            'vin' => 'nullable|string|max:100',
            'expiration_assurance' => 'nullable|date',
            'expiration_sicta' => 'nullable|date',

            // Nouveaux champs d'intervention
            'numero_ot' => 'nullable|string|max:191',
            'date_reception' => 'nullable|date',
            'kilometrage' => 'required|integer',
            'personne_a_contacter' => 'nullable|string|max:191',
            'circuit' => 'required|in:normal,devis_direct',
            
            // Équipements (boolean)
            'allume_cigare' => 'boolean',
            'rk7' => 'boolean',
            'rcd' => 'boolean',
            'essuie_glace_av' => 'boolean',
            'essuie_glace_ar' => 'boolean',
            'retro_ext_gauche' => 'boolean',
            'retro_ext_droit' => 'boolean',
            'retro_int' => 'boolean',
            'cric' => 'boolean',
            'manivelle' => 'boolean',
            'roue_secours' => 'boolean',
            'trousse' => 'boolean',
            'pare_brise_fissure' => 'boolean',

            'niveau_carburant' => 'nullable|string|max:50',
            'intervalle_niveau_carburant' => 'nullable|string|max:191',
            'remarques_eventuelles' => 'nullable|string',

            'photo_avant' => 'nullable|image|max:5120',
            'photo_arriere' => 'nullable|image|max:5120',
            'photo_gauche' => 'nullable|image|max:5120',
            'photo_droite' => 'nullable|image|max:5120',
        ]);

        // Gestion client
        if (!empty($validated['client_id'])) {
            $clientId = $validated['client_id'];
        } else {
            $client = Client::create([
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'] ?? null,
                'telephone' => $validated['telephone'],
                'email' => $validated['email'] ?? null,
                'adresse' => $validated['adresse'] ?? null,
                'type_client' => $validated['type_client'] ?? 'particulier',
            ]);
            $clientId = $client->id;
        }

        // Gestion véhicule
        $immat = strtoupper(trim($validated['immatriculation']));
        $vehicule = Vehicule::updateOrCreate(
            ['immatriculation' => $immat],
            [
                'client_id' => $clientId,
                'marque' => $validated['marque'] ?? null,
                'modele' => $validated['modele'] ?? null,
                'vin' => isset($validated['vin']) ? strtoupper($validated['vin']) : null,
                'expiration_assurance' => $validated['expiration_assurance'] ?? null,
                'expiration_sicta' => $validated['expiration_sicta'] ?? null,
            ]
        );

        // Upload des photos
        $photoPaths = [];
        foreach (['photo_avant', 'photo_arriere', 'photo_gauche', 'photo_droite'] as $photoField) {
            if ($request->hasFile($photoField)) {
                $photoPaths[$photoField] = $request->file($photoField)->store('interventions', 'public');
            } else {
                $photoPaths[$photoField] = null;
            }
        }

        // --- TRAITEMENT DE LA DATE ET DE L'HEURE EXACTE ---
        $dateBase = !empty($validated['date_reception']) 
            ? Carbon::parse($validated['date_reception'])->format('Y-m-d') 
            : now()->format('Y-m-d');
        
        // On combine la date choisie (ou du jour) avec l'heure exacte courante
        $dateHeureExacte = $dateBase . ' ' . now()->format('H:i:s');

        // Création de l'intervention avec TOUTES les informations
        Intervention::create([
            'vehicule_id' => $vehicule->id,
            'receptionniste_id' => auth()->id(),
            'numero_ot' => $validated['numero_ot'] ?? 'OT-' . date('Ymd-His'),
            'date_reception' => $dateHeureExacte, // <-- Injecte la date ET l'heure
            'kilometrage' => $validated['kilometrage'],
            'personne_a_contacter' => $validated['personne_a_contacter'] ?? null,
            'circuit' => $validated['circuit'],

            // Équipements
            'allume_cigare' => $validated['allume_cigare'] ?? false,
            'rk7' => $validated['rk7'] ?? false,
            'rcd' => $validated['rcd'] ?? false,
            'essuie_glace_av' => $validated['essuie_glace_av'] ?? false,
            'essuie_glace_ar' => $validated['essuie_glace_ar'] ?? false,
            'retro_ext_gauche' => $validated['retro_ext_gauche'] ?? false,
            'retro_ext_droit' => $validated['retro_ext_droit'] ?? false,
            'retro_int' => $validated['retro_int'] ?? false,
            'cric' => $validated['cric'] ?? false,
            'manivelle' => $validated['manivelle'] ?? false,
            'roue_secours' => $validated['roue_secours'] ?? false,
            'trousse' => $validated['trousse'] ?? false,
            'pare_brise_fissure' => $validated['pare_brise_fissure'] ?? false,

            'niveau_carburant' => $validated['niveau_carburant'] ?? null,
            'intervalle_niveau_carburant' => $validated['intervalle_niveau_carburant'] ?? null,
            'remarques_eventuelles' => $validated['remarques_eventuelles'] ?? null,
            'statut' => 'reception',

            'photo_avant' => $photoPaths['photo_avant'],
            'photo_arriere' => $photoPaths['photo_arriere'],
            'photo_gauche' => $photoPaths['photo_gauche'],
            'photo_droite' => $photoPaths['photo_droite'],
        ]);

        return redirect()->route('dashboard')->with('success', 'Fiche de réception complète enregistrée avec succès !');
    }
}