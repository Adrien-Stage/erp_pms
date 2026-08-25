<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Registre des propriétaires de la plateforme.
 *
 * Un propriétaire est un utilisateur de rôle « owner » ; il détient un ou
 * plusieurs établissements. Cet écran donne à l'administrateur technique une
 * entrée par personne plutôt que par établissement, et permet d'ouvrir la
 * fiche d'un de ses établissements sans repasser par l'onglet Établissements.
 *
 * Contrôleur séparé d'AdminAuditController, déjà volumineux : la gestion des
 * personnes n'a pas les mêmes règles que celle des conteneurs.
 */
class OwnerController extends Controller
{
    /**
     * Le middleware de route couvre déjà ce point ; ce garde-fou protège les
     * méthodes si la route venait à être remontée hors du groupe « tech ».
     */
    private function ensureTechAdmin(): void
    {
        abort_unless(Auth::user()?->isTechAdmin(), 403);
    }

    /**
     * Le paramètre de route est un User quelconque : on refuse tout ce qui
     * n'est pas un propriétaire, pour que cet écran ne serve jamais à
     * modifier ou supprimer un administrateur technique.
     */
    private function ensureOwner(User $owner): void
    {
        $this->ensureTechAdmin();
        abort_unless($owner->isOwner(), 404);
    }

    public function index(Request $request): View
    {
        $this->ensureTechAdmin();

        $search = trim((string) $request->query('q', ''));

        $owners = User::query()
            ->where('role', User::ROLE_OWNER)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->withCount('tenants')
            ->orderBy('name')
            ->get();

        return view('admin.owners.index', [
            'owners' => $owners,
            'search' => $search,
            'stats'  => [
                'total'          => $owners->count(),
                'actifs'         => $owners->where('is_active', true)->count(),
                'inactifs'       => $owners->where('is_active', false)->count(),
                'etablissements' => $owners->sum('tenants_count'),
            ],
        ]);
    }

    public function show(User $owner): View
    {
        $this->ensureOwner($owner);

        $owner->load(['tenants' => fn ($q) => $q->orderBy('name')]);

        return view('admin.owners.show', compact('owner'));
    }

    /** Modification des coordonnées. Le mot de passe n'est touché que s'il est fourni. */
    public function update(Request $request, User $owner): RedirectResponse
    {
        $this->ensureOwner($owner);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($owner->id)],
            'phone'        => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'nationality'  => ['nullable', 'string', 'max:100'],
            'password'     => ['nullable', 'string', 'min:4'],
        ], [
            'email.unique' => 'Cette adresse est déjà utilisée par un autre compte.',
        ]);

        $owner->fill([
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'phone'        => $validated['phone'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'nationality'  => $validated['nationality'] ?? null,
        ]);

        // Champ laissé vide = mot de passe inchangé, et non effacé.
        if (!empty($validated['password'])) {
            $owner->password = Hash::make($validated['password']);
        }

        $owner->save();

        AuditLog::record(Auth::id(), 'owner_update', "Propriétaire {$owner->name} modifié", 'owners');

        return back()->with('success', "Les informations de {$owner->name} ont été enregistrées.");
    }

    /** Activation / désactivation du compte propriétaire. */
    public function toggleActive(User $owner): RedirectResponse
    {
        $this->ensureOwner($owner);

        $owner->update(['is_active' => !$owner->is_active]);

        $etat = $owner->is_active ? 'activé' : 'désactivé';
        AuditLog::record(Auth::id(), 'owner_toggle_active', "Propriétaire {$owner->name} {$etat}", 'owners');

        return back()->with('success', "Le compte de {$owner->name} a été {$etat}.");
    }

    /**
     * Suppression du compte.
     *
     * La clé étrangère tenants.owner_id est en cascade : supprimer un
     * propriétaire qui détient encore des établissements effacerait ces
     * établissements de la base, sans toucher à leurs conteneurs ni à leurs
     * sauvegardes. On refuse tant que le parc n'a pas été réaffecté ou
     * supprimé explicitement.
     */
    public function destroy(User $owner): RedirectResponse
    {
        $this->ensureOwner($owner);

        $count = $owner->tenants()->count();

        if ($count > 0) {
            return back()->with('error', sprintf(
                'Impossible de supprimer %s : ce propriétaire détient encore %d établissement%s. '
                . 'Réaffectez-les ou supprimez-les d\'abord.',
                $owner->name,
                $count,
                $count > 1 ? 's' : ''
            ));
        }

        $nom = $owner->name;
        $owner->delete();

        AuditLog::record(Auth::id(), 'owner_delete', "Propriétaire {$nom} supprimé", 'owners');

        return redirect()->route('tech.owners.index')
            ->with('success', "Le compte de {$nom} a été supprimé.");
    }
}
