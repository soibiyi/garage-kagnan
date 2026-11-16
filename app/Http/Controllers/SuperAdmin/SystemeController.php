<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Annonce;
use App\Models\Parametre;
use App\Support\Parametres;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Outils système : paramètres globaux, mode maintenance, annonces. */
class SystemeController extends Controller
{
    private const ROLES_ANNONCE = [
        'admin' => 'Administrateurs',
        'receptionniste' => 'Réceptionnistes',
        'mecanicien' => 'Mécaniciens',
        'administratif' => 'Administratif',
        'charge_client' => 'Chargés clients',
        'magasinier' => 'Magasiniers',
        'chauffeur' => 'Chauffeurs',
        'depanneur' => 'Dépanneurs',
        'secretaire' => 'Secrétaires',
    ];

    public function index()
    {
        $valeurs = Parametres::tous();
        $modifs = Parametre::query()->with('modifiePar:id,name')->get()->keyBy('cle');

        $parametres = collect(Parametres::DEFINITIONS)
            ->filter(fn ($d) => $d['groupe'] !== null)
            ->map(fn ($d, $cle) => [
                'cle' => $cle,
                'groupe' => $d['groupe'],
                'libelle' => $d['libelle'],
                'aide' => $d['aide'],
                'type' => is_bool($d['defaut']) ? 'booleen' : (in_array('date_format:H:i', $d['regles'], true) ? 'heure' : 'nombre'),
                'valeur' => $valeurs[$cle],
                'defaut' => $d['defaut'],
                'modifie' => isset($modifs[$cle]) ? ['par' => $modifs[$cle]->modifiePar?->name, 'le' => $modifs[$cle]->updated_at] : null,
            ])
            ->values()
            ->groupBy('groupe');

        return Inertia::render('SuperAdmin/Systeme', [
            'parametres' => $parametres,
            'maintenance' => [
                'active' => (bool) $valeurs['maintenance_active'],
                'message' => $valeurs['maintenance_message'],
                'depuis' => isset($modifs['maintenance_active']) && $valeurs['maintenance_active'] ? $modifs['maintenance_active']->updated_at : null,
            ],
            'annonces' => Annonce::query()->with('auteur:id,name')->latest('id')->limit(50)->get()->map(fn (Annonce $a) => [
                'id' => $a->id,
                'titre' => $a->titre,
                'message' => $a->message,
                'niveau' => $a->niveau,
                'roles' => $a->roles ?? [],
                'debut' => $a->debut?->format('Y-m-d\TH:i'),
                'fin' => $a->fin?->format('Y-m-d\TH:i'),
                'active' => $a->active,
                'en_cours' => $a->active && (! $a->debut || $a->debut->isPast()) && (! $a->fin || $a->fin->isFuture()),
                'auteur' => $a->auteur?->name,
                'created_at' => $a->created_at,
            ]),
            'rolesAnnonce' => self::ROLES_ANNONCE,
        ]);
    }

    public function parametres(Request $request)
    {
        $definitions = collect(Parametres::DEFINITIONS)->filter(fn ($d) => $d['groupe'] !== null);
        $data = $request->validate($definitions->map(fn ($d) => $d['regles'])->all());

        // Cases à cocher : vraies valeurs booléennes ; nombres : vrais nombres
        foreach ($data as $cle => $valeur) {
            $defaut = Parametres::DEFINITIONS[$cle]['defaut'];
            $data[$cle] = match (true) {
                is_bool($defaut) => (bool) $valeur,
                is_int($defaut) => (int) $valeur,
                is_float($defaut) => (float) $valeur,
                default => $valeur,
            };
        }

        Parametres::set($data, $request->user()->id);

        return back()->with('success', 'Paramètres enregistrés.');
    }

    public function maintenance(Request $request)
    {
        $data = $request->validate([
            'active' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        Parametres::set([
            'maintenance_active' => (bool) $data['active'],
            'maintenance_message' => $data['message'] ?: Parametres::DEFINITIONS['maintenance_message']['defaut'],
        ], $request->user()->id);

        return back()->with('success', $data['active']
            ? 'Mode maintenance activé : seuls les super administrateurs peuvent utiliser l’application.'
            : 'Mode maintenance désactivé : l’application est de nouveau ouverte à tous.');
    }

    public function annonceStore(Request $request)
    {
        Annonce::create($this->validerAnnonce($request) + ['auteur_id' => $request->user()->id]);

        return back()->with('success', 'Annonce publiée.');
    }

    public function annonceUpdate(Request $request, Annonce $annonce)
    {
        $annonce->update($this->validerAnnonce($request));

        return back()->with('success', 'Annonce mise à jour.');
    }

    public function annonceDestroy(Annonce $annonce)
    {
        $annonce->delete();

        return back()->with('success', 'Annonce supprimée.');
    }

    private function validerAnnonce(Request $request): array
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:2000'],
            'niveau' => ['required', Rule::in(Annonce::NIVEAUX)],
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in(array_keys(self::ROLES_ANNONCE))],
            'debut' => ['nullable', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:debut'],
            'active' => ['required', 'boolean'],
        ]);
        $data['roles'] = ! empty($data['roles']) ? array_values($data['roles']) : null;

        return $data;
    }
}
