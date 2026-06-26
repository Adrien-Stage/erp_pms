# Villa Boutanga PMS — Contexte du projet

## Stack technique
- Laravel 12, Blade, TailwindCSS v4, PostgreSQL
- Alpine.js, Lucide Icons
- Laravel Herd (Windows), pgAdmin

## Ce qui est fait

### Infrastructure
- 16 modèles + trait BelongsToTenant (multitenant)
- 16 migrations en 6 vagues + folio_items
- Seeders : Tenant (Villa Boutanga), Roles, Users (hotel + restaurant), 4 RoomTypes, 10 Rooms, 50 Customers
- Enums : RoomStatus, BookingStatus
- Services : CheckOutService, LoyaltyService
- Table folio_items (suivi prestations séjour)

### Modules UI terminés
- Layout hotel + Sidebar avec sous-menu Réservations (layouts/hotel.blade.php)
- Composants : sidebar-link, stat-card
- Login page animée aux couleurs Villa Boutanga
- Dashboard temps réel (arrivées, départs, occupation, housekeeping)
- Chambres : liste, vue carte, types CRUD, statuts, historique
- Clients : liste avec filtres fidélité, fiche détaillée, historique séjours, progression niveau
- Réservations individuelles :
  - Wizard création 3 étapes (client → dates → chambre)
  - Détail avec folio complet
  - Check-in / Check-out automatisé
  - Ajout/suppression prestations folio
  - Enregistrement paiements
  - Génération facture + vue impression
  - Edit réservation
- Réservations groupe :
  - Création dossier avec contact principal
  - Ajout/retrait chambres individuelles
  - Prestations de groupe (3 modes : par chambre, par personne, global)
  - Paiement global (2 modes : proportionnel, égal)
  - Check-in / Check-out groupé
  - Facture groupe détaillée par chambre
  - Edit + Annulation dossier
- Housekeeping basique (changement statuts)
- Factures : vue individuelle + groupe, impression isolée

### Système RBAC (Role-Based Access Control) — COMPLET ✅

#### Infrastructure RBAC
- **Table roles** : gestion des rôles avec relation many-to-many vers users
- **Table role_user** : pivot table pour l'assignation des rôles
- **Middleware EnsureRoleAccess** : validation des permissions par route
- **Blade directives** : @role, @admin, @hasnotRole pour conditionner l'UI
- **Modèle Role** : avec méthodes helper et relation belongsToMany users
- **Modèle User** : méthodes hasRole(), hasAnyRole(), isAdmin(), canAccessFinancialData(), etc.

#### Rôles définis (10 rôles)
- `admin` : Super administrateur (accès total)
- `manager` : Directeur d'établissement (gestion complète)
- `reception` : Réceptionniste (réservations, clients, check-in/out)
- `accountant` : Comptable (accès données financières)
- `cashier` : Caissier (paiements, factures)
- `housekeeping_leader` : Chef d'équipe housekeeping
- `housekeeping_staff` : Équipe housekeeping
- `restaurant_manager` : Manager restaurant
- `restaurant_staff` : Personnel restaurant
- `maintenance` : Équipe maintenance

#### Protection des routes
- Toutes les routes CRUD protégées par middleware `role:*`
- Routes financières : `accountant,manager,admin`
- Routes chambres : `manager,reception,housekeeping_leader,housekeeping_staff`
- Routes réservations : `reception,manager`
- Routes groupes : `reception,manager`
- Routes housekeeping : `housekeeping_leader,housekeeping_staff,manager`

#### Interface conditionnelle
- **Dashboard** : bouton admin (admin), carte housekeeping (housekeeping)
- **Dashboard** : la 4e carte repose sur `@role(...)` multi-rôles et doit rester visible pour `housekeeping`, `manager` et `reception`
- **Chambres** : boutons CRUD (manager), formulaire statut (housekeeping + manager + reception)
- **Réservations** : boutons création/actions (reception + manager)
- **Groupes** : boutons création/actions (reception + manager)
- **Tests** : 11/11 tests passent ✅

#### Système de Popup d'accès refusé — IMPLÉMENTÉ ✅ (Session 2)
- **Composant Blade** : `resources/views/components/access-denied-popup.blade.php`
  - Popup modal avec design cohérent (rouge/warning, icône, animation)
  - Buttons "Fermer" et "Retour au tableau de bord"
  - Focus trap pour accessibilité
- **Intégration layout** : Popup inclus dans `layouts/hotel.blade.php`
- **Middleware amélioré** : `app/Http/Middleware/EnsureRoleAccess.php`
  - Détecte les requêtes AJAX (header `X-Requested-With: XMLHttpRequest`)
  - Retourne JSON `{"access_denied": true, "message": "..."}` au lieu de `abort(403)`
  - Messages personnalisés selon le rôle requis
- **Interception JavaScript** : Script global dans le popup component
  - Intercepte les réponses fetch (AJAX) avec statut 403
  - Affichage automatique du popup avec message d'erreur
- **Note importante (fluidité)** :
  - Le popup intercepte uniquement les réponses `fetch()` (AJAX) en 403.
  - Les formulaires HTML restent en soumission normale (POST + redirect + flash message), afin d'éviter les "succès invisibles" et les modals qui restent ouverts.

#### Formulaires avec classe `expect-popup`
- `resources/views/rooms/show.blade.php` : formulaire changement statut
- `resources/views/rooms/index.blade.php` : création/modification/suppression chambre + types
- `resources/views/bookings/show.blade.php` : check-in, check-out, annulation

#### Routes de test
- `GET /test-popup` : Route protégée (admin requis) pour tester le popup
- Boutons de test sur le dashboard (bouton rouge + bouton orange)

#### Utilisateurs de test
- **Super Admin** : `admin@villaboutanga.cm` / `password`
- **Manager** : `manager@villaboutanga.cm` / `password`
- **Réceptionniste** : `reception@villaboutanga.cm` / `password`
- **Housekeeping-leader** : `housekeeping.leader@villaboutanga.cm` / `password`
- **Housekeeping-staff** :
  - `housekeeping.staff1@villaboutanga.cm` / `password`
  - `housekeeping.staff2@villaboutanga.cm` / `password`
  - `housekeeping.staff3@villaboutanga.cm` / `password`
- **Chef restaurant** : `restaurant.chief@villaboutanga.cm` / `password`
- **Serveur restaurant** : `restaurant.staff@villaboutanga.cm` / `password`
- **Caissier restaurant** : `restaurant.cashier@villaboutanga.cm` / `password`
- **Manager Boutique** : `shop.manager@villaboutanga.cm` / `password`
- **Caissier Boutique** : `shop.cashier@villaboutanga.cm` / `password`

#### Docs
- `scenario.md` : scénario complet pour une vidéo tutoriel (par rôles / modules)

## Conventions importantes
- Prix stockés en centimes (45000 FCFA = 4500000 en base)
- Affichage : ceil() pour éviter les virgules sur les arrondis TVA
- Tolérance 100 centimes sur validation paiement (arrondis TVA 19,25%)
- Couleurs via variables CSS : text-primary, text-secondary, text-accent, bg-surface-dark
- Icônes via Lucide : <i data-lucide="nom" class="w-4 h-4"></i>
- Recherche live : debounce 400ms sur oninput
- Modals : JS vanilla avec classList.remove('hidden')
- data-* attributes pour passer données aux modals (évite bugs apostrophes)
- Redirects : toujours redirect()->route() jamais back() sur les POST

## Règles métier clés
- Folio : ajout/suppression prestations uniquement en checked_in
- Check-out : solde dû doit être 0 (tolérance 100 centimes)
- Points fidélité : 1pt/1000 FCFA, multiplicateurs par niveau
- Niveaux : bronze(0) silver(5M) gold(20M) platinum(50M) FCFA cumulés
- Chambre après check-out → statut 'cleaning' (pas 'available')
- Groupe : dates bloquées si chambres en checked_in
- Groupe : annulation impossible si status in_house ou completed
- Prestation groupe : label "(groupe GRP-XXXX)" ajouté à la description

## Ce qu'il reste à faire

### Priorité haute
- ✅ Middleware RBAC (limiter accès selon le rôle) — COMPLET
- ✅ Système de Popup d'accès refusé — COMPLET
- ✅ Module Utilisateurs (gestion staff par le manager) — COMPLET
- ✅ Housekeeping complet (liste des priorités, assignation staff) — COMPLET

### Priorité moyenne
- ✅ Module Restaurant (menus + commandes + facturation + portail QR + garde-manger + lien folio) — COMPLET (MVP)
- ✅ Module Shop/POS (articles culturels + boutique) — COMPLET
- ❌ Rapports (taux occupation, revenus, fidélité)
- ❌ PDF facture via DomPDF

### Technique
- ❌ Notifications email (confirmation réservation, rappel départ)
- ❌ BookingFactory + BookingSeeder
- ❌ Refactoring recalculateTotals en méthode unique
- ❌ Notes internes (internal_notes) dans les vues

## Modifications détaillées (Session 2)

### Fichiers CRÉÉS
1. **`resources/views/components/access-denied-popup.blade.php`**
   - Composant Blade pour le popup modal
   - JavaScript vanilla pour gestion affichage/masquage
   - Interception fetch (AJAX) pour afficher le popup sur les erreurs 403
   - Logs de debug pour faciliter le troubleshooting

2. **`popup-access-denied-plan.md`**
   - Plan détaillé de l'implémentation du système de popup

### Fichiers MODIFIÉS

1. **`app/Http/Middleware/EnsureRoleAccess.php`**
   - Ajout détection requêtes AJAX (header `X-Requested-With`)
   - Ajout messages personnalisés par rôle au lieu de messages génériques
   - Retour JSON au lieu de abort(403) pour les requêtes AJAX
   - Structure : détecte si requête AJAX → retourne JSON 403, sinon abort()

2. **`resources/views/layouts/hotel.blade.php`**
   - Ajout composant `<x-access-denied-popup />` après la balise main
   - Positionnement du popup en tant que modal global

3. **`resources/views/rooms/index.blade.php`**
   - Ajout classe `expect-popup` à 7 formulaires :
     - Création chambre
     - Modification chambre
     - Suppression chambre (2 fois - carte + liste)
     - Suppression type
     - Création type
     - Modification type

4. **`resources/views/rooms/show.blade.php`**
   - Ajout classe `expect-popup` au formulaire changement statut
   - Ajout `'reception'` à la directive `@role` (now includes reception pour changer statut)

5. **`resources/views/bookings/show.blade.php`**
   - Ajout classe `expect-popup` aux formulaires :
     - Check-in
     - Check-out
     - Annulation réservation

6. **`resources/views/dashboard.blade.php`**
   - Ajout 2 boutons de test pour le popup :
     - Bouton rouge : test manuel du popup JavaScript
     - Bouton orange : test AJAX avec route protégée
   - Ajout script `testPopup()` pour afficher le popup manuellement

7. **`routes/web.php`**
   - Ajout route test : `GET /test-popup` (middleware auth + role:admin)
   - Ajout middleware `role:manager,reception,housekeeping_leader,housekeeping_staff` à la route `updateStatus` (changement statut chambre)
   - Nommage correct de la route test

8. **`context.md`** (ce fichier)
   - Mise à jour sections RBAC et Interface conditionnelle
   - Ajout section "Système de Popup d'accès refusé"
   - Ajout section "Modifications détaillées"

### Changements de permissions

**Route `/rooms/{room}/status` (changement de statut chambre)**
- **Avant** : Aucun middleware spécifique (hérité du groupe rooms)
- **Après** : Middleware `role:manager,reception,housekeeping_leader,housekeeping_staff`

**Vue changement statut** (`resources/views/rooms/show.blade.php`)
- **Avant** : `@role('housekeeping_leader', 'housekeeping_staff', 'manager')`
- **Après** : `@role('housekeeping_leader', 'housekeeping_staff', 'manager', 'reception')`

### Système de debug intégré
- Console logs détaillés dans le popup component
- Affichage logs lors de :
  - Chargement du script popup
  - Envoi de formulaires
  - Réception de réponses
  - Parsing JSON
  - Affichage popup
- Messages : "Form submitted", "AJAX response received", "Parsed JSON data", etc.

## Structure des fichiers clés
- app/Models/           → tous les modèles
- app/Services/         → CheckOutService, LoyaltyService
- app/Enums/            → RoomStatus, BookingStatus
- app/Http/Controllers/ → BookingController, GroupBookingController,
                          RoomController, CustomerController,
                          HousekeepingController, InvoiceController,
                          UserManagementController
- app/Http/Middleware/  → EnsureRoleAccess.php, AdminOnly.php
- resources/views/layouts/hotel.blade.php  → layout principal (inclut popup)
- resources/views/components/              → sidebar-link, stat-card, access-denied-popup
- resources/views/bookings/                → index, create, select-room, show, edit (formulaires expect-popup)
- resources/views/groups/                  → index, create, show, edit, invoice
- resources/views/customers/               → index, show
- resources/views/rooms/                   → index (formulaires expect-popup), show (formulaires expect-popup)
- resources/views/users/                   → index (vue liste + vue cartes, modales create/edit)
- resources/views/invoices/                → show

## Correctif RBAC UI (Session 3)

### Problème rencontré
- Après l’implémentation du RBAC, certaines cartes Blade conditionnelles n’étaient plus affichées.
- Symptômes observés :
  - la 4e carte du dashboard disparaissait pour `manager` et `reception`
  - la carte "Changer le statut" sur le détail d’une chambre disparaissait aussi
- La route de changement de statut était pourtant bien autorisée pour `manager,reception,housekeeping_leader,housekeeping_staff`.

### Cause racine
- La directive Blade personnalisée `@role(...)` avait été implémentée avec `hasRole(...)`.
- Or les vues utilisent souvent `@role('role1', 'role2', 'role3')`.
- Avec cette implémentation, la directive ne gérait pas correctement les vérifications multi-rôles, ce qui masquait des blocs UI autorisés.

### Correctif appliqué
1. **`app/Providers/AppServiceProvider.php`**
   - Correction de la directive `@role(...)` pour utiliser `hasAnyRole([...])`
   - Correction de `@hasnotRole(...)` pour rester cohérente avec les vérifications multi-rôles

2. **`resources/views/dashboard.blade.php`**
   - Ouverture explicite de la 4e carte aux rôles `housekeeping_leader`, `housekeeping_staff`, `manager` et `reception`
   - Objectif : conserver 4 cartes visibles dans la première section pour les profils métier concernés

### Impact attendu
- Le `manager` et le `receptionniste` revoient la 4e carte du dashboard
- Le `receptionniste` revoit la carte de changement de statut dans `rooms/show`
- Toutes les vues utilisant `@role(...)` avec plusieurs rôles bénéficient désormais du bon comportement

### Validation
- Vérification du code effectuée sur les directives Blade et les vues impactées
- Exécution des commandes Laravel non réalisée dans ce terminal car `php` n’est pas disponible dans le `PATH`
- Si un cache Blade persiste localement, exécuter `php artisan view:clear`

## Tests et validation
- **PHPUnit Tests** : `tests/Feature/AuthorizationTest.php`
  - 11/11 tests passent ✅
  - Couvre tous les rôles et permissions
- **Popup Fonctionnel** : Testé manuellement
   - Bouton test sur dashboard ✅
   - Interception fetch() en 403 ✅
   - Affichage popup ✅
 - **Note** : Les formulaires ne sont plus interceptés en AJAX (fluidité + redirects + flash messages).

## Correctifs fluidité (Session 5)
- Suppression de l'interception globale des formulaires `expect-popup` (elle consommait les redirects et masquait les flash messages).
- Les actions CRUD (ex: chambres) redeviennent des POST classiques: message de succès visible et liste à jour sans rafraichir manuellement.
- Ajout de feedback sur le module Chambres: erreurs visibles dans le modal + réouverture automatique du modal en cas d'erreur.

## Module Utilisateurs (Session 4) — COMPLET ✅

### Fonctionnalités livrées
- Espace manager `users.index` pour gérer le staff du tenant (hors `admin` et `manager`)
- Création de compte staff avec rôle métier, mot de passe, téléphone, statut actif/inactif
- Modification de profil staff (dont reset mot de passe optionnel)
- Activation/désactivation de compte via bouton d'action
- Filtres: recherche live, rôle, statut
- Double affichage: vue liste + vue cartes (toggle par icônes Lucide)
- Messages de succès et d'erreurs visibles après soumission des formulaires

### Sécurité / règles métier
- Routes `users.*` protégées par `middleware('role:manager')`
- Isolation tenant stricte: un manager ne peut agir que sur les users de son tenant
- Interdiction de gérer les profils `admin` et `manager`
- Synchronisation des rôles: colonne `users.role` + pivot `role_user`
- Blocage login des comptes inactifs:
  - si `is_active = false`, connexion refusée
  - message: "Votre compte a ete desactive. Veuillez contacter votre manager."

### Ajustements UX intégrés
- Sidebar: gestion des noms longs (ellipsis `...`) sans casser le layout
- Nom utilisateur tronqué avec `Str::limit(..., 13, '...')`
- Bouton de déconnexion verrouillé en largeur fixe pour éviter les décalages
- Conservation du mode d'affichage (`view=list|cards`) après create/update/toggle

### Fichiers créés/modifiés (module users)
- `app/Http/Controllers/UserManagementController.php` (nouveau)
- `resources/views/users/index.blade.php` (nouveau)
- `routes/web.php` (routes users manager)
- `resources/views/layouts/hotel.blade.php` (sidebar + lien module users)
- `app/Http/Requests/Auth/LoginRequest.php` (blocage compte inactif)


## Housekeeping (Session 5) — COMPLET ✅

### Fonctionnalités livrées
- Liste prioritaire des chambres sales avec score d’urgence
- Règles de priorité: bloquée (critique), arrivée aujourd’hui (haute), arrivée demain (élevée), fallback opérationnel
- Affectation des chambres triées par priorité dans le formulaire manager/leader
- Visualisation des priorités directement dans la liste d’assignation

### Sécurité et cohérence multi-tenant
- Filtrage `tenant_id` renforcé sur les données housekeeping (équipes, assignations, pipeline, suivi)
- Vérification tenant lors de l’assignation d’une équipe
- Contrôle d’accès sur actions terrain (`clean`, `ready`, `issue`) :
  - `manager` et `housekeeping_leader` autorisés globalement
  - staff autorisé uniquement sur les chambres de son équipe

### Fichiers modifiés
- `app/Http/Controllers/HousekeepingController.php`
- `resources/views/housekeeping/index.blade.php`

## Correctif Incident (Session 5.1) — APPLIQUÉ ✅

### Incident
- Erreur fatale PHP sur HousekeepingController:
  - `Namespace declaration statement has to be the very first statement`

### Cause racine
- Présence d’un BOM UTF-8 (`﻿`) avant `<?php` dans `app/Http/Controllers/HousekeepingController.php`.

### Correctif
- Réécriture du fichier en UTF-8 sans BOM.
- Vérification binaire: le fichier commence désormais par `3C 3F 70` (`<?p`).

### Impact
- Le contrôleur Housekeeping se charge correctement.
- Suppression de l’erreur fatale au chargement des routes/vues liées.

## Module Discussions (Session 6) — EN COURS AVANCÉ ✅

### Fonctionnalités livrées
- Interface type messagerie desktop:
  - colonne gauche: liste des conversations
  - zone centrale: messages de la conversation active
  - bouton flottant: démarrer une nouvelle discussion
- Envoi de messages en AJAX + affichage instantané
- Polling des nouveaux messages (sans rechargement)
- Auto-scroll sur le dernier message lors des nouveaux messages
- Compteurs non lus par conversation + point de notification dans la sidebar
- Positionnement du lien Discussions en bas de sidebar (avant le profil)
- État vide si aucune conversation sélectionnée:
  - affichage "Selectionne une conversation"

### Suppression de conversation (workflow demandé)
- Menu options (3 points) par conversation:
  - `Archiver`
  - `Supprimer`
- Popup de suppression avec 2 modes:
  - `Juste pour moi`: masque la conversation pour l'utilisateur courant
  - `Supprimer pour tous`: supprime la conversation des deux côtés
- Actions en AJAX sans recharger la page
- Si la conversation active est supprimée:
  - l'écran repasse immédiatement sur l'état "Selectionne une conversation"

### Correctifs importants appliqués
- Correctif BOM UTF-8 sur `DiscussionController.php` (erreur namespace fatale éliminée)
- Correctif duplication de message:
  - garde anti-duplication côté front sur `data-message-id`
- Correctif menu options qui disparaissait:
  - la liste n'est plus rerendue tant qu'un menu est ouvert
- Correctif réapparition après "supprimer pour moi":
  - à chaque nouveau message, la conversation est réactivée pour les autres participants
  - remise à `null` de `deleted_at` et `archived_at` côté pivot
- Correctif rendu icônes Lucide après rerender dynamique:
  - helper global `window.refreshLucideIcons()`

### Migration de nettoyage ajoutée
- `database/migrations/2026_03_28_172000_cleanup_discussion_deleted_archived_state.php`
- Objectif:
  - corriger les anciens états où `deleted_at` et `archived_at` étaient remplis ensemble
  - conserver `deleted_at`
  - remettre `archived_at` à `null`
- Commande à exécuter localement:
  - `php artisan migrate`

### Fichiers discussion créés/modifiés
- `app/Http/Controllers/DiscussionController.php`
- `app/Models/DiscussionConversation.php`
- `app/Models/DiscussionMessage.php`
- `resources/views/discussions/index.blade.php`
- `resources/js/app.js`
- `routes/web.php`
- `app/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `resources/views/layouts/hotel.blade.php`
- Migrations:
  - `2026_03_28_140000_create_discussion_messages_table.php`
  - `2026_03_28_150000_create_discussion_conversations_table.php`
  - `2026_03_28_150100_create_discussion_conversation_user_table.php`
  - `2026_03_28_150200_add_conversation_id_to_discussion_messages_table.php`
  - `2026_03_28_160000_add_last_read_at_to_discussion_conversation_user_table.php`
  - `2026_03_28_170000_add_archived_at_to_discussion_conversation_user_table.php`
  - `2026_03_28_171000_add_deleted_at_to_discussion_conversation_user_table.php`
  - `2026_03_28_172000_cleanup_discussion_deleted_archived_state.php`

## Module Restaurant (Session 7) — COMPLET ✅

### Menus (back-office)
- Gestion des catégories + articles (CRUD via modales)
- Filtres: recherche, catégorie, type, statut
- Prix: saisi en FCFA, stocké en centimes (x100)
- Accès:
  - lecture: `manager`, `restaurant_chief`, `restaurant_staff`
  - écriture (CRUD): `manager`, `restaurant_chief`

### Portail client (QR)
- URL locale (exemple):
  - `/portal/villa-boutanga/restaurant?table=12`
- Menu mobile + panier + validation commande
- `table_number` obligatoire pour valider une commande

### Commandes restaurant (staff)
- Back-office: liste + détail + changement de statut
- Création manuelle par le staff (client sans QR) via modale panier
- Statuts: `pending`, `confirmed`, `preparing`, `ready`, `served`, `canceled`
- Règles:
  - un serveur peut créer et mettre à jour le statut
  - suppression de commande non exposée (pas de route delete)
  - `table_number` obligatoire

### Facturation restaurant (interne)
- Accès: `manager`, `restaurant_chief`, `cashier`
- Encaissement:
  - `payment_status`: `unpaid|paid|refunded`
  - `payment_method`: `cash|mobile_money|card|room_charge|other`
- Reçu imprimable après paiement
- Mode "Sur chambre" (résident):
  - sélection d'un `booking` en `checked_in`
  - création automatique d'un `FolioItem` de type `restaurant`
  - inclus dans la facture finale au check-out (via `CheckOutService`)

### Lien Sidebar
- Rubrique Restaurant:
  - `Commandes`, `Menus`, `Garde-manger`, `Portail (QR)` (ouvre le portail dans un nouvel onglet)
  - `Facturation` (uniquement manager/chef/cashier)

### Données / tables
- Menus:
  - `restaurant_menu_categories`
  - `restaurant_menu_items`
- Commandes portail/staff:
  - `restaurant_customer_orders` (avec `source` = `portal|staff`, `created_by`)
  - `restaurant_customer_order_items`
- Facturation / liaison hôtel:
  - `restaurant_customer_orders.booking_id` + `folio_item_id` (sur chambre)
- Garde-manger:
  - `restaurant_pantry_categories`
  - `restaurant_pantry_items`
  - `restaurant_pantry_movements`

## Dashboard & Sidebar (Session 7.x) — APPLIQUÉ ✅

### Dashboard personnalisé (par service)
- Le dashboard n'est plus identique pour tous:
  - Hôtel / Réception: arrivées, départs, in-house, occupation + panneaux réservations/statut chambres
  - Housekeeping: cartes housekeeping + panneau "à surveiller"
  - Restaurant: commandes en attente, à servir, impayées (si autorisé), stocks bas + dernières commandes
  - Finance: CA resto du jour + solde hôtel (in-house)
- Implémentation:
  - `app/Http/Controllers/DashboardController.php` construit `cards` et `panels` selon le rôle
  - `resources/views/dashboard.blade.php` affiche dynamiquement

### Sidebar filtrée (par service)
- La sidebar n'affiche que les onglets du service de l'utilisateur connecté:
  - `Général`: dashboard toujours visible
  - `Hôtel`: manager/reception/housekeeping
  - `Restaurant`: manager/restaurant/cashier (avec Facturation réservée)
  - `Gestion`: manager/reception/cashier
- Fichier: `resources/views/layouts/hotel.blade.php`

### Seeders (comptes restaurant)
- Ajout de comptes seedés:
  - `restaurant.chief@villaboutanga.cm` / `password` (role `restaurant_chief`)
  - `restaurant.staff@villaboutanga.cm` / `password` (role `restaurant_staff`)
- Seeder: `database/seeders/UserSeeder.php`

## Module Shop/POS (Session 8) — COMPLET ✅

### Fonctionnalités livrées
- **Gestion des articles** (shop_manager uniquement)
  - Création/modification/suppression articles
  - Catégories d'articles (Sculptures, Textiles, Bijoux, Artisanat, Souvenirs)
  - Prix en FCFA, stocké en centimes
  - Stock avec niveau de réappro
  - Filtres: recherche, catégorie, statut
  - 13 articles de test seedés

- **Point de vente / Commandes** (shop_manager et shop_cashier)
  - Création commande avec panier dynamique
  - Calcul automatique sous-total, TVA (19,25%), total
  - Support clients (nom + téléphone) ou via client existant
  - Mode "Sur chambre" (lien folio) pour clients résidents
  - Méthodes paiement: espèces, mobile money, carte, sur chambre, autre
  - Affichage détail commande avec résumé
  - Statuts: non payée, payée, remboursée
  - Actions: marquer payée, rembourser (+ restauration stock), imprimer

### Sécurité & Règles métier
- Routes protégées par `middleware('role:shop_manager,shop_cashier')`
- Gestion articles réservée à shop_manager
- Décrément stock à la création commande
- Restauration stock en cas de remboursement
- Isolation tenant stricte

### Rôles créés (2 rôles)
- `shop_manager` : Gestion articles et boutique
- `shop_cashier` : Ventes et encaissement

### Utilisateurs seedés
- `shop.manager@villaboutanga.cm` / `password` (role `shop_manager`)
- `shop.cashier@villaboutanga.cm` / `password` (role `shop_cashier`)

### Tables créées
- `shop_categories` : catégories articles
- `shop_products` : articles en vente (price en centimes, stock)
- `shop_orders` : commandes/ventes (subtotal, tax_amount, total_amount)
- `shop_order_items` : lignes commande

### Fichiers créés/modifiés
- Modèles:
  - `app/Models/ShopCategory.php`
  - `app/Models/ShopProduct.php`
  - `app/Models/ShopOrder.php`
  - `app/Models/ShopOrderItem.php`
- Contrôleurs:
  - `app/Http/Controllers/ShopProductController.php`
  - `app/Http/Controllers/ShopOrderController.php`
- Migrations:
  - `2026_04_16_100000_create_shop_categories_table.php`
  - `2026_04_16_100100_create_shop_products_table.php`
  - `2026_04_16_100200_create_shop_orders_table.php`
  - `2026_04_16_100300_create_shop_order_items_table.php`
- Seeders:
  - `database/seeders/ShopSeeder.php` (13 articles test)
  - Modification `database/seeders/UserSeeder.php` (ajout shop users)
  - Modification `database/seeders/RoleSeeder.php` (ajout 2 rôles)
  - Modification `database/seeders/DatabaseSeeder.php` (call ShopSeeder)
- Vues:
  - `resources/views/shop/products/index.blade.php` (liste articles)
  - `resources/views/shop/products/create.blade.php` (créer article)
  - `resources/views/shop/products/edit.blade.php` (modifier article)
  - `resources/views/shop/orders/index.blade.php` (liste commandes)
  - `resources/views/shop/orders/create.blade.php` (POS / créer commande)
  - `resources/views/shop/orders/show.blade.php` (détail commande)
- Routes: `routes/web.php` (groupe `/shop` with CRUD routes)
- Sidebar: `resources/views/layouts/hotel.blade.php` (section Boutique)

### Chargement des données (seeders)
**Important** : Les seeders sont répartis ainsi :
- `RoleSeeder` → crée les rôles (inclus `shop_manager`, `shop_cashier`)
- `UserSeeder` → crée les utilisateurs (inclus `shop.manager@`, `shop.cashier@`)
- `ShopSeeder` → crée catégories et articles boutique (13 produits test)
- `DatabaseSeeder` → appelle tous les seeders

**Commandes à exécuter** :
1. **Première initialisation** (réinitialise la DB) :
   ```bash
   php artisan migrate:fresh --seed
   ```
   Cela exécute toutes les migrations ET tous les seeders (DatabaseSeeder).

2. **Charger uniquement les utilisateurs** (si DB déjà prête) :
   ```bash
   php artisan db:seed --class=UserSeeder
   ```
   Cela charge admin, manager, reception, housekeeping staff, restaurant staff, **et shop staff**.

3. **Charger uniquement les articles boutique** :
   ```bash
   php artisan db:seed --class=ShopSeeder
   ```
   Cela charge **seulement** les catégories et 13 articles (pas les utilisateurs).

## Améliorations UX et Caisse Boutique (Session 9) — COMPLET ✅

### Standardisation de la recherche client
- Création du composant réutilisable global `<x-customer-search>`
- Centralisation du moteur et logique de recherche / création client rapide dans `resources/js/app.js` (`customerSearchDef`).
- Remplacement des formulaires de sélection client statiques au niveau des réservations, factures, et boutique.

### Refonte de l'interface POS (Boutique)
- Réécriture intégrale de `shop/orders/create.blade.php`.
- Abandon des `select` classiques au profit d'un système de panier dynamique en Alpine.js (`app.js` -> `orderItemsDef`).
- UI moderne: rendu visuel sous forme de "ticket de caisse", gestion instantanée du sous-total, de la TVA (19,25%), et ajout multi-articles.

### Système de Gestion de Caisse (Boutique)
- Implémentation stricte d'un modèle comptable (`cash_register_sessions`, `cash_register_disbursements`).
- **Règles imposées** : Impossible pour un caissier ou manager d'effectuer une vente si sa caisse n'a pas étée au préalable "Ouverte" pour la journée.
- **Tableau de clôture interactif (Rapport Z)** :
  - Calcule automatiquement le "Solde théorique" (Fond initial + ventes espèces - décaissements).
  - Demande la saisie du "Solde réel compté" lors de la fermeture de la caisse.
  - Alpine.js compare en direct et identifie l’écart (profit, perte ou juste).
- Historique "Compta Boutique" complet : permet à la direction de visualiser le fond par session à `/shop/cash-register`.

### Indicateurs de Performance (KPIs Dashboard)
- Injection logique des métriques boutique au sein de l'unique contrôleur adaptatif (`DashboardController.php`).
- Les utilisateurs `shop_manager` (et managers globaux) voient maintenant apparaître sur leur page d'accueil :
  - Les indicateurs de Chiffre d'Affaires du jour (+ évolution vs la veille).
  - Le compte des articles et de commandes passées dans la session courante.
  - Un classement des Meilleurs Ventes (Top 3 du mois).
  - Un rapport critique de stocks bas automatique.
  - Les boutons d'Actions Rapides dynamiques "Ouvrir / Fermer caisse".

### Refonte de l'Impression des Reçus (Factures Clients)
- Analyse du modèle "Facture de réservation" format A4.
- Application de ce standard de formatage (Logo, structure en grille détaillée, totaux et signature) exclusif à la version **Imprimée** (`@media print`) de la commande boutique (`shop/orders/show.blade.php`).
- Refonte de la page d'impression autonome de restaurant (`restaurant/billing/receipt.blade.php`) vers ce même standard visuel A4 sans perturber l'interface admin.
