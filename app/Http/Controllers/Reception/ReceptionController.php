<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Vehicule;
use App\Models\Intervention;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReceptionController extends Controller
{
    /**
     * Prochain numéro d'OT d'un siège pour aujourd'hui : SGK-02102026/001, ZGK-02102026/001, ...
     * On repart de l'OT le plus élevé déjà émis par ce siège aujourd'hui (le compteur
     * redémarre à 001 chaque jour et reste indépendant d'un siège à l'autre).
     */
    private function prochainNumeroOt(string $siege): string
    {
        // Pour passer à l'année sur 2 chiffres (SGK-021026/001), remplacer 'dmY' par 'dmy'
        $prefix = $siege . '-' . Carbon::now()->format('dmY') . '/';

        $dernier = Intervention::where('numero_ot', 'like', $prefix . '%')
            ->pluck('numero_ot')
            ->map(fn ($ot) => (int) substr($ot, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad($dernier + 1, 3, '0', STR_PAD_LEFT);
    }

    // Affiche le formulaire de nouvelle réception avec génération automatique du numéro OT
    public function create()
    {
        $user = auth()->user();

        // Un employé sans siège attribué ne peut pas ouvrir de dossier (l'admin choisit le siège dans le formulaire)
        if ($user->role !== 'admin' && empty($user->siege)) {
            return redirect()->route('dashboard')
                ->with('error', "Aucun siège ne vous est attribué. Contactez l'administrateur.");
        }

        // Un prochain numéro d'OT par siège : le formulaire affiche celui du siège concerné
        $defaultNumerosOt = collect(array_keys(config('sieges')))
            ->mapWithKeys(fn ($code) => [$code => $this->prochainNumeroOt($code)]);

        return Inertia::render('Reception/Create', [
            'clients' => Client::with('vehicules')->orderBy('nom')->get(),
            'defaultNumerosOt' => $defaultNumerosOt,
            'siege' => $user->siege, // null pour l'admin
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
            'siege' => ['nullable', Rule::in(array_keys(config('sieges')))],
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

        // Upload des photos sur le disque public
        $photoPaths = [];
        foreach (['photo_avant', 'photo_arriere', 'photo_gauche', 'photo_droite'] as $photoField) {
            if ($request->hasFile($photoField)) {
                $photoPaths[$photoField] = $request->file($photoField)->store('interventions/photos', 'public');
            } else {
                $photoPaths[$photoField] = null;
            }
        }

        // --- TRAITEMENT DE LA DATE ET DE L'HEURE EXACTE ---
        $dateBase = !empty($validated['date_reception']) 
            ? Carbon::parse($validated['date_reception'])->format('Y-m-d') 
            : now()->format('Y-m-d');
        
        $dateHeureExacte = $dateBase . ' ' . now()->format('H:i:s');

        // Siège : celui du compte connecté, sinon (admin) celui choisi dans le formulaire
        $siege = auth()->user()->siege ?: ($validated['siege'] ?? null);
        if (empty($siege)) {
            throw ValidationException::withMessages(['siege' => 'Veuillez choisir le siège.']);
        }

        // Le numéro d'OT est toujours recalculé côté serveur (jamais pris du formulaire) :
        // évite les doublons dus à un numéro périmé resté affiché dans le formulaire
        $numeroOt = $this->prochainNumeroOt($siege);

        // Création de l'intervention avec TOUTES les informations
        Intervention::create([
            'vehicule_id' => $vehicule->id,
            'siege' => $siege,
            'receptionniste_id' => auth()->id(),
            'numero_ot' => $numeroOt,
            'date_reception' => $dateHeureExacte,
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

        return redirect()->route('dashboard')->with('success', "Fiche de réception enregistrée. N° OT : {$numeroOt}");
    }
}