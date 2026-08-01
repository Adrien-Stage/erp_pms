<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Espace de l'éditeur de contenu.
 *
 * Un éditeur ne gère que le contenu marketing du site de l'établissement
 * auquel il est rattaché. Il n'a accès à aucun autre établissement, ni à quoi
 * que ce soit d'autre dans l'ERP.
 *
 * L'espace a sa propre adresse et sa propre page de connexion, sans marquage
 * ERP. Cela réduit la découverte fortuite de la console d'administration, mais
 * ce n'est pas ce qui protège : la protection réelle vient du cloisonnement
 * appliqué ici et du refus, à la connexion, de tout compte qui n'est pas un
 * éditeur.
 */
class SiteEditorController extends Controller
{
    /** Formulaire de connexion. Ne mentionne ni l'ERP ni l'administration. */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->isSiteEditor()) {
            return redirect()->route('site-editor.content');
        }

        return view('editor.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Un même message pour tous les échecs : identifiant inconnu, mot de
        // passe faux, compte désactivé ou compte d'un autre rôle. Distinguer
        // ces cas révélerait quels comptes existent, et surtout que cette
        // adresse sert aussi à autre chose.
        $echec = fn () => back()
            ->withInput(['email' => $credentials['email']])
            ->withErrors(['email' => 'Identifiants incorrects.']);

        if (!$user || !$user->isSiteEditor() || !$user->is_active) {
            return $echec();
        }

        if (!Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            return $echec();
        }

        // Rattachement manquant : le compte n'a pas de site à éditer.
        if (!Auth::user()->tenant_id) {
            Auth::logout();
            $request->session()->invalidate();

            return back()->withErrors(['email' => "Aucun site n'est rattaché à ce compte. Contactez votre gestionnaire."]);
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        AuditLog::record($user->id, 'site_editor_login',
            "Connexion de l'éditeur {$user->name}", 'site_editor');

        return redirect()->intended(route('site-editor.content'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('site-editor.login');
    }

    /** Éditeur du contenu, borné à l'établissement rattaché au compte. */
    public function content(): View
    {
        $tenant = $this->tenantRattache();

        return view('editor.content', compact('tenant'));
    }

    /**
     * Enregistrement : on réutilise la logique de l'ERP plutôt que d'en
     * maintenir une seconde, mais l'établissement vient du compte connecté,
     * jamais de la requête — l'éditeur ne peut donc pas viser un autre site.
     */
    public function update(Request $request, AdminAuditController $admin): RedirectResponse
    {
        $tenant = $this->tenantRattache();

        $admin->updateSiteContent($request, $tenant);

        AuditLog::record(Auth::id(), 'site_content_update',
            "Contenu du site de {$tenant->name} modifié par l'éditeur", 'site_editor');

        return redirect()->route('site-editor.content')
            ->with('success', 'Le contenu du site a été enregistré.');
    }

    /**
     * Établissement du compte connecté.
     *
     * Le middleware garantit déjà le rôle ; ce garde-fou couvre le cas d'un
     * rattachement retiré pendant la session.
     */
    private function tenantRattache(): \App\Models\Tenant
    {
        $tenant = Auth::user()?->tenant;

        abort_unless($tenant, 403, "Aucun site n'est rattaché à votre compte.");

        return $tenant;
    }

    // ── Administration des éditeurs (côté ERP) ───────────────────────────────

    /** Seuls l'administrateur technique et le propriétaire créent des éditeurs. */
    private function autoriseGestion(\App\Models\Tenant $tenant): void
    {
        $user = Auth::user();

        abort_unless($user, 401);
        abort_unless($user->isTechAdmin() || $tenant->owner_id === $user->id, 403);
    }

    private function retourSite(\App\Models\Tenant $tenant): RedirectResponse
    {
        return redirect()->route('tech.establishments.show', [
            'tenant' => $tenant, 'section' => 'site-content',
        ]);
    }

    /** Création d'un compte éditeur rattaché à cet établissement. */
    public function storeEditor(Request $request, \App\Models\Tenant $tenant): RedirectResponse
    {
        $this->autoriseGestion($tenant);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'email.unique' => 'Cette adresse est déjà utilisée par un compte de la plateforme.',
        ]);

        $editeur = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role'      => User::ROLE_SITE_EDITOR,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        AuditLog::record(Auth::id(), 'site_editor_create',
            "Éditeur {$editeur->name} créé pour {$tenant->name}", 'site_editor');

        return $this->retourSite($tenant)->with('success',
            "L'éditeur {$editeur->name} a été créé. Il se connecte sur " . route('site-editor.login') . '.');
    }

    public function toggleEditor(\App\Models\Tenant $tenant, User $editor): RedirectResponse
    {
        $this->autoriseGestion($tenant);
        $this->verifieRattachement($tenant, $editor);

        $editor->update(['is_active' => !$editor->is_active]);
        $etat = $editor->is_active ? 'activé' : 'désactivé';

        AuditLog::record(Auth::id(), 'site_editor_toggle',
            "Éditeur {$editor->name} {$etat}", 'site_editor');

        return $this->retourSite($tenant)->with('success', "Le compte de {$editor->name} a été {$etat}.");
    }

    public function destroyEditor(\App\Models\Tenant $tenant, User $editor): RedirectResponse
    {
        $this->autoriseGestion($tenant);
        $this->verifieRattachement($tenant, $editor);

        $nom = $editor->name;
        $editor->delete();

        AuditLog::record(Auth::id(), 'site_editor_delete', "Éditeur {$nom} supprimé", 'site_editor');

        return $this->retourSite($tenant)->with('success', "Le compte de {$nom} a été supprimé.");
    }

    /**
     * Le compte visé doit être un éditeur DE CET établissement : sans cette
     * vérification, l'URL permettrait d'agir sur un compte d'un autre site,
     * voire sur un administrateur.
     */
    private function verifieRattachement(\App\Models\Tenant $tenant, User $editor): void
    {
        abort_unless($editor->isSiteEditor() && $editor->tenant_id === $tenant->id, 404);
    }
}
