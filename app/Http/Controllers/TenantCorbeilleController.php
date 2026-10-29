<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantCorbeilleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Corbeille d'un établissement : ce que ses employés ont effacé par un bouton
 * « Supprimer ». Réservée à l'administrateur TECH de la console — personne
 * dans l'hôtel ne la voit, le propriétaire non plus. Il la filtre, restaure
 * une entrée ou la purge pour de bon.
 */
class TenantCorbeilleController extends Controller
{
    /** Filtres transmis tels quels à l'établissement. */
    private const FILTRES = ['type', 'module', 'auteur', 'du', 'au', 'q', 'etat', 'page'];

    public function __construct(private readonly TenantCorbeilleClient $client)
    {
    }

    private function exigerTech(): void
    {
        abort_unless(Auth::user()?->isTechAdmin(), 403, "La corbeille est réservée à l'administrateur TECH.");
    }

    private function auteur(): string
    {
        return Auth::user()->name . ' <' . Auth::user()->email . '>';
    }

    public function show(Request $request, Tenant $tenant): View
    {
        $this->exigerTech();

        $filtres = $request->validate([
            'type'   => ['nullable', 'string', 'max:80'],
            'module' => ['nullable', 'string', 'max:40'],
            'auteur' => ['nullable', 'string', 'max:120'],
            'du'     => ['nullable', 'date'],
            'au'     => ['nullable', 'date'],
            'q'      => ['nullable', 'string', 'max:120'],
            'etat'   => ['nullable', 'in:dans,restauree,tout'],
            'page'   => ['nullable', 'integer', 'min:1'],
        ]);

        return view('establishments.corbeille', [
            'tenant'   => $tenant,
            'filtres'  => array_intersect_key($filtres, array_flip(self::FILTRES)) + ['etat' => 'dans'],
            'resultat' => $this->client->lister($tenant, $filtres + ['etat' => 'dans']),
        ]);
    }

    public function restore(Request $request, Tenant $tenant, int $entree): RedirectResponse
    {
        $this->exigerTech();
        $libelle = (string) $request->input('libelle', "#{$entree}");

        $resultat = $this->client->restaurer($tenant, $entree, $this->auteur());

        AuditLog::record(Auth::id(), 'tenant_corbeille_restore',
            "Corbeille de {$tenant->name} : « {$libelle} » restauré" . ($resultat['ok'] ? '' : ' — refusé : ' . $resultat['message']),
            'tenant_corbeille', ['tenant_id' => $tenant->id, 'entree' => $entree]);

        return $this->retour($request, $tenant)->with(
            $resultat['ok'] ? 'success' : 'error',
            $resultat['ok'] ? "« {$libelle} » est restauré dans l'établissement." : "Restauration refusée : {$resultat['message']}"
        );
    }

    public function purge(Request $request, Tenant $tenant, int $entree): RedirectResponse
    {
        $this->exigerTech();
        $libelle = (string) $request->input('libelle', "#{$entree}");

        $resultat = $this->client->purger($tenant, $entree, $this->auteur());

        AuditLog::record(Auth::id(), 'tenant_corbeille_purge',
            "Corbeille de {$tenant->name} : « {$libelle} » purgé définitivement" . ($resultat['ok'] ? '' : ' — échec : ' . $resultat['message']),
            'tenant_corbeille', ['tenant_id' => $tenant->id, 'entree' => $entree]);

        return $this->retour($request, $tenant)->with(
            $resultat['ok'] ? 'success' : 'error',
            $resultat['ok'] ? "« {$libelle} » est effacé pour de bon." : "Purge impossible : {$resultat['message']}"
        );
    }

    /** Retour à la même vue de la corbeille : mêmes filtres, même page. */
    private function retour(Request $request, Tenant $tenant): RedirectResponse
    {
        $filtres = array_filter((array) $request->input('filtres', []), fn ($v) => $v !== null && $v !== '');

        return redirect()->route('tech.establishments.corbeille', ['tenant' => $tenant] + array_intersect_key($filtres, array_flip(self::FILTRES)));
    }
}
