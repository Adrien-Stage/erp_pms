<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PDO;

/**
 * Gestion des employés d'un établissement depuis l'ERP.
 *
 * Ces employés vivent dans la base de leur établissement, pas dans celle de
 * l'ERP : écrire ici, c'est écrire dans la base que wetchah_app lit. Il n'y a
 * donc rien à synchroniser — une modification est visible immédiatement côté
 * établissement, et inversement.
 *
 * Les accès suivent le modèle de wetchah_app : une table « roles » par
 * établissement, et un pivot « role_user » portant le niveau (lecture ou
 * écriture) module par module.
 */
class TenantUserController extends Controller
{
    public function __construct(private TenantDatabase $tenantDb)
    {
    }

    /**
     * L'administrateur technique gère tout ; un propriétaire uniquement ses
     * propres établissements.
     */
    private function authorizeTenant(Tenant $tenant): void
    {
        $user = Auth::user();

        abort_unless($user, 401);
        abort_unless($user->isTechAdmin() || $tenant->owner_id === $user->id, 403,
            "Vous n'avez pas l'autorisation de gérer cet établissement.");
    }

    /**
     * Retour après action.
     *
     * Quand l'action part de la fiche d'un employé, on y revient : renvoyer à
     * la liste ferait perdre le contexte de consultation. La suppression fait
     * exception — la fiche n'existe plus.
     */
    private function retour(Tenant $tenant, ?int $userId = null): RedirectResponse
    {
        if ($userId !== null
            && request()->input('return_to') === 'fiche'
            && Auth::user()->isTechAdmin()) {
            return redirect()->route('tech.establishments.users.show', [
                'tenant' => $tenant,
                'user'   => $userId,
            ]);
        }

        return redirect()->route(
            Auth::user()->isTechAdmin() ? 'tech.establishments.show' : 'business.establishments.show',
            ['tenant' => $tenant, 'section' => 'users']
        );
    }

    /**
     * Fiche détaillée d'un employé.
     *
     * Rassemble sur un seul écran ce qui était éparpillé entre une ligne de
     * tableau et une modale : identité, état du compte, et surtout le détail
     * des accès module par module avec leur niveau — l'information qui décide
     * de ce que cette personne peut réellement faire.
     */
    public function show(Tenant $tenant, int $user)
    {
        $this->authorizeTenant($tenant);

        try {
            $employe = $this->tenantDb->userDetail($tenant, $user);

            if (!$employe) {
                return $this->retour($tenant)->with('error', 'Cet employé est introuvable dans cet établissement.');
            }

            $rolesAssignables = collect($this->tenantDb->assignableRoles($tenant));
            $managersActifs   = $this->tenantDb->activeManagerCount($tenant);
        } catch (\Throwable $e) {
            return $this->retour($tenant)->with('error', $this->messageErreur($e));
        }

        // Retirer le dernier manager fermerait l'administration de
        // l'établissement depuis wetchah_app : l'écran le dit avant le clic
        // plutôt qu'après.
        $dernierManager = $employe->role === 'manager' && $managersActifs <= 1;

        return view('admin.tenants.user', compact('tenant', 'employe', 'rolesAssignables', 'dernierManager'));
    }

    /** Activation / désactivation d'un employé. */
    public function toggleActive(Tenant $tenant, int $user): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        try {
            $employe = $this->tenantDb->findUser($tenant, $user);

            if (!$employe) {
                return $this->retour($tenant)->with('error', 'Cet employé est introuvable dans cet établissement.');
            }

            $nouvelEtat = !$employe->is_active;

            $stmt = $this->tenantDb->connect($tenant)
                ->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$nouvelEtat ? 'true' : 'false', $user]);
        } catch (\Throwable $e) {
            return $this->retour($tenant, $user)->with('error', $this->messageErreur($e));
        }

        $etat = $nouvelEtat ? 'activé' : 'désactivé';
        AuditLog::record(Auth::id(), 'tenant_user_toggle_active',
            "Employé {$employe->name} {$etat} dans {$tenant->name}", 'tenant_users');

        return $this->retour($tenant, $user)->with('success', "Le compte de {$employe->name} a été {$etat}.");
    }

    /** Suppression définitive d'un employé de l'établissement. */
    public function destroy(Tenant $tenant, int $user): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        try {
            $employe = $this->tenantDb->findUser($tenant, $user);

            if (!$employe) {
                return $this->retour($tenant)->with('error', 'Cet employé est introuvable dans cet établissement.');
            }

            // Le dernier manager retiré, plus personne ne peut administrer
            // l'établissement depuis wetchah_app : on refuse.
            if ($employe->role === 'manager' && $this->compteManagers($tenant) <= 1) {
                return $this->retour($tenant, $user)->with('error',
                    "Impossible de supprimer {$employe->name} : c'est le dernier manager de l'établissement. "
                    . 'Créez-en un autre avant de le retirer.');
            }

            $pdo = $this->tenantDb->connect($tenant);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user]);

            $tenant->update(['users_count' => max(0, $tenant->users_count - 1)]);
        } catch (\Throwable $e) {
            return $this->retour($tenant, $user)->with('error', $this->messageErreur($e));
        }

        AuditLog::record(Auth::id(), 'tenant_user_delete',
            "Employé {$employe->name} supprimé de {$tenant->name}", 'tenant_users');

        return $this->retour($tenant)->with('success', "Le compte de {$employe->name} a été supprimé.");
    }

    /**
     * Modification des informations et des accès.
     *
     * Le mot de passe n'est touché que s'il est fourni. Les rôles envoyés
     * remplacent intégralement ceux de l'employé — l'écran présente toujours
     * l'ensemble des rôles, donc l'absence d'une case vaut retrait.
     */
    public function update(Request $request, Tenant $tenant, int $user): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'password'      => ['nullable', 'string', 'min:4'],
            'role'          => ['nullable', 'string', 'max:50'],
            'roles'         => ['nullable', 'array'],
            'roles.*'       => ['integer'],
            'levels'        => ['nullable', 'array'],
            'levels.*'      => ['in:read,write'],
        ]);

        try {
            $employe = $this->tenantDb->findUser($tenant, $user);

            if (!$employe) {
                return $this->retour($tenant)->with('error', 'Cet employé est introuvable dans cet établissement.');
            }

            $pdo = $this->tenantDb->connect($tenant);

            // Un email en doublon empêcherait la connexion de l'employé.
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$validated['email'], $user]);
            if ($stmt->fetch()) {
                return $this->retour($tenant, $user)
                    ->with('error', 'Cette adresse est déjà utilisée par un autre employé de cet établissement.');
            }

            $pdo->beginTransaction();

            if (!empty($validated['password'])) {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ?, password = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([
                    $validated['name'], $validated['email'], $validated['phone'] ?? null,
                    Hash::make($validated['password']), $user,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$validated['name'], $validated['email'], $validated['phone'] ?? null, $user]);
            }

            // Rôle principal : colonne historique, encore lue par wetchah_app
            // pour les utilisateurs sans entrée dans le pivot.
            if (!empty($validated['role'])) {
                $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')
                    ->execute([$validated['role'], $user]);
            }

            $this->remplacerRoles($pdo, $user, $validated['roles'] ?? [], $validated['levels'] ?? []);

            $pdo->commit();
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->retour($tenant, $user)->with('error', $this->messageErreur($e));
        }

        AuditLog::record(Auth::id(), 'tenant_user_update',
            "Employé {$validated['name']} modifié dans {$tenant->name}", 'tenant_users');

        return $this->retour($tenant, $user)->with('success', "Les accès de {$validated['name']} ont été enregistrés.");
    }

    /**
     * Remplace les rôles de l'employé par ceux transmis, avec leur niveau.
     *
     * @param  array<int, int>     $roleIds
     * @param  array<int, string>  $levels   niveau par identifiant de rôle
     */
    private function remplacerRoles(PDO $pdo, int $userId, array $roleIds, array $levels): void
    {
        try {
            $pdo->prepare('DELETE FROM role_user WHERE user_id = ?')->execute([$userId]);

            if (empty($roleIds)) {
                return;
            }

            $insert = $pdo->prepare(
                'INSERT INTO role_user (user_id, role_id, level, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
            );

            foreach ($roleIds as $roleId) {
                // Défaut en écriture : c'est le comportement attendu quand on
                // coche un rôle sans préciser, et la lecture seule est le
                // choix explicite.
                $niveau = $levels[$roleId] ?? 'write';
                $insert->execute([$userId, (int) $roleId, $niveau]);
            }
        } catch (\PDOException $e) {
            // Établissement antérieur au multi-rôles : la colonne « role »
            // mise à jour plus haut reste sa seule source d'autorisation.
        }
    }

    private function compteManagers(Tenant $tenant): int
    {
        $stmt = $this->tenantDb->connect($tenant)
            ->query("SELECT COUNT(*) FROM users WHERE role = 'manager' AND is_active = true");

        return (int) $stmt->fetchColumn();
    }

    /** Message lisible : l'échec vient presque toujours du conteneur arrêté. */
    private function messageErreur(\Throwable $e): string
    {
        \Illuminate\Support\Facades\Log::warning('Écriture base tenant échouée : ' . $e->getMessage());

        return "Impossible de joindre la base de l'établissement. "
            . 'Vérifiez que ses conteneurs sont démarrés, puis réessayez.';
    }
}
