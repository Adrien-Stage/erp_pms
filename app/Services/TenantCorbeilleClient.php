<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Corbeille d'un établissement, lue et traitée par son API d'orchestration.
 *
 * L'application garde ce qu'un bouton « Supprimer » a effacé : la fiche, ce
 * qui est parti avec elle, ses liens, ses fichiers. Elle seule sait les
 * remettre en place : la console liste, demande la restauration ou la purge,
 * et transmet le nom de l'administrateur TECH qui agit.
 */
class TenantCorbeilleClient
{
    public function __construct(private readonly EtablissementApi $api)
    {
    }

    /**
     * Une page de la corbeille, avec les valeurs de filtre existantes.
     *
     * @return array{ok: bool, message?: string, entrees?: list<array>, pagination?: array, filtres?: array}
     */
    public function lister(Tenant $tenant, array $filtres): array
    {
        $resultat = $this->envoyer($tenant, 'GET', '/api/corbeille', array_filter($filtres, fn ($v) => $v !== null && $v !== ''));

        if (! $resultat['ok']) {
            return $resultat;
        }

        return ['ok' => true] + (array) $resultat['reponse']->json();
    }

    /** @return array{ok: bool, message: string} */
    public function restaurer(Tenant $tenant, int $entree, string $auteur): array
    {
        $resultat = $this->envoyer($tenant, 'POST', "/api/corbeille/{$entree}/restaurer", ['auteur' => $auteur]);

        return ['ok' => $resultat['ok'], 'message' => $resultat['message'] ?? ''];
    }

    /** @return array{ok: bool, message: string} */
    public function purger(Tenant $tenant, int $entree, string $auteur): array
    {
        $resultat = $this->envoyer($tenant, 'DELETE', "/api/corbeille/{$entree}", ['auteur' => $auteur]);

        return ['ok' => $resultat['ok'], 'message' => $resultat['message'] ?? ''];
    }

    /** @return array{ok: bool, message?: string, reponse?: Response} */
    private function envoyer(Tenant $tenant, string $methode, string $chemin, array $donnees): array
    {
        if (! $this->api->disponible($tenant)) {
            return ['ok' => false, 'message' => "L'établissement n'est pas encore provisionné, ou son secret de service est inconnu."];
        }

        try {
            $reponse = $this->api->requete($tenant, $methode, $chemin, $donnees, 15);
        } catch (\Throwable $e) {
            Log::warning("[Corbeille] {$tenant->slug} injoignable : " . $e->getMessage());

            return ['ok' => false, 'message' => "L'établissement est injoignable. Vérifiez que ses conteneurs sont démarrés, puis réessayez."];
        }

        if ($reponse?->successful()) {
            return ['ok' => true, 'reponse' => $reponse];
        }

        return ['ok' => false, 'message' => $this->refus($tenant, $reponse)];
    }

    private function refus(Tenant $tenant, ?Response $reponse): string
    {
        return match (true) {
            $reponse === null => "Le secret de service de l'établissement est inconnu.",
            // Application antérieure à la corbeille : la route n'existe pas chez elle.
            $reponse->status() === 404 => "L'établissement n'a pas encore la corbeille : mettez-le à jour, puis réessayez.",
            $reponse->status() === 503 => "Le canal d'administration de l'établissement est fermé : appliquez ses modules pour lui remettre son secret, puis réessayez.",
            $reponse->status() === 401 => "L'établissement a refusé le jeton de la console : appliquez ses modules pour resynchroniser ses secrets.",
            // Le motif d'une restauration refusée, tel que l'application le donne.
            $reponse->status() === 422 => (string) ($reponse->json('message') ?: 'Opération refusée par l\'établissement.'),
            default => tap("L'établissement a refusé l'opération ({$reponse->status()}).",
                fn () => Log::warning("[Corbeille] {$tenant->slug} refus {$reponse->status()} {$reponse->body()}")),
        };
    }
}
