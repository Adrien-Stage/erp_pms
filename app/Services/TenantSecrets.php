<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Secrets de service propres à chaque établissement.
 *
 * REPORTING_SECRET garde l'API de reporting, la matrice des droits et le
 * provisioning des comptes GRC ; ASSISTANCE_SECRET signe les jetons du mode
 * assistance. Longtemps, une seule valeur lue dans le .env de la console était
 * injectée dans tous les établissements et tous les GRC : qui lisait
 * l'environnement d'un seul conteneur pouvait lire les finances, réécrire les
 * droits et ouvrir une session d'administration dans tous les autres.
 *
 * Même logique que l'APP_KEY et la clé du GRC : la valeur vit dans le compose
 * de l'établissement, source de vérité de ce que ses conteneurs ont reçu. Elle
 * y est tirée au hasard à la première génération puis réutilisée à chaque
 * mise à jour — en changer à chaque fois couperait la console le temps du
 * redémarrage. Une valeur héritée égale au secret commun est remplacée à la
 * génération suivante : c'est ainsi qu'un établissement déjà en service quitte
 * le secret partagé, à sa prochaine mise à jour ou application des modules.
 */
class TenantSecrets
{
    public const REPORTING  = 'REPORTING_SECRET';
    public const ASSISTANCE = 'ASSISTANCE_SECRET';

    /**
     * Secret en vigueur chez l'établissement : celui que ses conteneurs ont
     * reçu. Chaîne vide si aucun n'est connu.
     */
    public function current(Tenant $tenant, string $cle): string
    {
        // Sans compose lisible, l'établissement n'a pu recevoir que le secret
        // commun : c'est le seul qui ait une chance d'être accepté.
        return $this->lireDansCompose($tenant->composePath(), $cle) ?? $this->secretCommun($cle);
    }

    /**
     * Valeur à inscrire dans le prochain compose de l'établissement.
     *
     * Reprend celle déjà figée, sauf si c'est le secret commun : celle-là
     * serait partagée avec les autres établissements, on en tire une neuve.
     */
    public function forCompose(string $composePath, string $cle): string
    {
        $existant = $this->lireDansCompose($composePath, $cle);
        $commun   = $this->secretCommun($cle);

        if ($existant !== null && $existant !== '' && ($commun === '' || !hash_equals($commun, $existant))) {
            return $existant;
        }

        return bin2hex(random_bytes(32));
    }

    private function lireDansCompose(string $composePath, string $cle): ?string
    {
        if (!is_file($composePath)) {
            return null;
        }

        $contenu = file_get_contents($composePath);

        if ($contenu === false || !preg_match('/^\s*' . preg_quote($cle, '/') . ':\s*"([^"]*)"/m', $contenu, $m)) {
            return null;
        }

        return $m[1];
    }

    private function secretCommun(string $cle): string
    {
        return (string) match ($cle) {
            self::REPORTING  => config('provisioning.reporting_secret'),
            self::ASSISTANCE => config('assistance.secret'),
        };
    }
}
