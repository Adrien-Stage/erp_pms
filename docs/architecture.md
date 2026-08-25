# Architecture

## Le principe

Un ERP hôtelier classique est mutualisé : une application, une base, et une colonne
`tenant_id` partout pour séparer les clients. WeTchah ERP fait l'inverse.

> **1 établissement = 1 application = 1 base de données.**

Chaque établissement client tourne dans **son propre couple de conteneurs Docker**
(`app` + `db`), totalement isolé des autres. Aucune donnée n'est partagée, aucune
requête ne traverse deux établissements. Si l'un tombe, les autres ne le savent pas.

La console d'administration — ce dépôt — n'est donc pas un hôtel : c'est **l'usine
qui fabrique les hôtels** et le poste de supervision qui les surveille.

Conséquence directe sur le code : là où `wetchah_app` compte 57 modèles et 85
migrations, la console n'en compte que **7 modèles et 16 migrations**. Elle ne
stocke presque rien — elle orchestre.

## Vue d'ensemble

```
┌──────────────────── Machine hôte (serveur du client) ───────────────────────┐
│                                                                             │
│  /var/run/docker.sock ──────────┐        {TENANTS_BASE_PATH}/.compose/      │
│                                 │           ├─ hotel-a.yml                  │
│                                 │           └─ hotel-b.yml                  │
│                                 │                                           │
│  ┌───────────── Réseau Docker « pms » (externe, partagé) ────────────────┐  │
│  │                                                                       │  │
│  │  ┌─ CONSOLE (ce dépôt) ─────────────┐                                 │  │
│  │  │  wetchah_erp-app     :8080 → 80  │ ← pilote Docker via le socket   │  │
│  │  │    alias « MEKA_ERP-app »        │                                 │  │
│  │  │    SQLite (database.sqlite)      │                                 │  │
│  │  │  pms-db  PostgreSQL 16   :5433   │ (déclaré, non utilisé — cf. bas)│  │
│  │  │  pms-vite                :5173   │ (profil « dev » uniquement)     │  │
│  │  └──────────────────────────────────┘                                 │  │
│  │           │                    │                                      │  │
│  │           │ docker compose     │ PDO + HTTP                           │  │
│  │           ▼                    ▼                                      │  │
│  │  ┌─ ÉTABLISSEMENT « hotel-a » ──────────────────────────────────────┐ │  │
│  │  │  meka-erp-hotel-a-app   :8081 → 80    (image GHCR wetchah_app)   │ │  │
│  │  │  meka-erp-hotel-a-db                  (PostgreSQL 16, non exposé)│ │  │
│  │  │  meka-erp-hotel-a-web   :9081 → 3000  (si module « website »)    │ │  │
│  │  └──────────────────────────────────────────────────────────────────┘ │  │
│  │                                                                       │  │
│  │  ┌─ ÉTABLISSEMENT « hotel-b » ──────────────────────────────────────┐ │  │
│  │  │  … même structure, ports et volumes distincts                    │ │  │
│  │  └──────────────────────────────────────────────────────────────────┘ │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

Tout le monde — console comprise — vit sur le **même réseau Docker `pms`**, déclaré
`external: true`. C'est ce qui permet à la console de joindre chaque établissement
par son nom de conteneur, sans passer par l'hôte.

Ce réseau doit être créé une fois à la main :

```bash
docker network create pms
```

## Comment la console pilote Docker

Le conteneur de la console monte **le socket Docker de l'hôte** :

```yaml
volumes:
  - /var/run/docker.sock:/var/run/docker.sock
```

Son image embarque le client `docker` et le plugin `compose` (voir
[`docker/app/Dockerfile`](../docker/app/Dockerfile)). Elle exécute donc de vraies
commandes `docker pull`, `docker compose up`, `docker exec` — les conteneurs
d'établissements sont créés **à côté** d'elle sur l'hôte, pas à l'intérieur.

C'est puissant et c'est dangereux : **accès au socket Docker vaut accès root sur
l'hôte**. La console doit tourner sur une machine dédiée et son espace TECH être
réservé à des comptes de confiance.

## Les trois canaux vers un établissement

La console parle à chaque établissement de trois façons distinctes, chacune avec
son usage propre :

| Canal | Implémentation | Sert à |
|---|---|---|
| **Docker** | `docker` / `docker compose` via le socket | Créer, démarrer, arrêter, mettre à jour, diagnostiquer |
| **PDO** | Connexion PostgreSQL directe au conteneur `db` | Lire et écrire les employés de l'établissement |
| **HTTP** | `GET /api/reporting/*` avec jeton de service | Agréger les chiffres pour la console business |

### PDO — pourquoi il n'y a pas de synchronisation

[`TenantDatabase`](../app/Services/TenantDatabase.php) ouvre une connexion PostgreSQL
directe vers le conteneur de base de l'établissement, par son **nom de conteneur**
sur le réseau `pms` (pas l'alias générique `db`, qui pointerait vers la base de la
console elle-même). Un repli sur `127.0.0.1:{db_port}` couvre le cas où la console
tourne hors conteneur.

Il n'y a donc **aucune copie** des employés côté console : modifier un utilisateur
depuis l'espace TECH revient à écrire dans la base que `wetchah_app` lit. La
« synchronisation » est immédiate parce qu'il n'y a qu'une seule donnée.

### HTTP — le reporting business

[`BusinessReportingClient`](../app/Services/BusinessReportingClient.php) interroge
`http://meka-erp-{slug}-app/api/reporting/*` avec un en-tête
`Authorization: Bearer {REPORTING_SECRET}`. Ce secret est injecté dans chaque
conteneur au provisioning : console et établissement partagent la même clé.

L'espace business **n'a aucune base de données propre**. Chaque page agrège N appels
HTTP vers N établissements. C'est un choix assumé — au prix d'une contrainte : ces
appels doivent avoir des **timeouts courts**, sans quoi un seul établissement en
panne ralentirait toute la console.

## Modèle de données

La console utilise **SQLite** (`database/database.sqlite`). C'est suffisant : elle
stocke des métadonnées, pas des transactions métier.

```
User ──1───n── Tenant                    Tenant ──1───n── TenantBackup
 │  (owner_id)                             │
 │                                         └──1───1── BackupSchedule
 └── role : tech_admin | owner | site_editor
 └── tenant_id : renseigné pour les site_editor uniquement

AuditLog ──n───1── User          AssistanceSession ──n───1── Tenant, User
```

### Tenant — le modèle central

[`app/Models/Tenant.php`](../app/Models/Tenant.php) porte tout l'état d'un
établissement, en quatre familles :

| Famille | Colonnes |
|---|---|
| **Identité** | `name`, `slug`, `address`, `phone`, `email`, `currency` |
| **Docker / base** | `db_name`, `db_username`, `db_password`, `docker_app_container`, `docker_db_container`, `docker_web_container`, `docker_status`, `docker_image_tag`, `web_image_tag`, `app_port`, `db_port`, `web_port` |
| **Modules** | `modules` (JSON), `api_enabled`, `website_enabled` |
| **Métadonnées** | `owner_id`, `is_active`, `users_count`, `settings` (JSON), `site_content` (JSON), `provisioned_at`, `last_health_check` |

Deux colonnes méritent l'attention :

- **`docker_image_tag`** contient un **digest** (`sha256:…`), pas un tag. Chaque
  établissement est épinglé sur une version exacte de l'image et ne bougera jamais
  tant qu'une mise à jour n'est pas explicitement demandée. Un nouveau build de
  `wetchah_app` n'impacte aucun établissement existant.
- **`site_content`** est un JSON qui contient tout le contenu marketing du site
  vitrine (voir [CMS du site vitrine](cms-site-vitrine.md)). Il vit côté console,
  pas côté établissement.

Le `slug` est la clé de nommage de toute l'infrastructure de l'établissement —
conteneurs, volumes, projet Compose, fichier YAML. Il est **immuable en pratique** :
le changer orphelinerait les conteneurs et volumes existants.

## Conventions de nommage

Elles datent de l'ancien nom du projet (MEKA) et ont été **délibérément conservées**
lors du renommage en WeTchah, pour ne pas casser les déploiements existants :

| Objet | Convention | Exemple (slug `hotel-a`) |
|---|---|---|
| Projet Compose | `meka-erp-{slug}` | `meka-erp-hotel-a` |
| Conteneur app | `meka-erp-{slug}-app` | `meka-erp-hotel-a-app` |
| Conteneur db | `meka-erp-{slug}-db` | `meka-erp-hotel-a-db` |
| Conteneur web | `meka-erp-{slug}-web` | `meka-erp-hotel-a-web` |
| Volume base | `meka_erp_{slug}_pgdata` | `meka_erp_hotel_a_pgdata` |
| Volume storage | `meka_erp_{slug}_storage` | `meka_erp_hotel_a_storage` |
| Fichier Compose | `{TENANTS_BASE_PATH}/.compose/{slug}.yml` | `…/.compose/hotel-a.yml` |

Même logique côté console : le projet Compose s'appelle toujours `pms`, et le
conteneur `wetchah_erp-app` répond aussi à l'**alias réseau `MEKA_ERP-app`** — les
sites d'établissements déjà déployés continuent de joindre le CMS à cette adresse
sans rien changer.

## Modules

Un établissement active un sous-ensemble de modules, stockés dans `Tenant::modules`
et injectés dans son conteneur via `TENANT_MODULES` :

`hotel` · `restaurant` · `shop` · `housekeeping` · `accounting` · `analytics` ·
`discussions` · `ai` · `api` · `website`

Une seule dépendance est appliquée automatiquement
([`applyModuleDependencies()`](../app/Http/Controllers/AdminAuditController.php)) :

> **`website` ⇒ `api`.** Le site vitrine consomme l'API de l'établissement ; l'activer
> sans l'API produirait un site vide. L'inverse est libre : on peut exposer l'API
> pour une intégration tierce sans site public.

Le module `website` est le seul qui change l'**infrastructure** : il ajoute un
troisième conteneur au Compose de l'établissement. Les autres ne changent que ce
que `wetchah_app` affiche.

## Points de dette connus

Documentés ici pour éviter les fausses pistes :

- **`pms-db` (PostgreSQL 16) est déclaré dans le `docker-compose.yml` de la console
  mais inutilisé.** L'application tourne sur SQLite (`DB_CONNECTION: sqlite`), et
  le bloc `provisioning.postgres` de [`config/provisioning.php`](../config/provisioning.php)
  n'est référencé nulle part dans `app/`. Vestige de l'époque où la console devait
  héberger les bases des établissements.
- **`AiToolsService`, `CheckOutService` et `LoyaltyService` sont du code mort.** Ce
  sont des reliquats du code hôtelier ; les deux derniers sont encore liés dans
  `AppServiceProvider` mais jamais résolus. Leur retrait fait partie de la Phase 5
  du plan d'architecture.
- **`AdminAuditController` fait 2175 lignes** et porte à lui seul les espaces TECH
  et BUSINESS, le provisioning, les sauvegardes, l'assistance et le CMS. C'est le
  principal candidat à un découpage.

## Pour aller plus loin

- [Provisioning](provisioning.md) — le cycle de vie détaillé d'un établissement
- [Rôles et accès](roles-et-acces.md) — comment les trois espaces sont cloisonnés
- [Configuration](configuration.md) — toutes les variables d'environnement
