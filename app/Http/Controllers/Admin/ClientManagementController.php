<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statut = $request->query('statut', 'tous');

        $clients = Client::query()
            ->with(['vehicules' => fn ($q) => $q->latest(), 'portalRequests' => fn ($q) => $q->latest()->limit(5)])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('portal_identifier', 'like', "%{$search}%");
                });
            })
            ->when($statut !== 'tous', function ($query) use ($statut) {
                if ($statut === 'actifs') {
                    $query->where(function ($q) {
                        $q->where('status', '!=', 'banni')->orWhereNull('status');
                    })->whereNull('banned_at');
                }

                if ($statut === 'bannis') {
                    $query->where(function ($q) {
                        $q->where('status', 'banni')->orWhereNotNull('banned_at');
                    });
                }
            })
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
                'statut' => $statut,
            ],
            'stats' => [
                'total' => Client::count(),
                'actifs' => Client::where(function ($q) {
                    $q->where('status', '!=', 'banni')->orWhereNull('status');
                })->whereNull('banned_at')->count(),
                'bannis' => Client::where(function ($q) {
                    $q->where('status', 'banni')->orWhereNotNull('banned_at');
                })->count(),
            ],
        ]);
    }

    public function show(Client $client)
    {
        $client->load([
            'vehicules.interventions' => fn ($query) => $query->latest('date_reception'),
            'vehicules.interventions.devis',
            'vehicules.interventions.facture',
            'portalRequests' => fn ($query) => $query->latest()->take(20),
            'consignes' => fn ($query) => $query->latest()->take(20),
        ]);

        return Inertia::render('Admin/Clients/Show', [
            'client' => $client,
        ]);
    }

    public function toggleStatus(Client $client)
    {
        if ($client->isBanni()) {
            $client->reactiver();
            $message = 'Compte client réactivé.';
        } else {
            $client->bannir();
            $message = 'Compte client banni.';
        }

        return back()->with('success', $message);
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('admin.clients.index')->with('success', 'Compte client supprimé.');
    }
}
