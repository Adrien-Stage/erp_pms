<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Matrice des droits d'un établissement, lue et écrite depuis la console.
 *
 * Le catalogue des droits vit dans le code de l'application, où il suit les
 * routes : la console ne peut pas le connaître, elle le demande. Elle ne
 * renvoie ensuite que les écarts — jamais la matrice entière, qui se
 * périmerait au premier droit ajouté à l'application.
 *
 * L'écriture passe par la même API plutôt que d'écrire directement dans la
 * base de l'établissement : c'est l'application qui sait quels droits
 * existent, et elle refuse ceux qu'aucune route n'applique.
 */
class PermissionMatrixClient
{
    private function baseUrl(Tenant $tenant): string
    {
        $container = $tenant->docker_app_container ?: ('meka-erp-' . $tenant->slug . '-app');

        // Nom du conteneur sur le réseau Docker : ni 127.0.0.1, qui désignerait
        // l'ERP, ni le port publié, qui n'existe que sur l'hôte.
        return 'http://' . $container;
    }

    private function secret(): string
    {
        return (string) config('provisioning.reporting_secret');
    }

    /**
     * Gabarit, écarts en vigueur, rôles et incompatibilités.
     *
     * @return array{catalogue: array, modules: array, roles: array, ecarts: array, incompatibilites: array}|null
     *         null si l'établissement n'est pas joignable ou pas provisionné.
     */
    public function fetch(Tenant $tenant): ?array
    {
        if (!$tenant->provisioned_at || $this->secret() === '') {
            return null;
        }

        try {
            $reponse = Http::withToken($this->secret())->timeout(5)->acceptJson()
                ->get($this->baseUrl($tenant) . '/api/permissions/matrice');

            return $reponse->successful() ? (array) $reponse->json() : null;
        } catch (\Throwable $e) {
            Log::info("[Matrice] {$tenant->slug} injoignable : " . $e->getMessage());

            return null;
        }
    }

    /**
     * Remplace les écarts portés par les rôles.
     *
     * @param  list<array{role: string, permission: string, effect: string, reason?: ?string}>  $ecarts
     * @return array{ok: bool, message: string}
     */
    public function push(Tenant $tenant, array $ecarts): array
    {
        if ($this->secret() === '') {
            return ['ok' => false, 'message' => "Le secret de service n'est pas configuré : la matrice n'a pas été transmise."];
        }

        try {
            $reponse = Http::withToken($this->secret())->timeout(8)->acceptJson()
                ->put($this->baseUrl($tenant) . '/api/permissions/matrice', ['ecarts' => array_values($ecarts)]);

            if ($reponse->successful()) {
                return ['ok' => true, 'message' => $reponse->json('appliques', 0) . ' règle(s) appliquée(s).'];
            }

            // 422 : l'application a refusé des droits qu'elle ne connaît pas.
            $inconnus = (array) $reponse->json('inconnus', []);
            if ($inconnus !== []) {
                return ['ok' => false, 'message' => 'Droits inconnus de cet établissement : ' . implode(', ', $inconnus)];
            }

            Log::warning("[Matrice] {$tenant->slug} refus " . $reponse->status() . ' ' . $reponse->body());

            return ['ok' => false, 'message' => "L'établissement a refusé la mise à jour (" . $reponse->status() . ').'];
        } catch (\Throwable $e) {
            Log::warning("[Matrice] {$tenant->slug} injoignable : " . $e->getMessage());

            return ['ok' => false, 'message' => "L'établissement est injoignable : la matrice n'a pas été transmise."];
        }
    }
}
