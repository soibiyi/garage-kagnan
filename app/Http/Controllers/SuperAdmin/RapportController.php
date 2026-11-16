<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Rapport;
use App\Support\Parametres;
use App\Support\RapportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Rapports quotidiens / hebdomadaires du super administrateur. */
class RapportController extends Controller
{
    public function index()
    {
        return Inertia::render('SuperAdmin/Rapports', [
            'rapports' => Rapport::query()->with('auteur:id,name')->latest('id')->paginate(20)->through(fn (Rapport $r) => $this->resume($r)),
            'reglages' => [
                'quotidien' => (bool) Parametres::get('rapport_quotidien'),
                'hebdomadaire' => (bool) Parametres::get('rapport_hebdomadaire'),
                'email' => (bool) Parametres::get('rapport_email'),
            ],
            'selection' => null,
        ]);
    }

    public function show(Rapport $rapport)
    {
        return Inertia::render('SuperAdmin/Rapports', [
            'rapports' => Rapport::query()->with('auteur:id,name')->latest('id')->paginate(20)->through(fn (Rapport $r) => $this->resume($r)),
            'reglages' => [
                'quotidien' => (bool) Parametres::get('rapport_quotidien'),
                'hebdomadaire' => (bool) Parametres::get('rapport_hebdomadaire'),
                'email' => (bool) Parametres::get('rapport_email'),
            ],
            'selection' => $this->resume($rapport) + ['donnees' => $rapport->donnees],
        ]);
    }

    public function generer(Request $request)
    {
        $data = $request->validate(['periode' => ['required', 'in:jour,semaine']]);
        $rapport = RapportBuilder::generer($data['periode'], $request->user()->id, envoyer: false);

        return redirect()->route('superadmin.rapports.show', $rapport)->with('success', 'Rapport généré.');
    }

    public function pdf(Rapport $rapport)
    {
        $pdf = Pdf::loadView('pdf.rapport-superadmin', [
            'rapport' => $rapport,
            'd' => $rapport->donnees,
            'sieges' => config('sieges'),
        ])->setPaper('a4');

        return $pdf->download('rapport-' . $rapport->periode . '-' . $rapport->debut->format('Y-m-d') . '.pdf');
    }

    private function resume(Rapport $r): array
    {
        $d = $r->donnees;

        return [
            'id' => $r->id,
            'periode' => $r->periode,
            'debut' => $r->debut,
            'fin' => $r->fin,
            'created_at' => $r->created_at,
            'auteur' => $r->auteur?->name,
            'ca' => $d['argent']['ca'] ?? 0,
            'encaisse' => $d['argent']['encaisse'] ?? 0,
            'actions_sensibles' => $d['actions_sensibles']['total'] ?? 0,
            'erreurs' => $d['erreurs']['nouvelles'] ?? 0,
        ];
    }
}
