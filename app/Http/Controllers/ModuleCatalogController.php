<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\ModuleCatalog;
use Illuminate\Support\Facades\Auth;

/**
 * Fiche détaillée d'un module de l'application établissement : à quoi il
 * sert, ses écrans, son guide d'utilisation et les établissements équipés.
 *
 * Page à part entière plutôt qu'onglet du dashboard : le guide est long et
 * mérite une adresse partageable, notamment pour l'envoyer à un manager.
 */
class ModuleCatalogController extends Controller
{
    public function show(string $module)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) {
            abort(403, "Accès interdit - Rôle TECH_ADMIN requis.");
        }

        $definition = ModuleCatalog::find($module);
        if (!$definition) {
            abort(404, "Module inconnu.");
        }

        $tenants = Tenant::orderBy('name')->get();

        return view('admin.modules.show', [
            'module'   => $definition,
            'equipped' => ModuleCatalog::tenantsWith($tenants, $definition['key']),
            'total'    => $tenants->count(),
        ]);
    }
}
