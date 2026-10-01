<?php

namespace App\Services;

use App\Models\Tenant;
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
    public function __construct(private readonly EtablissementApi $api)
    {
    }

    /**
     * Gabarit, couches, personnel et règles de cumul.
     *
     * Une application antérieure à la version 2 ne renvoie que le gabarit,
     * les écarts, les rôles et les incompatibilités : la console lui garde
     * l'ancien écran.
     *
     * @return array<string, mixed>|null null si l'établissement n'est pas joignable ou pas provisionné.
     */
    public function fetch(Tenant $tenant): ?array
    {
        if (!$this->api->disponible($tenant)) {
            return null;
        }

        try {
            $reponse = $this->api->requete($tenant, 'GET', '/api/permissions/matrice', delai: 8);

            return $reponse?->successful() ? (array) $reponse->json() : null;
        } catch (\Throwable $e) {
            Log::info("[Matrice] {$tenant->slug} injoignable : " . $e->getMessage());

            return null;
        }
    }

    /**
     * Ce que changerait un lot : qui gagne ou perd quel droit, quels cumuls
     * il ouvre. Rien n'est enregistré.
     *
     * @param  list<array<string, mixed>>  $ecarts
     * @return array{ok: bool, message: string, apercu?: array<string, mixed>}
     */
    public function preview(Tenant $tenant, array $ecarts): array
    {
        try {
            $reponse = $this->api->requete($tenant, 'POST', '/api/permissions/matrice/apercu', ['ecarts' => array_values($ecarts)], 20);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => "L'établissement est injoignable : l'aperçu n'a pas pu être calculé."];
        }

        if ($reponse?->successful()) {
            return ['ok' => true, 'message' => '', 'apercu' => (array) $reponse->json()];
        }

        return ['ok' => false, 'message' => $this->refus($tenant, $reponse)];
    }

    /**
     * Remplace les écarts que la console porte sur les rôles.
     *
     * @param  list<array{role: string, permission: string, effect: string, scope?: ?string, reason?: ?string}>  $ecarts
     * @param  array{derogation?: bool, empreinte?: ?string, auteur?: ?string}  $options
     * @return array{ok: bool, message: string, conflit?: bool, cumuls?: array, empreinte?: string}
     */
    public function push(Tenant $tenant, array $ecarts, array $options = []): array
    {
        if (!$this->api->disponible($tenant)) {
            return ['ok' => false, 'message' => "Le secret de service n'est pas configuré : la matrice n'a pas été transmise."];
        }

        $corps = ['ecarts' => array_values($ecarts)] + array_filter([
            'derogation' => $options['derogation'] ?? null,
            'empreinte'  => $options['empreinte'] ?? null,
            'auteur'     => $options['auteur'] ?? null,
        ], static fn ($v) => $v !== null);

        try {
            $reponse = $this->api->requete($tenant, 'PUT', '/api/permissions/matrice', $corps);
        } catch (\Throwable $e) {
            Log::warning("[Matrice] {$tenant->slug} injoignable : " . $e->getMessage());

            return ['ok' => false, 'message' => "L'établissement est injoignable : la matrice n'a pas été transmise."];
        }

        if ($reponse?->successful()) {
            return [
                'ok'        => true,
                'message'   => $reponse->json('appliques', 0) . ' règle(s) appliquée(s).',
                'cumuls'    => (array) $reponse->json('cumuls', []),
                'empreinte' => (string) $reponse->json('empreinte', ''),
            ];
        }

        if ($reponse?->status() === 409) {
            return [
                'ok'      => false,
                'conflit' => true,
                'message' => "La matrice a été modifiée par quelqu'un d'autre depuis l'ouverture de l'écran : rien n'a été enregistré. Rechargez-la, puis refaites vos changements.",
            ];
        }

        return [
            'ok'      => false,
            'message' => $this->refus($tenant, $reponse),
            'cumuls'  => (array) ($reponse?->json('cumuls') ?? []),
        ];
    }

    /** Message lisible pour un refus de l'établissement. */
    private function refus(Tenant $tenant, ?\Illuminate\Http\Client\Response $reponse): string
    {
        if ($reponse === null) {
            return "Le secret de service n'est pas configuré.";
        }

        // 422 : l'application a refusé des droits qu'elle ne connaît pas.
        $inconnus = (array) $reponse->json('inconnus', []);
        if ($inconnus !== []) {
            return 'Droits inconnus de cet établissement : ' . implode(', ', $inconnus);
        }

        $cumuls = (array) $reponse->json('cumuls', []);
        if ($cumuls !== []) {
            return (string) $reponse->json('message') . ' ' . implode(' ; ', array_map(
                static fn (array $c): string => ($c['role'] ?? '?') . ' reçoit ' . ($c['permission'] ?? '?') . ' — ' . ($c['motif'] ?? ''),
                $cumuls
            ));
        }

        $roles = (array) $reponse->json('roles', []);
        if ($roles !== []) {
            return 'Rôles que la console ne règle pas : ' . implode(', ', $roles);
        }

        Log::warning("[Matrice] {$tenant->slug} refus " . $reponse->status() . ' ' . $reponse->body());

        return "L'établissement a refusé la mise à jour (" . $reponse->status() . ').';
    }
}
