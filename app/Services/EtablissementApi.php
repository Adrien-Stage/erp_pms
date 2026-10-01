<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Canal d'orchestration vers l'application d'un établissement : matrice des
 * droits, comptes administrateurs, départements.
 *
 * Plus aucune écriture directe dans la base de l'établissement : c'est
 * l'application qui sait ce qu'un compte, un droit ou un département doit
 * porter, et elle seule peut valider et tracer.
 *
 * Jeton : le secret d'orchestration de l'établissement, que seule la console
 * détient. Une application antérieure à ce secret ne le connaît pas et refuse
 * la requête : elle est rejouée avec le secret de reporting, qui gardait
 * autrefois ce canal.
 */
class EtablissementApi
{
    public function __construct(private readonly TenantSecrets $secrets)
    {
    }

    /**
     * Nom du conteneur sur le réseau Docker : ni 127.0.0.1, qui désignerait
     * l'ERP, ni le port publié, qui n'existe que sur l'hôte.
     */
    public function baseUrl(Tenant $tenant): string
    {
        return 'http://' . ($tenant->docker_app_container ?: ('meka-erp-' . $tenant->slug . '-app'));
    }

    /** L'établissement peut-il être interrogé : provisionné, et un jeton connu ? */
    public function disponible(Tenant $tenant): bool
    {
        return $tenant->provisioned_at !== null && $this->jetons($tenant) !== [];
    }

    /**
     * Envoie une requête sur le canal d'orchestration.
     *
     * @throws \Illuminate\Http\Client\ConnectionException établissement injoignable
     */
    public function requete(Tenant $tenant, string $methode, string $chemin, array $donnees = [], int $delai = 8): ?Response
    {
        $reponse = null;

        foreach ($this->jetons($tenant) as $jeton) {
            $client = Http::withToken($jeton)->timeout($delai)->acceptJson();
            $url = $this->baseUrl($tenant) . $chemin;

            $reponse = match (strtoupper($methode)) {
                'GET'    => $client->get($url, $donnees),
                'POST'   => $client->post($url, $donnees),
                'PUT'    => $client->put($url, $donnees),
                'PATCH'  => $client->patch($url, $donnees),
                'DELETE' => $client->delete($url, $donnees),
            };

            if ($reponse->status() !== 401) {
                return $reponse;
            }
        }

        return $reponse;
    }

    /** @return list<string> jetons à essayer, dans l'ordre */
    private function jetons(Tenant $tenant): array
    {
        return array_values(array_unique(array_filter([
            $this->secrets->current($tenant, TenantSecrets::ORCHESTRATION),
            $this->secrets->current($tenant, TenantSecrets::REPORTING),
        ])));
    }
}
