<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantDirectoryClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Comptes administrateurs d'un établissement — son service informatique.
 *
 * Ils ne se créent que d'ici : personne, dans l'établissement, n'accorde un
 * niveau égal au sien. L'administrateur crée ensuite tous les autres comptes
 * dans l'application. Tout passe par l'API de l'établissement.
 */
class TenantAdminAccountController extends Controller
{
    public function __construct(private readonly TenantDirectoryClient $annuaire)
    {
    }

    private function authorizeTenant(Tenant $tenant): void
    {
        $user = Auth::user();

        abort_unless($user, 401);
        abort_unless($user->isTechAdmin() || $tenant->owner_id === $user->id, 403,
            "Vous n'avez pas l'autorisation de gérer cet établissement.");
    }

    private function retour(Tenant $tenant): RedirectResponse
    {
        return redirect()->route(
            (Auth::user()->isTechAdmin() ? 'tech.' : 'business.') . 'establishments.permissions',
            ['tenant' => $tenant, 'onglet' => 'administrateurs']
        );
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $valide = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        // Sans mot de passe saisi, on en tire un : il n'est montré qu'une fois.
        $genere = empty($valide['password']) ? Str::password(14, symbols: false) : null;

        $resultat = $this->annuaire->creerAdministrateur($tenant, [
            'name'     => $valide['name'],
            'email'    => $valide['email'],
            'phone'    => $valide['phone'] ?? null,
            'password' => $genere ?? $valide['password'],
            'auteur'   => Auth::user()->name . ' <' . Auth::user()->email . '>',
        ]);

        AuditLog::record(Auth::id(), 'tenant_admin_create',
            "Compte administrateur {$valide['name']} ({$valide['email']}) pour {$tenant->name} — "
                . ($resultat['ok'] ? 'créé' : 'échec : ' . $resultat['message']),
            'security', ['tenant_id' => $tenant->id]);

        if (!$resultat['ok']) {
            return $this->retour($tenant)->withInput($request->except('password'))->with('error', $resultat['message']);
        }

        return $this->retour($tenant)
            ->with('success', "Compte administrateur de {$valide['name']} créé.")
            ->with('identifiants', $genere ? ['email' => strtolower($valide['email']), 'password' => $genere] : null);
    }

    /** Réinitialiser le mot de passe, désactiver ou réactiver un administrateur. */
    public function update(Request $request, Tenant $tenant, int $compte): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $valide = $request->validate([
            'action'   => ['required', 'in:reinitialiser,desactiver,reactiver'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'nom'      => ['nullable', 'string', 'max:255'],
        ]);

        $auteur = Auth::user()->name . ' <' . Auth::user()->email . '>';
        $genere = null;

        $donnees = match ($valide['action']) {
            'reinitialiser' => ['password' => ($valide['password'] ?? null) ?: ($genere = Str::password(14, symbols: false))],
            'desactiver'    => ['actif' => false],
            'reactiver'     => ['actif' => true],
        };

        $resultat = $this->annuaire->modifierAdministrateur($tenant, $compte, $donnees + ['auteur' => $auteur]);

        $libelle = match ($valide['action']) {
            'reinitialiser' => 'mot de passe réinitialisé',
            'desactiver'    => 'désactivé',
            'reactiver'     => 'réactivé',
        };
        $nom = $valide['nom'] ?? "#{$compte}";

        AuditLog::record(Auth::id(), 'tenant_admin_update',
            "Compte administrateur {$nom} de {$tenant->name} : {$libelle}"
                . ($resultat['ok'] ? '' : ' — échec : ' . $resultat['message']),
            'security', ['tenant_id' => $tenant->id, 'compte' => $compte]);

        if (!$resultat['ok']) {
            return $this->retour($tenant)->with('error', $resultat['message']);
        }

        return $this->retour($tenant)
            ->with('success', "Compte administrateur {$nom} : {$libelle}.")
            ->with('identifiants', $genere ? ['email' => $nom, 'password' => $genere] : null);
    }
}
