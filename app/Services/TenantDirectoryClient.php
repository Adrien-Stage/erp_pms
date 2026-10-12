<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Comptes administrateurs et départements d'un établissement, tenus par son
 * API d'orchestration.
 *
 * La console n'écrit plus dans la base de l'établissement. Elle ne crée plus
 * que les comptes administrateurs — le service informatique de l'hôtel, qui
 * crée ensuite tous les autres dans l'application.
 */
class TenantDirectoryClient
{
    public function __construct(private readonly EtablissementApi $api)
    {
    }

    /** @return array{ok: bool, message: string, id?: int} */
    public function creerAdministrateur(Tenant $tenant, array $donnees): array
    {
        return $this->envoyer($tenant, 'POST', '/api/comptes/administrateurs', $donnees);
    }

    /** @return array{ok: bool, message: string} */
    public function modifierAdministrateur(Tenant $tenant, int $compte, array $donnees): array
    {
        return $this->envoyer($tenant, 'PATCH', "/api/comptes/administrateurs/{$compte}", $donnees);
    }

    /** @return array{ok: bool, message: string, id?: int} */
    public function creerDepartement(Tenant $tenant, array $donnees): array
    {
        return $this->envoyer($tenant, 'POST', '/api/departements', $donnees);
    }

    /** @return array{ok: bool, message: string} */
    public function modifierDepartement(Tenant $tenant, int $departement, array $donnees): array
    {
        return $this->envoyer($tenant, 'PUT', "/api/departements/{$departement}", $donnees);
    }

    /** @return array{ok: bool, message: string} */
    public function supprimerDepartement(Tenant $tenant, int $departement, ?string $auteur = null): array
    {
        return $this->envoyer($tenant, 'DELETE', "/api/departements/{$departement}", array_filter(['auteur' => $auteur]));
    }

    /** @return array{ok: bool, message: string, id?: int} */
    private function envoyer(Tenant $tenant, string $methode, string $chemin, array $donnees): array
    {
        if (!$this->api->disponible($tenant)) {
            return ['ok' => false, 'message' => "L'établissement n'est pas encore provisionné, ou son secret de service est inconnu."];
        }

        try {
            $reponse = $this->api->requete($tenant, $methode, $chemin, $donnees);
        } catch (\Throwable $e) {
            Log::warning("[Orchestration] {$tenant->slug} injoignable : " . $e->getMessage());

            return ['ok' => false, 'message' => "L'établissement est injoignable. Vérifiez que ses conteneurs sont démarrés, puis réessayez."];
        }

        if ($reponse?->successful()) {
            return ['ok' => true, 'message' => '', 'id' => (int) $reponse->json('id', 0)];
        }

        return ['ok' => false, 'message' => $this->refus($tenant, $reponse)];
    }

    private function refus(Tenant $tenant, ?Response $reponse): string
    {
        return match (true) {
            $reponse === null => "Le secret de service de l'établissement est inconnu.",
            // Application antérieure : la route n'existe pas encore chez elle.
            $reponse->status() === 404 => "L'établissement n'a pas encore cette fonction : mettez-le à jour, puis réessayez.",
            $reponse->status() === 503 => "Le canal d'administration de l'établissement est fermé : appliquez ses modules pour lui remettre son secret, puis réessayez.",
            $reponse->status() === 401 => "L'établissement a refusé le jeton de la console : appliquez ses modules pour resynchroniser ses secrets.",
            $reponse->status() === 422 => $this->premiereErreur($reponse),
            default => tap("L'établissement a refusé l'opération ({$reponse->status()}).",
                fn () => Log::warning("[Orchestration] {$tenant->slug} refus {$reponse->status()} {$reponse->body()}")),
        };
    }

    private function premiereErreur(Response $reponse): string
    {
        foreach ((array) $reponse->json('errors', []) as $messages) {
            if (is_array($messages) && $messages !== []) {
                return (string) $messages[0];
            }
        }

        return (string) ($reponse->json('message') ?: 'Données refusées par l\'établissement.');
    }
}
