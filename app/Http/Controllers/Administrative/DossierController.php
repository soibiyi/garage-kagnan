<?php

namespace App\Http\Controllers\Administrative;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use Inertia\Inertia;

class DossierController extends Controller
{
    public function index()
    {
        // On récupère les dossiers qui ont le statut 'atelier' (transmis par la réception/atelier)
        $dossiers = Intervention::with(['vehicule.client', 'mecanicien', 'receptionniste'])
            ->where('statut', 'atelier') 
            ->latest()
            ->get();

        return Inertia::render('Administration/DossiersIndex', [
            'dossiers' => $dossiers
        ]);
    }

    public function show(Intervention $dossier)
    {
        $dossier->load(['vehicule.client', 'mecanicien', 'receptionniste']);

        return Inertia::render('Administration/DossierShow', [
            'dossier' => $dossier
        ]);
    }
}