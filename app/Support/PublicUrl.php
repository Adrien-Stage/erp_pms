<?php

namespace App\Support;

/**
 * Adresse sous laquelle un navigateur atteint les établissements.
 *
 * La console tourne dans un conteneur, mais les ports des établissements sont
 * publiés sur l'hôte : ce que voit un navigateur n'est donc pas forcément
 * « localhost ». Sur un serveur distant, c'est le nom ou l'adresse du serveur
 * (TENANTS_PUBLIC_HOST) — sans quoi chaque lien « Ouvrir » pointe vers le poste
 * de l'administrateur, et l'APP_URL d'un établissement vers une adresse que ni
 * le site vitrine ni les assistants ne peuvent joindre.
 */
final class PublicUrl
{
    /** Nom ou adresse publique du serveur ; « localhost » en développement. */
    public static function host(): string
    {
        $hote = trim((string) config('provisioning.public_host', 'localhost'));

        return $hote !== '' ? $hote : 'localhost';
    }

    /** « http://serveur », sans port. */
    public static function origin(): string
    {
        return 'http://' . self::host();
    }

    /** « http://serveur:8081 », ou null sans port. */
    public static function forPort(int|string|null $port): ?string
    {
        return $port ? self::origin() . ':' . $port : null;
    }
}
