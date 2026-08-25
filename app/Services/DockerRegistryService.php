<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client minimal pour l'API de distribution OCI/Docker d'un registre (GHCR).
 * Utilise le flux de token anonyme, valable pour les packages publics
 * (aucune authentification nécessaire côté serveur — cf. décision "package
 * GHCR public" de la Phase A).
 */
class DockerRegistryService
{
    private const MANIFEST_ACCEPT = 'application/vnd.oci.image.index.v1+json, '
        . 'application/vnd.docker.distribution.manifest.list.v2+json, '
        . 'application/vnd.docker.distribution.manifest.v2+json';

    /**
     * Retire le nom d'hôte du registre (ex: "ghcr.io/adrien-stage/villa_b"
     * -> "adrien-stage/villa_b") pour construire les chemins d'API.
     */
    public function imagePath(string $registryImage): string
    {
        return preg_replace('#^[a-z0-9.-]+/#i', '', $registryImage);
    }

    /**
     * Liste les tags publiés pour une image (ex: "adrien-stage/villa_b").
     */
    public function listTags(string $imagePath): array
    {
        $token = $this->getAnonymousToken($imagePath, 'ghcr.io');

        $response = $this->http()
            ->withToken($token)
            ->get("https://ghcr.io/v2/{$imagePath}/tags/list");

        return $response->successful() ? (array) $response->json('tags', []) : [];
    }

    /**
     * Résout le digest exact (sha256:...) pointé par un tag donné.
     */
    public function resolveDigest(string $imagePath, string $tag): ?string
    {
        $token = $this->getAnonymousToken($imagePath, 'ghcr.io');

        $response = $this->http()
            ->withToken($token)
            ->withHeaders(['Accept' => self::MANIFEST_ACCEPT])
            ->get("https://ghcr.io/v2/{$imagePath}/manifests/{$tag}");

        if (!$response->successful()) {
            return null;
        }

        return $response->header('docker-content-digest');
    }

    private function getAnonymousToken(string $imagePath, string $registryHost): string
    {
        $response = $this->http()->get("https://{$registryHost}/token", [
            'scope' => "repository:{$imagePath}:pull",
        ]);

        return (string) $response->json('token', '');
    }

    /**
     * Client HTTP borné : sans délai d'expiration, un appel au registre peut se
     * figer indéfiniment sur une connexion instable. On limite l'établissement
     * de la connexion et la durée totale, et on retente les erreurs réseau
     * transitoires avant d'abandonner.
     */
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::connectTimeout(10)
            ->timeout(30)
            ->retry(3, 1500, throw: false);
    }
}
