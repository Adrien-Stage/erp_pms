<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Répertoire officiel des Services de l'infrastructure WeTchah.
 *
 * Un Service est une unité applicative / conteneurisé indépendante
 * déployée pour un établissement (PMS, Portail Web, Plateforme GRC).
 * Chaque service porte un ou plusieurs modules métier.
 */
class ServiceCatalog
{
    public const SERVICE_APP  = 'app';
    public const SERVICE_SITE = 'site';
    public const SERVICE_GRC  = 'grc';

    public static function all(): array
    {
        return [
            self::SERVICE_APP => [
                'slug'              => self::SERVICE_APP,
                'label'             => 'Application PMS (Cœur d\'exploitation)',
                'tagline'           => 'Gestion hôtelière opérationnelle : chambres, réservations, restauration, housekeeping, comptabilité et caisse.',
                'icon'              => 'server',
                'accent'            => 'indigo',
                'is_core'           => true,
                'requires_api'      => false,
                'container_key'     => 'docker_app_container',
                'port_key'          => 'app_port',
                'default_port'      => 8081,
                'db_container_key'  => 'docker_db_container',
                'db_port_key'       => 'db_port',
                'modules'           => [
                    'hebergement', 'reservations', 'clients', 'housekeeping',
                    'restaurant', 'portail', 'shop', 'economat',
                    'comptabilite', 'ledger', 'analytics', 'utilisateurs',
                    'parametres', 'pwa', 'discussions', 'api', 'ai',
                ],
            ],

            self::SERVICE_SITE => [
                'slug'              => self::SERVICE_SITE,
                'label'             => 'Site Web Vitrine & Réservation Web',
                'tagline'           => 'Portail public responsive (SvelteKit) : vitrine de l\'hôtel, catalogue des chambres et réservation directe.',
                'icon'              => 'globe',
                'accent'            => 'rose',
                'is_core'           => false,
                'requires_api'      => true,
                'container_key'     => 'docker_web_container',
                'port_key'          => 'web_port',
                'default_offset'    => 1000, // app_port + 1000
                'modules'           => [
                    'website_public', 'website_booking', 'website_cms',
                ],
            ],

            self::SERVICE_GRC => [
                'slug'              => self::SERVICE_GRC,
                'label'             => 'Plateforme Contrôle de Gestion & GRC',
                'tagline'           => 'Gouvernance, Risques & Conformité (FastAPI + SvelteKit) : cartographie 5x5, audits internes et flux PMS.',
                'icon'              => 'shield-check',
                'accent'            => 'teal',
                'is_core'           => false,
                'requires_api'      => true,
                'container_key'     => 'docker_grc_container',
                'port_key'          => 'grc_port',
                'default_offset'    => 2000, // app_port + 2000
                'modules'           => [
                    'grc_risks', 'grc_compliance', 'grc_audit', 'grc_policies',
                    'grc_incidents', 'grc_evaluations', 'grc_third_parties',
                    'grc_reporting', 'grc_audit_logs',
                ],
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /**
     * Services indispensables (cœur), toujours présents.
     */
    public static function core(): array
    {
        return array_filter(self::all(), fn ($s) => $s['is_core']);
    }

    /**
     * Services optionnels activables par établissement.
     */
    public static function optional(): array
    {
        return array_filter(self::all(), fn ($s) => !$s['is_core']);
    }

    /**
     * Vérifie si un service est activé pour un établissement donné.
     */
    public static function isEnabled(Tenant $tenant, string $serviceKey): bool
    {
        return match ($serviceKey) {
            self::SERVICE_APP  => true, // Le PMS est toujours actif
            self::SERVICE_SITE => (bool) ($tenant->website_enabled || in_array('website', $tenant->modules ?? [], true)),
            self::SERVICE_GRC  => $tenant->hasGrc(),
            default            => false,
        };
    }

    /**
     * Retourne le port résolu d'un service pour un établissement.
     */
    public static function resolvedPort(Tenant $tenant, string $serviceKey): ?int
    {
        return match ($serviceKey) {
            self::SERVICE_APP  => $tenant->app_port ? (int) $tenant->app_port : null,
            self::SERVICE_SITE => $tenant->web_port ? (int) $tenant->web_port : ($tenant->app_port ? (int) $tenant->app_port + 1000 : null),
            self::SERVICE_GRC  => $tenant->resolvedGrcPort(),
            default            => null,
        };
    }

    /**
     * URL complète d'accès au service.
     */
    public static function serviceUrl(Tenant $tenant, string $serviceKey): ?string
    {
        $port = self::resolvedPort($tenant, $serviceKey);
        return $port ? "http://localhost:{$port}" : null;
    }
}
