<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Models\Intervention;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class VehiculeParcController extends Controller
{
    /**
     * Affiche la liste des véhicules sur le parc et ceux en atelier/administration.
     */
    public function index()
    {
        // 1. Véhicules toujours sur le parc (en attente de prise en charge / au statut réception)
        $interventionsParc = Intervention::duSiege()->with(['vehicule.client', 'receptionniste', 'mecanicien'])
            ->where('statut', 'reception')
            ->latest('date_reception')
            ->get();

        // 2. Véhicules passés en mode atelier / administration (sortis du parc principal)
        $interventionsAtelier = Intervention::duSiege()->with(['vehicule.client', 'receptionniste', 'mecanicien'])
            ->whereIn('statut', ['atelier', 'en_cours', 'attente_accord'])
            ->latest('date_reception')
            ->get();

        // Récupère uniquement les utilisateurs qui ont le rôle 'mecanicien'
        // Uniquement les mécaniciens du même siège (l'admin les voit tous)
        $mecaniciens = User::where('role', 'mecanicien')
            ->when(auth()->user()->role !== 'admin', fn ($q) => $q->where('siege', auth()->user()->siege))
            ->get();

        return Inertia::render('Reception/Parc/Index', [
            'interventions' => $interventionsParc,
            'interventionsAtelier' => $interventionsAtelier,
            'mecaniciens' => $mecaniciens,
        ]);
    }

    /**
     * Affiche les détails d'une intervention / véhicule sur le parc.
     */
    public function show($id)
    {
        $intervention = Intervention::duSiege()->with(['vehicule.client', 'receptionniste', 'mecanicien'])
            ->findOrFail($id);

        return Inertia::render('Reception/Parc/Show', [
            'intervention' => $intervention
        ]);
    }

    /**
     * Assigne un mécanicien, enregistre le rapport des pannes et fait avancer le dossier vers l'administration.
     */
    public function updateProgress(Request $request, $id)
    {
        $request->validate([
            'mecanicien_id' => 'required|exists:users,id',
            'rapport_mecanicien' => 'required|string',
        ]);

        $intervention = Intervention::duSiege()->findOrFail($id);

        $intervention->update([
            'mecanicien_id' => $request->mecanicien_id,
            'rapport_mecanicien' => $request->rapport_mecanicien,
            'statut' => 'atelier', // Passe le statut à l'atelier / administration
        ]);

        // Redirige vers le tableau de bord général pour y voir s'afficher le dossier
        return redirect()->route('dashboard')->with('success', 'Dossier transmis avec succès à l\'administration !');
    }

    /** Seuls la réception et l'admin peuvent modifier les infos. */
private function autoriserModification(): void
{
    abort_unless(in_array(auth()->user()->role, ['admin', 'receptionniste']), 403);
}

private function messagesModification(): array
{
    return [
        'required' => 'Le champ « :attribute » est obligatoire.',
        'email' => "L'adresse e-mail n'est pas valide.",
        'integer' => 'Le champ « :attribute » doit être un nombre entier.',
        'date' => 'Le champ « :attribute » doit être une date valide.',
        'unique' => 'Cette immatriculation est déjà utilisée par un autre véhicule.',
        'max.string' => 'Le champ « :attribute » ne doit pas dépasser :max caractères.',
    ];
}

/** Modifie le véhicule (et le kilométrage d'entrée de ce dossier). */
public function updateVehicule(Request $request, $id)
{
    $this->autoriserModification();

    $intervention = Intervention::duSiege()->with('vehicule')->findOrFail($id);
    $vehicule = $intervention->vehicule;

    // Normalisation avant validation (la vérification d'unicité se fait sur la valeur en majuscules)
    $request->merge([
        'immatriculation' => strtoupper(trim((string) $request->immatriculation)),
        'vin' => $request->filled('vin') ? strtoupper(trim($request->vin)) : null,
    ]);

    $data = $request->validate([
        'immatriculation' => ['required', 'string', 'max:50', Rule::unique('vehicules', 'immatriculation')->ignore($vehicule->id)],
        'marque' => 'nullable|string|max:100',
        'modele' => 'nullable|string|max:100',
        'vin' => 'nullable|string|max:100',
        'kilometrage' => 'required|integer|min:0',
        'expiration_assurance' => 'nullable|date',
        'expiration_sicta' => 'nullable|date',
    ], $this->messagesModification(), [
        'immatriculation' => 'Immatriculation',
        'marque' => 'Marque',
        'modele' => 'Modèle',
        'vin' => 'Numéro de châssis (VIN)',
        'kilometrage' => 'Kilométrage',
        'expiration_assurance' => 'Expiration assurance',
        'expiration_sicta' => 'Expiration SICTA',
    ]);

    $vehicule->update(collect($data)->except('kilometrage')->all());
    $intervention->update(['kilometrage' => $data['kilometrage']]);

    return back()->with('success', 'Informations du véhicule mises à jour.');
}

/** Modifie le client / propriétaire du véhicule. */
public function updateClient(Request $request, $id)
{
    $this->autoriserModification();

    $intervention = Intervention::duSiege()->with('vehicule.client')->findOrFail($id);
    $client = $intervention->vehicule->client;
    abort_unless($client, 404);

    $data = $request->validate([
        'nom' => 'required|string|max:255',
        'prenom' => 'nullable|string|max:255',
        'telephone' => 'required|string|max:50',
        'email' => 'nullable|email|max:255',
        'adresse' => 'nullable|string',
    ], $this->messagesModification(), [
        'nom' => 'Nom',
        'prenom' => 'Prénom',
        'telephone' => 'Téléphone',
        'email' => 'E-mail',
        'adresse' => 'Adresse',
    ]);

    $client->update($data);

    return back()->with('success', 'Informations du client mises à jour.');
}

/**
 * Supprime UN dossier (une intervention) : sa facture et ses paiements, son devis et ses lignes,
 * ses photos. Le véhicule et le client ne sont jamais supprimés : un client qui possède
 * d'autres véhicules / dossiers les garde intacts.
 */
public function destroy($id)
{
    $this->autoriserModification();

    $intervention = Intervention::duSiege()->with('devis')->findOrFail($id);

    // Photos à effacer du disque (après la suppression en base)
    $photos = collect([
        $intervention->photo_avant,
        $intervention->photo_arriere,
        $intervention->photo_gauche,
        $intervention->photo_droite,
    ]);

    $supp = $intervention->photos_supplementaires;
    if (is_string($supp)) {
        $supp = json_decode($supp, true);
    }
    $photos = $photos->merge((array) $supp)->filter()->unique()->values();

    $numeroOt = $intervention->numero_ot;

    DB::transaction(function () use ($intervention) {
        // Facture et paiements de ce dossier
        if ($facture = Facture::where('intervention_id', $intervention->id)->first()) {
            Paiement::where('facture_id', $facture->id)->delete();
            $facture->delete();
        }

        // Devis et lignes de ce dossier
        if ($intervention->devis) {
            $intervention->devis->lignes()->delete();
            $intervention->devis->delete();
        }

        $intervention->delete();
    });

    foreach ($photos as $chemin) {
        Storage::disk('public')->delete($chemin);
    }

    return back()->with('success', 'Le dossier ' . ($numeroOt ?: '') . ' a été supprimé.');
}

}