<?php

namespace App\Support;

/**
 * Catalogue des rôles opérationnels de l'application établissement
 * (meka_template) — miroir consultatif des constantes ROLE_* et des
 * middlewares "role:" du template. Sert l'onglet Rôles du dashboard TECH :
 * pms ne crée pas ces rôles, il les documente et compte leur répartition
 * dans la base de chaque établissement.
 *
 * 'module' : périmètre principal du rôle — 'core' = modules cœur toujours
 * actifs (réservations, chambres, clients), sinon la clé du module optionnel
 * correspondant (restaurant, shop, housekeeping, accounting).
 */
class TenantRoles
{
    public static function catalog(): array
    {
        return [
            'admin' => [
                'label' => 'Administrateur',
                'description' => 'Accès complet à l\'application de l\'établissement, y compris les paramètres.',
                'module' => 'core',
            ],
            'manager' => [
                'label' => 'Manager (directeur)',
                'description' => 'Pilotage complet de l\'établissement : réservations, équipes, modules métier, analytics.',
                'module' => 'core',
            ],
            'reception' => [
                'label' => 'Réception',
                'description' => 'Arrivées/départs, réservations, clients et encaissements du front-desk.',
                'module' => 'core',
            ],
            'cashier' => [
                'label' => 'Caissier',
                'description' => 'Encaissements et clôtures de caisse (hôtel et restaurant).',
                'module' => 'core',
            ],
            'accountant' => [
                'label' => 'Comptable',
                'description' => 'Journal des dépenses, écritures et rapports de trésorerie.',
                'module' => 'accounting',
            ],
            'housekeeping_leader' => [
                'label' => 'Gouvernant(e)',
                'description' => 'Planification du nettoyage, affectation des équipes d\'étage.',
                'module' => 'housekeeping',
            ],
            'housekeeping_staff' => [
                'label' => 'Personnel d\'étage',
                'description' => 'Exécution et clôture des tâches de nettoyage des chambres.',
                'module' => 'housekeeping',
            ],
            'housekeeping' => [
                'label' => 'Housekeeping (legacy)',
                'description' => 'Ancien rôle générique housekeeping — remplacé par gouvernant(e) / personnel d\'étage.',
                'module' => 'housekeeping',
            ],
            'restaurant_chief' => [
                'label' => 'Chef restaurant',
                'description' => 'Carte, commandes, facturation et garde-manger du restaurant.',
                'module' => 'restaurant',
            ],
            'restaurant_staff' => [
                'label' => 'Personnel restaurant',
                'description' => 'Prise et suivi des commandes en salle.',
                'module' => 'restaurant',
            ],
            'shop_manager' => [
                'label' => 'Responsable boutique',
                'description' => 'Catalogue, stocks et point de vente de la boutique.',
                'module' => 'shop',
            ],
            'shop_cashier' => [
                'label' => 'Caissier boutique',
                'description' => 'Ventes et encaissements de la boutique.',
                'module' => 'shop',
            ],
        ];
    }

    /**
     * Matrice consultative rôle -> modules accessibles, déduite des
     * middlewares "role:" de meka_template. Colonnes affichées dans
     * l'onglet Rôles ('core' = Hôtel & réception, toujours actif).
     */
    public static function moduleColumns(): array
    {
        return [
            'core' => 'Hôtel & réception',
            'restaurant' => 'Restaurant',
            'shop' => 'Boutique',
            'housekeeping' => 'Housekeeping',
            'accounting' => 'Comptabilité',
            'analytics' => 'Analytics',
        ];
    }

    public static function permissions(): array
    {
        return [
            'admin'               => ['core', 'restaurant', 'shop', 'housekeeping', 'accounting', 'analytics'],
            'manager'             => ['core', 'restaurant', 'shop', 'housekeeping', 'accounting', 'analytics'],
            'reception'           => ['core', 'housekeeping'],
            'cashier'             => ['core', 'restaurant'],
            'accountant'          => ['accounting'],
            'housekeeping_leader' => ['housekeeping'],
            'housekeeping_staff'  => ['housekeeping'],
            'housekeeping'        => ['housekeeping'],
            'restaurant_chief'    => ['restaurant'],
            'restaurant_staff'    => ['restaurant'],
            'shop_manager'        => ['shop'],
            'shop_cashier'        => ['shop'],
        ];
    }
}
