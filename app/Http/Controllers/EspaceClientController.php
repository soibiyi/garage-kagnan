<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class EspaceClientController extends Controller
{
    // Page d'accueil : presentation, inscription, connexion
    public function index()
    {
        return Inertia::render('EspaceClient/Index');
    }

    // Tableau de bord du client
    public function dashboard()
    {
        return Inertia::render('EspaceClient/Dashboard');
    }
}
