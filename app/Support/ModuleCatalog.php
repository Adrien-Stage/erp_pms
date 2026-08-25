<?php

namespace App\Support;

/**
 * Répertoire des modules développés dans l'application établissement
 * (wetchah_app) — miroir consultatif alimentant l'onglet « Modules » du
 * dashboard TECH. L'ERP ne code pas ces modules, il les documente : à quoi
 * ils servent, qui les utilise, par où on y entre et comment on s'en sert.
 *
 * Champs d'une entrée :
 *  - label / tagline : nom et description courte affichés sur la carte
 *  - icon            : nom d'icône lucide (rendu via <i data-lucide="…">)
 *  - accent          : clé de couleur, traduite en classes dans les vues
 *                      (Tailwind ne scanne que resources/, pas app/)
 *  - type            : core | optionnel | derive | config — libellés dans
 *                      $typeBadges côté vues
 *  - key             : clé TENANT_MODULES pilotant l'activation, null si le
 *                      module est toujours actif
 *  - depends         : libellé du prérequis, quand il y en a un
 *  - entry           : point d'entrée dans le menu de l'application
 *  - roles           : rôles opérationnels qui y accèdent (voir TenantRoles)
 *  - screens         : écrans du module — libellé, chemin, rôle de l'écran
 *  - guide           : guide d'utilisation, étape par étape
 *  - tips            : points de vigilance issus du fonctionnement réel
 */
class ModuleCatalog
{
    /**
     * Clés canoniques du sélecteur de modules (admin/tenants/show.blade.php).
     * Un établissement dont aucune de ces clés n'est enregistrée n'est jamais
     * passé par le sélecteur : tout est actif chez lui.
     */
    public const TOGGLE_KEYS = [
        'restaurant', 'shop', 'housekeeping', 'discussions',
        'analytics', 'ledger', 'api', 'website',
    ];

    public static function all(): array
    {
        return array_merge(self::core(), self::optional());
    }

    public static function find(string $slug): ?array
    {
        $module = self::all()[$slug] ?? null;

        return $module ? array_merge(['slug' => $slug], $module) : null;
    }

    /**
     * Modules optionnels réellement actifs pour un établissement, avec la
     * même tolérance que le sélecteur pour les établissements historiques.
     */
    public static function enabledKeys(?array $tenantModules): array
    {
        $tenantModules = $tenantModules ?? [];

        return empty(array_intersect(self::TOGGLE_KEYS, $tenantModules))
            ? self::TOGGLE_KEYS
            : array_values(array_intersect(self::TOGGLE_KEYS, $tenantModules));
    }

    /** Un module sans clé est un module cœur : toujours actif. */
    public static function isEnabledFor(?array $tenantModules, ?string $key): bool
    {
        return $key === null || in_array($key, self::enabledKeys($tenantModules), true);
    }

    /** Nombre d'établissements équipés, par clé de module optionnel. */
    public static function adoption(iterable $tenants): array
    {
        $counts = array_fill_keys(self::TOGGLE_KEYS, 0);

        foreach ($tenants as $tenant) {
            foreach (self::enabledKeys($tenant->modules ?? []) as $key) {
                $counts[$key]++;
            }
        }

        return $counts;
    }

    /** Établissements équipés d'un module donné. */
    public static function tenantsWith(iterable $tenants, ?string $key): array
    {
        $equipped = [];

        foreach ($tenants as $tenant) {
            if (self::isEnabledFor($tenant->modules ?? [], $key)) {
                $equipped[] = $tenant;
            }
        }

        return $equipped;
    }

    /** Modules cœur : livrés avec toute application, jamais désactivables. */
    private static function core(): array
    {
        return [
            'hebergement' => [
                'label'   => 'Chambres & hébergement',
                'tagline' => 'Le parc de chambres : types, tarifs, statuts d\'occupation et fiches techniques de coût.',
                'icon'    => 'bed-double',
                'accent'  => 'indigo',
                'type'    => 'core',
                'key'     => null,
                'entry'   => 'Hôtel › Chambres',
                'roles'   => ['manager', 'reception', 'accountant'],
                'screens' => [
                    ['label' => 'Chambres', 'path' => '/rooms', 'desc' => 'Liste du parc, statut de chaque chambre, photos et création de chambres.'],
                    ['label' => 'Types de chambres', 'path' => '/rooms (types)', 'desc' => 'Catégories tarifaires : capacité, tarif de base, équipements.'],
                    ['label' => 'Fiches techniques', 'path' => '/hebergement/fiches-techniques', 'desc' => 'Coût de revient d\'une nuitée par type de chambre et marge dégagée.'],
                    ['label' => 'Import / export CSV', 'path' => '/rooms/export, /rooms/import', 'desc' => 'Reprise en masse du parc et des types depuis un fichier.'],
                ],
                'guide' => [
                    ['title' => 'Créer les types de chambres', 'body' => 'Tout part du type : c\'est lui qui porte la capacité, le tarif de base et les équipements. Créez-les avant les chambres, sinon aucune chambre ne peut être rattachée.'],
                    ['title' => 'Déclarer les chambres', 'body' => 'Chaque chambre est rattachée à un type et reçoit un numéro. Pour un parc existant, l\'import CSV évite la saisie une par une — exportez d\'abord le fichier pour en obtenir le format.'],
                    ['title' => 'Suivre les statuts', 'body' => 'Le statut d\'une chambre (libre, occupée, en nettoyage, hors service) évolue avec les arrivées, les départs et le housekeeping. La réception peut le forcer depuis la fiche de la chambre.'],
                    ['title' => 'Renseigner les fiches techniques', 'body' => 'Pour chaque type, saisissez les postes de coût d\'une nuitée (blanchisserie, produits d\'accueil, énergie…). Un démarrage rapide propose des postes standards à ajuster ; la marge par nuitée en découle.'],
                ],
                'tips' => [
                    'Réservé au manager et à la réception ; le comptable n\'accède qu\'aux fiches techniques.',
                    'Le housekeeping ne passe plus par cette rubrique : il pilote les statuts depuis son propre module.',
                ],
            ],

            'reservations' => [
                'label'   => 'Réservations & agenda',
                'tagline' => 'Séjours individuels et groupes, agenda, folio, encaissements et caisse de la réception.',
                'icon'    => 'calendar-days',
                'accent'  => 'indigo',
                'type'    => 'core',
                'key'     => null,
                'entry'   => 'Hôtel › Agenda / Réservations',
                'roles'   => ['manager', 'reception', 'cashier'],
                'screens' => [
                    ['label' => 'Agenda', 'path' => '/agenda', 'desc' => 'Calendrier des séjours — écran à part entière, distinct de la liste.'],
                    ['label' => 'Réservations individuelles', 'path' => '/bookings', 'desc' => 'Cycle complet : création, confirmation, arrivée, départ, annulation.'],
                    ['label' => 'Groupes', 'path' => '/groups', 'desc' => 'Réservation multi-chambres, arrivée et départ groupés, facture unique.'],
                    ['label' => 'Caisse réception', 'path' => '/bookings/cash-register', 'desc' => 'Ouverture, décaissements et clôture de la session de caisse.'],
                    ['label' => 'Facture', 'path' => '/invoices/{facture}', 'desc' => 'Document émis au départ du client.'],
                ],
                'guide' => [
                    ['title' => 'Ouvrir la caisse en début de service', 'body' => 'Aucune action métier n\'est possible caisse fermée : modifier un séjour, encaisser, faire une arrivée ou un départ est bloqué tant que la session n\'est pas ouverte. Déclarez le fond de caisse à l\'ouverture.'],
                    ['title' => 'Créer la réservation', 'body' => 'Sélectionnez le client (ou créez-le à la volée), les dates et la chambre. La disponibilité est vérifiée sur la période demandée.'],
                    ['title' => 'Faire l\'arrivée', 'body' => 'Le check-in bascule la réservation en séjour en cours et occupe la chambre. Pour un groupe, l\'arrivée peut être faite pour toutes les chambres d\'un coup.'],
                    ['title' => 'Alimenter le folio pendant le séjour', 'body' => 'Le folio est le compte du séjour : consommations, extras et acomptes s\'y ajoutent. C\'est le point de jonction avec les autres modules — une commande restaurant ou une vente boutique peut y être reportée au lieu d\'être encaissée immédiatement.'],
                    ['title' => 'Encaisser puis faire le départ', 'body' => 'Les règlements se saisissent sur le folio ; le check-out solde le séjour, libère la chambre et permet l\'édition de la facture.'],
                    ['title' => 'Clôturer la caisse', 'body' => 'En fin de service, la personne qui a ouvert la caisse la ferme en déclarant l\'espèce comptée. L\'écart entre théorique et compté est enregistré : c\'est lui qui est audité, pas le montant.'],
                ],
                'tips' => [
                    'Celui qui ouvre la caisse est celui qui la ferme — la session est liée à son compte.',
                    'Caisse réception et caisse boutique sont deux caisses indépendantes, clôturées séparément.',
                ],
            ],

            'clients' => [
                'label'   => 'Clients',
                'tagline' => 'Fichier client de l\'établissement : coordonnées, historique des séjours et fidélité.',
                'icon'    => 'users',
                'accent'  => 'sky',
                'type'    => 'core',
                'key'     => null,
                'entry'   => 'Gestion › Clients',
                'roles'   => ['manager', 'reception', 'cashier'],
                'screens' => [
                    ['label' => 'Répertoire clients', 'path' => '/customers', 'desc' => 'Recherche par nom, téléphone ou email.'],
                    ['label' => 'Fiche client', 'path' => '/customers/{client}', 'desc' => 'Coordonnées, nationalité, historique des séjours et statut de fidélité.'],
                    ['label' => 'Import / export CSV', 'path' => '/customers/export, /customers/import', 'desc' => 'Reprise d\'un fichier client existant.'],
                ],
                'guide' => [
                    ['title' => 'Créer un client', 'body' => 'Depuis le répertoire, ou directement pendant la création d\'une réservation : le sélecteur de client permet de créer la fiche sans quitter le formulaire.'],
                    ['title' => 'Éviter les doublons', 'body' => 'Recherchez toujours le client par téléphone avant de créer une fiche : c\'est le champ le plus fiable pour retrouver un habitué.'],
                    ['title' => 'Consulter l\'historique', 'body' => 'La fiche client agrège ses séjours passés — utile pour reconnaître un client fidèle et justifier un geste commercial.'],
                    ['title' => 'Reprendre un fichier existant', 'body' => 'À l\'ouverture d\'un établissement, exportez le CSV pour obtenir le format, remplissez-le et réimportez-le plutôt que de saisir les fiches une à une.'],
                ],
                'tips' => [
                    'La caisse peut consulter les clients mais pas les créer ni les modifier.',
                ],
            ],

            'economat' => [
                'label'   => 'Économat',
                'tagline' => 'Le magasin central : articles, fournisseurs, bons de commande et demandes internes des départements.',
                'icon'    => 'warehouse',
                'accent'  => 'emerald',
                'type'    => 'core',
                'key'     => null,
                'entry'   => 'Économat › Tableau de bord',
                'roles'   => ['econome', 'manager', 'admin'],
                'screens' => [
                    ['label' => 'Tableau de bord', 'path' => '/economat', 'desc' => 'Valeur du stock, alertes de seuil et demandes en attente.'],
                    ['label' => 'Articles', 'path' => '/economat/articles', 'desc' => 'Fiches article, stock courant, coût moyen pondéré et ajustements.'],
                    ['label' => 'Fournisseurs', 'path' => '/economat/fournisseurs', 'desc' => 'Carnet des fournisseurs du magasin.'],
                    ['label' => 'Bons de commande', 'path' => '/economat/bons', 'desc' => 'Commande, envoi au fournisseur, réception et annulation.'],
                    ['label' => 'Demandes internes', 'path' => '/economat/demandes', 'desc' => 'Sorties de stock demandées par les départements.'],
                ],
                'guide' => [
                    ['title' => 'Créer le carnet fournisseurs et les articles', 'body' => 'Chaque article porte une unité, un seuil d\'alerte et son stock. Le prix n\'est pas saisi à la main : il résulte des réceptions.'],
                    ['title' => 'Acheter — le bon de commande', 'body' => 'Créez le bon, envoyez-le au fournisseur, puis enregistrez la réception avec les quantités réellement livrées. La réception entre le stock et recalcule le coût moyen pondéré de l\'article.'],
                    ['title' => 'Distribuer — la demande interne', 'body' => 'Un responsable de département (réception, gouvernante, chef, boutique) saisit sa demande. L\'économe la valide ou la refuse, puis la livre : c\'est la livraison qui sort le stock.'],
                    ['title' => 'Corriger — l\'ajustement', 'body' => 'Casse, perte ou écart d\'inventaire se traitent par un ajustement sur la fiche article, avec motif — jamais en modifiant le stock directement.'],
                    ['title' => 'Surveiller les seuils', 'body' => 'Le tableau de bord remonte les articles sous leur seuil d\'alerte : c\'est la liste de réapprovisionnement du jour.'],
                ],
                'tips' => [
                    'À ne pas confondre avec le garde-manger du restaurant : ce sont deux stocks distincts, avec leurs propres articles et mouvements.',
                    'Toute variation de stock passe par un mouvement journalisé — l\'historique d\'un article reste donc toujours reconstituable.',
                ],
            ],

            'comptabilite' => [
                'label'   => 'Comptabilité de caisse',
                'tagline' => 'Sessions de caisse, journal, compte de résultat, créances et dépenses — sans grand livre.',
                'icon'    => 'wallet',
                'accent'  => 'violet',
                'type'    => 'core',
                'key'     => null,
                'entry'   => 'Comptabilité › Comptabilité',
                'roles'   => ['accountant', 'manager', 'admin'],
                'screens' => [
                    ['label' => 'Vue d\'ensemble', 'path' => '/accounting', 'desc' => 'Recettes, dépenses et trésorerie de la période.'],
                    ['label' => 'Journal', 'path' => '/accounting/journal', 'desc' => 'Détail chronologique des mouvements.'],
                    ['label' => 'Compte de résultat', 'path' => '/accounting/compte-de-resultat', 'desc' => 'Produits et charges de la période.'],
                    ['label' => 'Créances', 'path' => '/accounting/creances', 'desc' => 'Ce qui reste dû par les clients.'],
                    ['label' => 'Caisse', 'path' => '/accounting/caisse', 'desc' => 'Sessions de caisse, écarts constatés et décaissements.'],
                    ['label' => 'Dépenses', 'path' => '/accounting/depenses', 'desc' => 'Saisie et suivi des dépenses de l\'établissement.'],
                ],
                'guide' => [
                    ['title' => 'Comprendre d\'où viennent les recettes', 'body' => 'Les recettes ne sont pas ressaisies : elles sont dérivées des règlements encaissés dans l\'hébergement, le restaurant et la boutique. Une même somme n\'est comptée qu\'une fois, même si elle transite par un folio.'],
                    ['title' => 'Saisir les dépenses', 'body' => 'Seules les dépenses sont saisies à la main, avec leur catégorie. Elles alimentent directement le compte de résultat.'],
                    ['title' => 'Contrôler les caisses', 'body' => 'L\'écran Caisse liste les sessions ouvertes et fermées avec l\'écart entre théorique et compté : c\'est le premier réflexe de contrôle quotidien.'],
                    ['title' => 'Suivre les créances', 'body' => 'Les séjours partis sans règlement complet apparaissent en créances — c\'est la liste de relance.'],
                    ['title' => 'Éditer les états', 'body' => 'Journal et compte de résultat se consultent sur une période choisie ; ils constituent le reporting mensuel de l\'établissement.'],
                ],
                'tips' => [
                    'Reste actif même sans le module Comptabilité avancée : les deux se superposent, ils ne se remplacent pas.',
                    'Le personnel sans accès aux données financières ne voit aucun montant dans l\'application.',
                ],
            ],

            'utilisateurs' => [
                'label'   => 'Utilisateurs & rôles',
                'tagline' => 'Comptes du personnel, rôles opérationnels et niveau lecture / écriture par module.',
                'icon'    => 'user-cog',
                'accent'  => 'slate',
                'type'    => 'core',
                'key'     => null,
                'entry'   => 'Gestion › Utilisateurs',
                'roles'   => ['manager'],
                'screens' => [
                    ['label' => 'Personnel', 'path' => '/users', 'desc' => 'Création de comptes, attribution des rôles, activation et désactivation.'],
                    ['label' => 'Profil', 'path' => '/profile', 'desc' => 'Chaque employé y modifie son mot de passe et ses informations.'],
                ],
                'guide' => [
                    ['title' => 'Créer le compte', 'body' => 'Le manager crée le compte de l\'employé et lui attribue un ou plusieurs rôles. Le rôle détermine ce qu\'il voit dans le menu latéral.'],
                    ['title' => 'Choisir le bon rôle', 'body' => 'Les rôles sont regroupés par périmètre : direction, hébergement, housekeeping, restaurant, boutique, économat, comptabilité. Attribuez le rôle le plus étroit qui couvre le poste.'],
                    ['title' => 'Poser un accès en lecture seule', 'body' => 'Un rôle peut être rattaché à un module en lecture seule : la personne consulte les écrans, mais toute action (création, modification, suppression) est refusée. Direction et manager ne sont jamais restreints.'],
                    ['title' => 'Désactiver un départ', 'body' => 'On désactive un compte plutôt que de le supprimer : l\'historique des actions et des caisses reste rattaché à la personne.'],
                ],
                'tips' => [
                    'Depuis l\'ERP, la fiche d\'un établissement permet aussi d\'agir sur ces comptes — ils vivent dans la base du tenant, la modification est donc immédiate côté application.',
                ],
            ],

            'parametres' => [
                'label'   => 'Paramètres de l\'établissement',
                'tagline' => 'Identité, thème, taxes, services et partenaires — la configuration métier vue par le manager.',
                'icon'    => 'settings',
                'accent'  => 'slate',
                'type'    => 'core',
                'key'     => null,
                'entry'   => 'Paramètres',
                'roles'   => ['manager', 'reception', 'housekeeping_leader', 'restaurant_chief', 'shop_manager'],
                'screens' => [
                    ['label' => 'Général', 'path' => '/settings?tab=general', 'desc' => 'Nom, logo, coordonnées et couleurs de l\'établissement.'],
                    ['label' => 'Hébergement', 'path' => '/settings?tab=hebergement', 'desc' => 'Paramètres de réception : heures d\'arrivée et de départ, options de séjour.'],
                    ['label' => 'Taxes', 'path' => '/settings?tab=taxes', 'desc' => 'Taxes appliquées aux séjours et aux ventes.'],
                    ['label' => 'Housekeeping / Restaurant / Boutique', 'path' => '/settings?tab=…', 'desc' => 'Réglages propres à chaque module activé.'],
                    ['label' => 'Services & partenaires', 'path' => '/settings?tab=services', 'desc' => 'Prestations annexes facturables et partenaires de l\'établissement.'],
                ],
                'guide' => [
                    ['title' => 'Poser l\'identité en premier', 'body' => 'Nom, logo et couleurs conditionnent l\'apparence de toute l\'application et des documents imprimés (factures, reçus). À faire dès l\'ouverture de l\'établissement.'],
                    ['title' => 'Configurer les taxes avant les premières ventes', 'body' => 'Les taxes s\'appliquent au moment de la facturation : les changer après coup ne recalcule pas les documents déjà émis.'],
                    ['title' => 'Régler chaque module activé', 'body' => 'Les onglets Housekeeping, Restaurant et Boutique n\'ont de sens que si le module correspondant est actif pour l\'établissement.'],
                    ['title' => 'Exporter / importer', 'body' => 'Chaque onglet dispose d\'un export et d\'un import CSV, pratique pour dupliquer une configuration d\'un établissement à un autre.'],
                ],
                'tips' => [
                    'Le logo et le thème sont importés par le manager depuis l\'application ; l\'ERP peut aussi imposer un thème depuis la fiche de l\'établissement.',
                ],
            ],

            'pwa' => [
                'label'   => 'Application installable & notifications',
                'tagline' => 'Installation sur mobile, écran hors-ligne, notifications internes et notifications push système.',
                'icon'    => 'smartphone',
                'accent'  => 'cyan',
                'type'    => 'core',
                'key'     => null,
                'entry'   => 'Bannière d\'installation du navigateur',
                'roles'   => ['tous les rôles'],
                'screens' => [
                    ['label' => 'Manifeste', 'path' => '/manifest.webmanifest', 'desc' => 'Décrit l\'application au navigateur : nom, couleurs, icônes.'],
                    ['label' => 'Icônes', 'path' => '/pwa/icon/{taille}', 'desc' => 'Icônes générées à la volée aux couleurs de l\'établissement.'],
                    ['label' => 'Hors-ligne', 'path' => '/offline', 'desc' => 'Écran affiché quand le réseau est coupé.'],
                    ['label' => 'Notifications', 'path' => '/notifications/unread', 'desc' => 'Cloche interne : demandes, commandes et alertes non lues.'],
                    ['label' => 'Web Push', 'path' => '/push/subscribe', 'desc' => 'Abonnement d\'un appareil aux notifications système.'],
                ],
                'guide' => [
                    ['title' => 'Installer l\'application', 'body' => 'Depuis le navigateur du téléphone, « Ajouter à l\'écran d\'accueil » installe l\'application aux couleurs de l\'établissement. Utile pour le housekeeping et les serveurs, qui travaillent sur mobile.'],
                    ['title' => 'Autoriser les notifications', 'body' => 'À la première connexion, l\'appareil demande l\'autorisation d\'afficher les notifications. Une fois accordée, l\'appareil est abonné — chaque navigateur et chaque téléphone s\'abonne séparément.'],
                    ['title' => 'Suivre les notifications internes', 'body' => 'La cloche du bandeau regroupe ce qui attend une action : demandes à l\'économat, commandes prêtes, messages non lus.'],
                ],
                'tips' => [
                    'Les icônes sont servies sans extension « .png » : une URL en .png serait interceptée par nginx et n\'atteindrait jamais Laravel.',
                ],
            ],
        ];
    }

    /**
     * Modules optionnels : activables établissement par établissement depuis
     * la fiche du tenant (clé propagée dans TENANT_MODULES au container).
     */
    private static function optional(): array
    {
        return [
            'restaurant' => [
                'label'   => 'Restaurant',
                'tagline' => 'Carte, service en salle, écran cuisine, garde-manger valorisé, fiches techniques et facturation.',
                'icon'    => 'utensils',
                'accent'  => 'amber',
                'type'    => 'optionnel',
                'key'     => 'restaurant',
                'entry'   => 'Restaurant › Commandes',
                'roles'   => ['manager', 'restaurant_chief', 'restaurant_staff', 'restaurant_cook', 'cashier'],
                'screens' => [
                    ['label' => 'Commandes', 'path' => '/restaurant/orders', 'desc' => 'Prise de commande en salle et suivi du cycle de service.'],
                    ['label' => 'Cuisine', 'path' => '/restaurant/kitchen', 'desc' => 'Écran cuisine : ce qui est à préparer, ce qui est prêt.'],
                    ['label' => 'Menus', 'path' => '/restaurant/menus', 'desc' => 'Catégories, plats, prix, photos et services de disponibilité.'],
                    ['label' => 'Fiches techniques', 'path' => '/restaurant/recipes', 'desc' => 'Recettes reliant un plat aux ingrédients du garde-manger.'],
                    ['label' => 'Garde-manger', 'path' => '/restaurant/pantry', 'desc' => 'Stock cuisine : réceptions, mouvements et valorisation.'],
                    ['label' => 'Inventaires', 'path' => '/restaurant/stock-counts', 'desc' => 'Comptages périodiques et écarts constatés.'],
                    ['label' => 'Facturation', 'path' => '/restaurant/billing', 'desc' => 'Encaissement des commandes et édition des reçus.'],
                ],
                'guide' => [
                    ['title' => 'Construire la carte', 'body' => 'Le chef cuisinier crée les catégories puis les plats : prix, photo et services auxquels chaque plat est disponible (petit-déjeuner, déjeuner, dîner). Import et export CSV disponibles pour une carte volumineuse.'],
                    ['title' => 'Créer le garde-manger et les fiches techniques', 'body' => 'Déclarez les ingrédients dans le garde-manger, puis reliez chaque plat à ses ingrédients par une fiche technique. C\'est ce lien qui rend le coût et la sortie de stock automatiques à chaque plat vendu.'],
                    ['title' => 'Prendre son service', 'body' => 'Un serveur ouvre son service avant de prendre des commandes — c\'est cette prise de service qui le rend éligible aux affectations.'],
                    ['title' => 'Faire tourner une commande', 'body' => 'Le cycle est : en attente → confirmée → en préparation → prête → servie, l\'annulation restant possible à tout moment. Le serveur transmet en cuisine, le cuisinier prend en préparation puis signale prêt, le serveur sert.'],
                    ['title' => 'Encaisser', 'body' => 'La facturation clôture la commande : encaissement direct, ou report sur le folio du séjour si le client est logé dans l\'établissement.'],
                    ['title' => 'Inventorier', 'body' => 'Un inventaire compare le stock théorique au stock compté et matérialise les écarts. À faire au rythme choisi par le chef, en fin de période.'],
                ],
                'tips' => [
                    'La cuisine et la salle ne communiquent pas directement : chaque commande passe par un serveur, qui transmet puis apporte le plat.',
                    'Le garde-manger est propre au restaurant — il ne remplace pas l\'économat, qui reste le magasin central de l\'établissement.',
                ],
            ],

            'shop' => [
                'label'   => 'Boutique',
                'tagline' => 'Point de vente : catalogue d\'articles, ventes directes, reçus et caisse dédiée.',
                'icon'    => 'store',
                'accent'  => 'emerald',
                'type'    => 'optionnel',
                'key'     => 'shop',
                'entry'   => 'Boutique › Articles',
                'roles'   => ['shop_manager', 'shop_cashier', 'manager'],
                'screens' => [
                    ['label' => 'Articles', 'path' => '/shop/products', 'desc' => 'Catalogue : prix, stock et import / export CSV.'],
                    ['label' => 'Commandes', 'path' => '/shop/orders', 'desc' => 'Ventes du jour, encaissement, remboursement et reçu.'],
                    ['label' => 'Caisse boutique', 'path' => '/shop/cash-register', 'desc' => 'Ouverture, décaissements et clôture de la caisse de la boutique.'],
                ],
                'guide' => [
                    ['title' => 'Alimenter le catalogue', 'body' => 'Le responsable boutique crée les articles avec leur prix de vente. L\'import CSV permet de charger un catalogue existant en une fois.'],
                    ['title' => 'Ouvrir la caisse boutique', 'body' => 'La vente suppose une caisse ouverte, indépendante de celle de la réception. Elle a son propre fond de caisse et sa propre clôture.'],
                    ['title' => 'Vendre', 'body' => 'La commande se compose article par article, puis est marquée payée. Un reçu imprimable est généré ; un remboursement reste possible sur une vente encaissée.'],
                    ['title' => 'Clôturer', 'body' => 'En fin de journée, la personne qui a ouvert la caisse la ferme en déclarant l\'espèce comptée. L\'écart est enregistré et remonte dans la comptabilité.'],
                ],
                'tips' => [
                    'Le manager est en lecture seule sur la boutique : il consulte tout mais n\'encaisse pas — la séparation est volontaire.',
                    'Le réassort de la boutique se demande à l\'économat via une demande interne.',
                ],
            ],

            'housekeeping' => [
                'label'   => 'Housekeeping',
                'tagline' => 'Nettoyage des chambres : équipes, affectations et cycle propreté jusqu\'à l\'inspection.',
                'icon'    => 'brush-cleaning',
                'accent'  => 'sky',
                'type'    => 'optionnel',
                'key'     => 'housekeeping',
                'entry'   => 'Hôtel › Housekeeping',
                'roles'   => ['housekeeping_leader', 'housekeeping_staff', 'manager'],
                'screens' => [
                    ['label' => 'Tableau housekeeping', 'path' => '/housekeeping', 'desc' => 'État de propreté du parc, affectations du jour et incidents signalés.'],
                    ['label' => 'Équipes', 'path' => '/housekeeping/teams', 'desc' => 'Constitution des équipes d\'étage par la gouvernante.'],
                    ['label' => 'Affectations', 'path' => '/housekeeping/assignments', 'desc' => 'Attribution des chambres à nettoyer.'],
                ],
                'guide' => [
                    ['title' => 'Constituer les équipes', 'body' => 'La gouvernante crée les équipes d\'étage et y rattache le personnel. Une équipe est l\'unité à laquelle on affecte des chambres.'],
                    ['title' => 'Affecter les chambres du jour', 'body' => 'Chaque matin, les chambres à faire (départs, recouches) sont réparties entre les équipes.'],
                    ['title' => 'Dérouler le cycle', 'body' => 'Le personnel marque la chambre en nettoyage, puis prête. La gouvernante inspecte : elle valide (la chambre redevient disponible) ou rejette, ce qui la renvoie au nettoyage.'],
                    ['title' => 'Signaler un incident', 'body' => 'Un problème constaté en chambre (équipement cassé, fuite) se signale depuis la chambre : la réception le voit sans avoir à être prévenue oralement.'],
                ],
                'tips' => [
                    'Le personnel de ménage ne voit aucun montant dans l\'application — les données financières lui sont masquées.',
                    'C\'est ce module qui pilote le statut de propreté des chambres, plus la rubrique Chambres.',
                ],
            ],

            'discussions' => [
                'label'   => 'Discussions',
                'tagline' => 'Messagerie interne entre membres du personnel, avec indicateur de messages non lus.',
                'icon'    => 'message-circle',
                'accent'  => 'violet',
                'type'    => 'optionnel',
                'key'     => 'discussions',
                'entry'   => 'Bas du menu latéral › Discussions',
                'roles'   => ['tous les rôles'],
                'screens' => [
                    ['label' => 'Discussions', 'path' => '/discussions', 'desc' => 'Conversations, envoi de messages et archivage.'],
                ],
                'guide' => [
                    ['title' => 'Ouvrir une conversation', 'body' => 'Sélectionnez les collègues concernés : la conversation peut réunir deux personnes ou toute une équipe.'],
                    ['title' => 'Repérer les messages non lus', 'body' => 'Une pastille sur l\'entrée « Discussions » du menu signale qu\'un message attend. Elle s\'éteint à la lecture.'],
                    ['title' => 'Archiver plutôt que supprimer', 'body' => 'Une conversation traitée s\'archive : elle sort de la liste courante sans perdre l\'historique.'],
                ],
                'tips' => [
                    'Le fil est interne à l\'établissement : aucun message ne traverse vers un autre établissement de la plateforme.',
                ],
            ],

            'analytics' => [
                'label'   => 'Analytics — tour de contrôle',
                'tagline' => 'Statistiques d\'occupation, de revenus et tableaux de bord réservés au manager.',
                'icon'    => 'chart-column',
                'accent'  => 'blue',
                'type'    => 'optionnel',
                'key'     => 'analytics',
                'entry'   => 'Analytique › Tour de contrôle',
                'roles'   => ['manager'],
                'screens' => [
                    ['label' => 'Tour de contrôle', 'path' => '/analytics', 'desc' => 'Occupation, revenus, fréquentation et tendances de l\'établissement.'],
                    ['label' => 'Impression', 'path' => '/analytics/print', 'desc' => 'Version imprimable du tableau de bord pour une réunion ou un conseil.'],
                ],
                'guide' => [
                    ['title' => 'Choisir la période', 'body' => 'Tous les indicateurs se recalculent sur la période sélectionnée : commencez toujours par la fixer avant de lire les chiffres.'],
                    ['title' => 'Lire l\'occupation', 'body' => 'Le taux d\'occupation rapporte les nuitées vendues aux nuitées disponibles du parc — il chute mécaniquement si des chambres sont hors service.'],
                    ['title' => 'Croiser avec les revenus', 'body' => 'Occupation forte et revenu faible signalent un problème de tarification ; l\'inverse, un parc sous-dimensionné.'],
                    ['title' => 'Imprimer pour partager', 'body' => 'La version imprimable produit un document propre, sans navigation, à joindre à un compte rendu.'],
                ],
                'tips' => [
                    'Strictement réservé au manager : aucun autre rôle n\'y accède, même en lecture.',
                ],
            ],

            'ledger' => [
                'label'   => 'Comptabilité avancée (SYSCOHADA)',
                'tagline' => 'Grand livre : plan de comptes, journaux, balance, clôture, tiers et lettrage, analytique.',
                'icon'    => 'book-open',
                'accent'  => 'violet',
                'type'    => 'optionnel',
                'key'     => 'ledger',
                'entry'   => 'Comptabilité › Grand livre',
                'roles'   => ['accountant', 'manager', 'admin'],
                'screens' => [
                    ['label' => 'Plan de comptes', 'path' => '/accounting/ledger/plan-de-comptes', 'desc' => 'Comptes SYSCOHADA de l\'établissement.'],
                    ['label' => 'Journaux & écritures', 'path' => '/accounting/ledger/journaux', 'desc' => 'Écritures par journal, consultation et extourne.'],
                    ['label' => 'Grand livre et balance', 'path' => '/accounting/ledger/grand-livre', 'desc' => 'Mouvements par compte et balance générale.'],
                    ['label' => 'Clôture journalière', 'path' => '/accounting/ledger/cloture', 'desc' => 'Passage de la journée en écritures comptables.'],
                    ['label' => 'Périodes et exercices', 'path' => '/accounting/ledger/periodes', 'desc' => 'Ouverture d\'exercice et verrouillage des périodes.'],
                    ['label' => 'Tiers et lettrage', 'path' => '/accounting/ledger/auxiliaire', 'desc' => 'Comptabilité auxiliaire, lettrage et balance âgée.'],
                    ['label' => 'Factures fournisseurs', 'path' => '/accounting/ledger/fournisseurs', 'desc' => 'Factures reçues et retenues à la source.'],
                    ['label' => 'Analytique', 'path' => '/accounting/ledger/analytique', 'desc' => 'Rentabilité par point de vente (classe 9).'],
                ],
                'guide' => [
                    ['title' => 'Ouvrir l\'exercice', 'body' => 'Premier geste : ouvrir l\'exercice comptable, puis reprendre les à-nouveaux si l\'établissement avait déjà une comptabilité.'],
                    ['title' => 'Vérifier le plan de comptes', 'body' => 'Le plan SYSCOHADA est fourni ; contrôlez qu\'il correspond aux besoins de l\'établissement avant la première clôture.'],
                    ['title' => 'Clôturer chaque journée', 'body' => 'La clôture journalière transforme l\'activité du jour (séjours, restaurant, boutique, caisses) en écritures comptables. C\'est le geste quotidien du comptable.'],
                    ['title' => 'Lettrer les tiers', 'body' => 'La comptabilité auxiliaire détaille les comptes collectifs client par client et fournisseur par fournisseur. Le lettrage, manuel ou automatique, rapproche facture et règlement ; la balance âgée en découle.'],
                    ['title' => 'Saisir les factures fournisseurs', 'body' => 'Les factures reçues s\'enregistrent avec, le cas échéant, la retenue à la source. Un état des retenues est éditable pour la déclaration.'],
                    ['title' => 'Verrouiller la période', 'body' => 'Une fois la période contrôlée, verrouillez-la : plus aucune écriture ne peut y être ajoutée ou modifiée. Une erreur découverte après coup se corrige par extourne, jamais par modification.'],
                ],
                'tips' => [
                    'S\'ajoute à la comptabilité de caisse sans la remplacer : l\'une répond au quotidien de la réception, l\'autre à l\'obligation légale et au pilotage.',
                    'Tout le monde n\'a pas besoin d\'un grand livre — module à réserver aux établissements réellement suivis par un comptable.',
                ],
            ],

            'portail' => [
                'label'   => 'Portail client (QR)',
                'tagline' => 'Carte du restaurant consultable par le client depuis son téléphone, sans compte ni installation.',
                'icon'    => 'qr-code',
                'accent'  => 'amber',
                'type'    => 'derive',
                'key'     => 'restaurant',
                'depends' => 'Fourni avec le module Restaurant',
                'entry'   => 'Restaurant › Portail (QR)',
                'roles'   => ['client final (public)'],
                'screens' => [
                    ['label' => 'Carte publique', 'path' => '/portal/{slug}/restaurant', 'desc' => 'Menu de l\'établissement, accessible sans authentification.'],
                    ['label' => 'Commande client', 'path' => '/portal/{slug}/restaurant/orders', 'desc' => 'Commande passée depuis la table par le client.'],
                    ['label' => 'Suivi', 'path' => '/portal/{slug}/restaurant/orders/{commande}', 'desc' => 'État de la commande côté client.'],
                ],
                'guide' => [
                    ['title' => 'Récupérer le lien', 'body' => 'Le lien du portail s\'ouvre depuis le menu Restaurant de l\'application, entrée « Portail (QR) ». Il contient le slug de l\'établissement.'],
                    ['title' => 'Générer et poser les QR codes', 'body' => 'Transformez ce lien en QR code et posez-le sur les tables ou en chambre. Aucun compte n\'est demandé au client.'],
                    ['title' => 'Traiter les commandes reçues', 'body' => 'Les commandes du portail arrivent dans l\'écran Commandes du restaurant et suivent ensuite le cycle normal du service.'],
                ],
                'tips' => [
                    'Le portail disparaît si le module Restaurant est désactivé : il est protégé par la même clé.',
                    'La carte affichée est celle du module Menus — rien à saisir deux fois.',
                ],
            ],

            'api' => [
                'label'   => 'API d\'intégration',
                'tagline' => 'Routes publiques en lecture (chambres, carte) et demandes de réservation depuis l\'extérieur.',
                'icon'    => 'plug',
                'accent'  => 'slate',
                'type'    => 'optionnel',
                'key'     => 'api',
                'entry'   => 'Aucune interface — consommée par des applications tierces',
                'roles'   => ['applications externes'],
                'screens' => [
                    ['label' => 'Chambres et types', 'path' => '/api/v1/rooms, /api/v1/room-types', 'desc' => 'Catalogue d\'hébergement en lecture seule.'],
                    ['label' => 'Carte du restaurant', 'path' => '/api/v1/restaurant/menu', 'desc' => 'Carte publique — nécessite le module Restaurant actif.'],
                    ['label' => 'Demande de réservation', 'path' => 'POST /api/v1/bookings', 'desc' => 'Demande entrante, limitée en débit contre le spam.'],
                    ['label' => 'Ping', 'path' => '/api/v1/ping', 'desc' => 'Vérification de disponibilité utilisée par le badge de liaison.'],
                ],
                'guide' => [
                    ['title' => 'Activer le module', 'body' => 'Sans lui, les routes /api/v1 ne répondent pas. C\'est le prérequis technique du site vitrine.'],
                    ['title' => 'Vérifier la liaison', 'body' => 'La route ping confirme que l\'application répond ; l\'application affiche elle-même un badge d\'état de liaison avec le site.'],
                    ['title' => 'Traiter les demandes entrantes', 'body' => 'Une demande envoyée par l\'API arrive comme réservation à traiter par la réception : elle n\'est jamais confirmée automatiquement.'],
                ],
                'tips' => [
                    'Lecture seule et sans authentification : ces routes n\'exposent que du contenu destiné à être public.',
                    'À ne pas confondre avec l\'API de reporting, protégée par jeton de service, que la console business de l\'ERP consomme.',
                ],
            ],

            'website' => [
                'label'   => 'Site vitrine',
                'tagline' => 'Site public de l\'établissement, alimenté par l\'application et éditable depuis l\'espace éditeur.',
                'icon'    => 'globe',
                'accent'  => 'rose',
                'type'    => 'optionnel',
                'key'     => 'website',
                'depends' => 'Nécessite l\'API d\'intégration active',
                'entry'   => 'Adresse publique de l\'établissement',
                'roles'   => ['site_editor', 'tech_admin'],
                'screens' => [
                    ['label' => 'Site public', 'path' => 'Domaine de l\'établissement', 'desc' => 'Chambres, carte et contenu marketing.'],
                    ['label' => 'Espace éditeur', 'path' => '/espace-editeur (ERP)', 'desc' => 'Édition du contenu du site par un compte dédié.'],
                    ['label' => 'Contenu du site', 'path' => 'Fiche établissement › Contenu du site', 'desc' => 'Le même contenu, éditable côté administration.'],
                ],
                'guide' => [
                    ['title' => 'Activer API puis site web', 'body' => 'Le sélecteur de modules force l\'API quand vous activez le site : le site lit ses données par l\'API de l\'application.'],
                    ['title' => 'Attendre le provisioning', 'body' => 'Première activation : l\'image du site est téléchargée et un troisième container est créé pour l\'établissement. C\'est plus long que l\'activation d\'un module ordinaire.'],
                    ['title' => 'Renseigner le contenu', 'body' => 'Textes, images et sections se saisissent depuis la fiche de l\'établissement, onglet « Contenu du site ».'],
                    ['title' => 'Déléguer l\'édition', 'body' => 'Créez un compte éditeur pour l\'établissement : il se connecte sur une adresse distincte et ne gère que le contenu de son site, sans voir l\'ERP.'],
                ],
                'tips' => [
                    'Le site est un container à part : le mettre à jour est une action distincte de la mise à jour de l\'application.',
                    'Désactiver le module retire le site public — le contenu saisi, lui, reste enregistré.',
                ],
            ],

            'ai' => [
                'label'   => 'Assistant IA (Kuété)',
                'tagline' => 'Assistant conversationnel adossé à Mistral, contextualisé sur l\'établissement et le rôle.',
                'icon'    => 'bot',
                'accent'  => 'fuchsia',
                'type'    => 'config',
                'key'     => null,
                'depends' => 'Nécessite une clé API Mistral dans le container',
                'entry'   => 'Bulle d\'assistance de l\'application',
                'roles'   => ['tous les rôles connectés'],
                'screens' => [
                    ['label' => 'Conversation', 'path' => 'POST /ai-chat', 'desc' => 'Échange avec l\'assistant depuis n\'importe quel écran.'],
                ],
                'guide' => [
                    ['title' => 'Configurer la clé', 'body' => 'Sans clé API Mistral renseignée dans l\'environnement du container, l\'assistant répond par une erreur de configuration. C\'est un réglage technique, pas une case du sélecteur de modules.'],
                    ['title' => 'Poser une question', 'body' => 'L\'assistant reçoit le contexte de l\'utilisateur connecté et de son établissement : les réponses sont donc situées, pas génériques.'],
                    ['title' => 'Garder le fil court', 'body' => 'Seuls les derniers échanges sont transmis à chaque question — les conversations très longues perdent leur début.'],
                ],
                'tips' => [
                    'Le modèle utilisé est configurable ; à défaut, un modèle Mistral léger est appliqué.',
                    'Affiché comme option à la création d\'un établissement, mais l\'activation réelle dépend de la clé API, pas du sélecteur de modules.',
                ],
            ],
        ];
    }
}
