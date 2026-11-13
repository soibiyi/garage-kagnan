<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Retour au compte du super administrateur après un « Voir en tant que ». */
class ImpersonationController extends Controller
{
    public function stop(Request $request)
    {
        $id = $request->session()->pull('impersonator_id');
        $superAdmin = $id ? User::find($id) : null;

        abort_unless($superAdmin?->estSuperAdmin(), 403);

        $consulte = $request->user();
        Auth::login($superAdmin);

        return redirect()
            ->route('superadmin.accueil', ['vu' => $consulte?->id])
            ->with('success', 'Vous êtes revenu à votre compte super administrateur.');
    }
}
