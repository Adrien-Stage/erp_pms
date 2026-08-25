# Développement

## Stack

| Élément | Version |
|---|---|
| PHP | 8.4 dans Docker (`^8.2` requis par Composer) |
| Laravel | 12 |
| Front | Blade + Alpine.js + Tailwind CSS 4, compilé par Vite 7 |
| Base | SQLite |
| Tests | Pest |
| Style | Laravel Pint |

Paquets notables : `barryvdh/laravel-dompdf` (rapports PDF),
`phpoffice/phpspreadsheet` (exports Excel), `resend/resend-laravel` (e-mails).

## Environnement local

Le développement se fait dans Docker, avec le **code source monté depuis l'hôte** —
ce qui a des conséquences importantes sur la performance, détaillées plus bas.

```bash
docker network create pms
```

```bash
cp .env.docker .env && docker compose up -d --build
```

```bash
docker exec wetchah_erp-app php artisan migrate --seed
```

La console répond sur <http://localhost:8080>, avec les comptes de démonstration
`admin` / `admin` (technique) et `owner` / `owner` (propriétaire).

Le service `vite` est sous profil `dev` et ne démarre pas par défaut :

```bash
docker compose --profile dev up -d vite
```

## ⚠️ Voir ses modifications : `dev-refresh.ps1`

C'est le point le plus déroutant du projet.

**Une modification de code PHP ou de vue Blade n'est pas visible tant que PHP-FPM
n'a pas redémarré.** Ce n'est pas un bug, c'est un arbitrage de performance
délibéré.

```powershell
.\dev-refresh.ps1
```

```powershell
.\dev-refresh.ps1 -Assets
```

### Pourquoi

Le code est monté en *bind mount* depuis Windows. Sur ce pont Windows↔Linux, un
`stat()` coûte environ **4,3 ms** contre **0,005 ms** sur le disque du conteneur,
soit ~870× plus lent.

Avec `opcache.validate_timestamps` activé, OPcache revérifie la date de modification
de **chaque** fichier compilé à chaque requête. Sur ~950 scripts, cela représentait
**~4,1 s par requête** — exactement le temps de chargement mesuré sur `/login`. Le
coût n'était pas la compilation (99,8 % de succès de cache) mais la validation.

D'où `opcache.validate_timestamps=0` dans
[`docker/app/php-opcache.ini`](../docker/app/php-opcache.ini). Une fois un fichier
compilé, PHP ne retouche plus au disque.

> **`view:clear` seul ne suffit pas.** Les vues Blade sont recompilées **au même
> chemin** : OPcache continue de servir l'ancien opcode. Il faut recharger PHP-FPM.

Le script enchaîne donc : vidage des caches Laravel → `supervisorctl restart php-fpm`
→ préchauffage par une requête sur `/login`. Ce préchauffage absorbe le coût de
recompilation des ~950 fichiers plutôt que de le faire subir à la première
navigation.

### Les assets

Même logique : les assets sont servis **compilés** par nginx depuis `public/build/`,
pas par le dev server Vite. En mode dev, Tailwind rescanne les sources à travers le
mount à chaque invalidation — le CSS mettait jusqu'à **95 s** à être servi, ce qui
bloquait l'affichage.

`-Assets` recompile (`npm run build`, ~2 min). Nécessaire uniquement après avoir
ajouté des classes Tailwind ou modifié du CSS/JS.

> Si `public/hot` existe, l'application rebascule sur le dev server Vite — et
> redevient lente. Le script le signale. Pour revenir aux assets compilés : arrêter
> `pms-vite` puis supprimer `public/hot`.

## Structure du code

```
app/
├─ Console/Commands/RunScheduledBackups.php    Commande « backups:run »
├─ Http/
│  ├─ Controllers/
│  │  ├─ AdminAuditController.php   TECH + BUSINESS + provisioning + backups + CMS
│  │  ├─ OwnerController.php        Registre des propriétaires
│  │  ├─ SiteEditorController.php   Espace éditeur + comptes éditeurs
│  │  └─ TenantUserController.php   Employés, écrits dans la base du tenant
│  └─ Middleware/
│     ├─ EnsureRoleAccess.php       RBAC + isolation multi-tenant (alias « role »)
│     ├─ AdminOnly.php              Alias « admin »
│     └─ TrackUserOnlineStatus.php  Marqueur de présence en cache
├─ Models/                          7 modèles
├─ Services/                        Toute la logique métier
└─ Support/
   ├─ SiteContentSchema.php         Schéma déclaratif du CMS
   └─ TenantRoles.php               Catalogue des rôles de wetchah_app
```

### Conventions observées

- **Le contrôleur ne contient aucune commande Docker.** Il valide, enregistre, et
  délègue à `TenantProvisioningService`. À préserver.
- **Commentaires en français, orientés « pourquoi ».** Le code explique les
  arbitrages et les pièges, pas ce que fait la ligne suivante. Les commentaires
  existants sont souvent la seule trace d'un bug corrigé — les lire avant de
  modifier.
- **Nommage mixte.** Les classes et méthodes du cœur Laravel sont en anglais, le
  code plus récent (`SiteEditorController`, `TenantUserController`) utilise des noms
  français (`tenantRattache()`, `autorise`). Suivre la langue du fichier modifié.
- **Nommage Docker figé sur `meka-erp-*`.** Vestige de l'ancien nom du projet,
  **conservé volontairement** pour ne pas casser les déploiements existants. Ne pas
  « corriger ».

## Tests

```bash
php artisan test
```

Ou dans le conteneur :

```bash
docker exec wetchah_erp-app php artisan test
```

Les tests utilisent SQLite **en mémoire** (`phpunit.xml`) : ils ne touchent jamais
`database/database.sqlite`.

### État actuel de la suite

Exécution complète : **44 tests passent, 48 échouent**. La suite est cassée, et il
est important de savoir pourquoi avant de s'y fier.

| Fichier | État | Cause |
|---|---|---|
| `OwnerRegistryTest` | ✅ 14 passent | — |
| `SiteEditorTest` | ✅ 15 passent | — |
| `TenantUserActionsTest` | ✅ 9 passent | — |
| `AuthorizationTest` | ⚠️ 4/11 | Appelle `RoleSeeder` |
| `AdminTenantTest` | ❌ 4 échouent | Appelle `RoleSeeder` |
| `AuditLogTest` | ❌ 5 échouent | Appelle `RoleSeeder` |
| `AdminExportTest` | ❌ 3 échouent | Appelle `RoleSeeder` |
| `BookingCalendarTest` | ❌ Code mort | Teste `Booking`, `Room`, `Customer` |
| `BookingTaxAndBookerTest` | ❌ Code mort | Idem |
| `CashRegisterAuthWorkflowTest` | ❌ Code mort | Idem |
| `CustomerManagementTest` | ❌ Code mort | Idem |
| `ReceptionCashRegisterTest` | ❌ Code mort | Idem |

Deux problèmes distincts, à traiter différemment :

1. **Seeders disparus.** `RoleSeeder`, `TenantSeeder`, `RoomTypeSeeder` et
   `RoomSeeder` ont été supprimés lors du retrait du code hôtelier — seuls
   `DatabaseSeeder` et `UserSeeder` subsistent. Les tests qui les appellent échouent
   sur `Target class [...] does not exist`. **Ces tests couvrent de vraies
   fonctionnalités de la console** : ils sont réparables en remplaçant l'appel au
   seeder par la création directe des données.

2. **Tests hôteliers morts.** 29 tests portent sur `Booking`, `Room`, `Customer`,
   la caisse et la réception — des modèles qui n'existent plus dans ce dépôt. Ils
   sont à supprimer, pas à réparer : leur objet a déménagé dans `wetchah_app`.

> Les trois suites qui passent (`OwnerRegistryTest`, `SiteEditorTest`,
> `TenantUserActionsTest`) sont les plus récentes et correspondent au périmètre
> réel de la console. Ce sont elles qui servent de modèle pour écrire de nouveaux
> tests.

## Style de code

```bash
./vendor/bin/pint
```

```bash
./vendor/bin/pint --test
```

## Dette technique connue

Recensée ici pour éviter les fausses pistes et les « corrections » malencontreuses.

| Sujet | Détail |
|---|---|
| **`AdminAuditController` : 2175 lignes** | Porte TECH, BUSINESS, provisioning, sauvegardes, assistance et CMS. Principal candidat au découpage |
| **Suite de tests cassée** | Voir ci-dessus |
| **Code mort** | `AiToolsService` (jamais appelé), `CheckOutService` et `LoyaltyService` (liés dans `AppServiceProvider`, jamais résolus) |
| **`pms-db` inutilisé** | Service PostgreSQL déclaré dans le Compose ; l'application tourne sur SQLite. Bloc `provisioning.postgres` référencé nulle part |
| **Documents racine obsolètes** | `INSTRUCTIONS_PROVISIONING.md`, `provisioning-guide.md`, `context.md`, `scenario.md`, `plan1.md`… décrivent des états passés |
| **Mot de passe propriétaire `min:4`** | Très faible pour un compte donnant accès à des données financières |
| **Pas de health check périodique** | La santé des établissements n'est évaluée qu'à la demande |
| **`storeTenant()` : validation `db_name`** | Unicité vérifiée côté console, pas côté PostgreSQL |

Le plan de nettoyage de fond est décrit en Phase 5 de
[`PLAN_REALISATION_ARCHITECTURE.md`](../PLAN_REALISATION_ARCHITECTURE.md).

## Travailler sur les autres dépôts

Modifier `wetchah_app` ou `wetchah_site` **n'a aucun effet** sur les établissements
existants : ils tournent sur une image GHCR figée par digest.

Le cycle complet est :

```
modifier → push sur main → la CI publie l'image → mettre à jour l'établissement depuis TECH
```

Pour itérer vite sur `wetchah_app`, travailler en local (Herd) plutôt que via un
établissement provisionné.

## Pour aller plus loin

- [Architecture](architecture.md) — structure et modèle de données
- [Provisioning](provisioning.md) — le service central
- [Configuration](configuration.md) — variables d'environnement
