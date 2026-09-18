<?php

namespace App\Support;

/**
 * Répertoire officiel des Départements hôteliers et d'entreprise.
 *
 * Définit la structure organisationnelle standard d'un établissement WeTchah,
 * avec les modules attachés par défaut à chaque département.
 */
class DepartmentCatalog
{
    public const DEPT_DIRECTION    = 'direction_generale';
    public const DEPT_RECEPTION    = 'reception_front_office';
    public const DEPT_HOUSEKEEPING = 'housekeeping_hebergement';
    public const DEPT_RESTAURATION = 'restauration_fb';
    public const DEPT_RH           = 'ressources_humaines';
    public const DEPT_FINANCE      = 'comptabilite_finance';
    public const DEPT_IT           = 'informatique_it';
    public const DEPT_QUALITE      = 'qualite_controle';
    public const DEPT_BOUTIQUE     = 'boutique_commerce';

    public static function all(): array
    {
        return [
            self::DEPT_DIRECTION => [
                'code'        => 'DIR',
                'slug'        => self::DEPT_DIRECTION,
                'name'        => 'Direction Générale',
                'description' => 'Supervise l’ensemble de l’établissement, arrête les choix stratégiques et pilote la rentabilité globale.',
                'icon'        => 'briefcase',
                'accent'      => 'indigo',
                'sort_order'  => 1,
                'default_modules' => [
                    'hebergement', 'reservations', 'clients', 'housekeeping',
                    'restaurant', 'shop', 'economat', 'comptabilite',
                    'analytics', 'parametres', 'utilisateurs',
                    'discussions', 'ai', 'website', 'grc',
                ],
            ],

            self::DEPT_RECEPTION => [
                'code'        => 'REC',
                'slug'        => self::DEPT_RECEPTION,
                'name'        => 'Réception / Front Office',
                'description' => 'Gère l’accueil, les réservations individuelles et groupes, l’enregistrement, le départ des clients et la caisse réception.',
                'icon'        => 'calendar-check',
                'accent'      => 'sky',
                'sort_order'  => 2,
                'default_modules' => [
                    'reservations', 'hebergement', 'clients', 'comptabilite',
                    'website', 'discussions', 'ai',
                ],
            ],

            self::DEPT_HOUSEKEEPING => [
                'code'        => 'HSK',
                'slug'        => self::DEPT_HOUSEKEEPING,
                'name'        => 'Hébergement / Housekeeping',
                'description' => 'Nettoyage des chambres, entretien des espaces communs, gestion du linge, des fiches techniques et inspection des étages.',
                'icon'        => 'sparkles',
                'accent'      => 'teal',
                'sort_order'  => 3,
                'default_modules' => [
                    'housekeeping', 'hebergement', 'economat', 'discussions',
                ],
            ],

            self::DEPT_RESTAURATION => [
                'code'        => 'FNB',
                'slug'        => self::DEPT_RESTAURATION,
                'name'        => 'Restauration (Food & Beverage - F&B)',
                'description' => 'Service en salle (restaurant, bar, banquets, room service) et production culinaire (cuisine, garde-manger, fiches recettes).',
                'icon'        => 'utensils',
                'accent'      => 'amber',
                'sort_order'  => 4,
                'default_modules' => [
                    'restaurant', 'portail', 'economat', 'discussions',
                ],
            ],

            self::DEPT_RH => [
                'code'        => 'RH',
                'slug'        => self::DEPT_RH,
                'name'        => 'Ressources Humaines (RH)',
                'description' => 'Recrutement, gestion des contrats, paie, suivi des dossiers employés, compétences, formation et relations sociales.',
                'icon'        => 'users',
                'accent'      => 'purple',
                'sort_order'  => 5,
                'default_modules' => [
                    'utilisateurs', 'grc', 'discussions',
                ],
            ],

            self::DEPT_FINANCE => [
                'code'        => 'FIN',
                'slug'        => self::DEPT_FINANCE,
                'name'        => 'Comptabilité et Finance',
                'description' => 'Comptabilité générale, grand livre SYSCOHADA, contrôle de gestion, audit interne, trésorerie et suivi des caisses.',
                'icon'        => 'calculator',
                'accent'      => 'emerald',
                'sort_order'  => 6,
                'default_modules' => [
                    'comptabilite', 'ledger', 'economat', 'analytics', 'grc',
                ],
            ],

            self::DEPT_IT => [
                'code'        => 'IT',
                'slug'        => self::DEPT_IT,
                'name'        => 'Informatique / IT',
                'description' => 'Systèmes de réservation (PMS), réseau, parc informatique, site web, passerelles API et support technique interne.',
                'icon'        => 'laptop',
                'accent'      => 'blue',
                'sort_order'  => 7,
                'default_modules' => [
                    'parametres', 'api', 'pwa', 'ai', 'website', 'discussions',
                ],
            ],

            self::DEPT_QUALITE => [
                'code'        => 'QLT',
                'slug'        => self::DEPT_QUALITE,
                'name'        => 'Qualité / Contrôle Qualité',
                'description' => 'Standards de service hôtelier, audits d’hygiène et de conformité, enquêtes de satisfaction clients et plans d\'action.',
                'icon'        => 'award',
                'accent'      => 'rose',
                'sort_order'  => 8,
                'default_modules' => [
                    'grc', 'clients', 'housekeeping', 'restaurant', 'discussions',
                ],
            ],

            self::DEPT_BOUTIQUE => [
                'code'        => 'BTQ',
                'slug'        => self::DEPT_BOUTIQUE,
                'name'        => 'Boutique & Commerce',
                'description' => 'Point de vente, catalogue d\'articles cadeaux / souvenirs, réassort économat et encaissement boutique.',
                'icon'        => 'store',
                'accent'      => 'orange',
                'sort_order'  => 9,
                'default_modules' => [
                    'shop', 'economat', 'discussions',
                ],
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Retourne la liste des départements associés à une clé de module.
     */
    public static function departmentsForModule(string $moduleKey): array
    {
        $depts = [];
        foreach (self::all() as $slug => $dept) {
            if (in_array($moduleKey, $dept['default_modules'] ?? [], true)) {
                $depts[] = $slug;
            }
        }
        return $depts;
    }
}
