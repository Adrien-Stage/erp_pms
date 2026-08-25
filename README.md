# WeTchah ERP — Console d'administration

Console d'administration d'une plateforme de gestion hôtelière multi-établissements,
déployée *on-premise* chez le client.

**Ce projet n'est pas un logiciel hôtelier.** C'est l'outil qui *fabrique* et
*supervise* les logiciels hôteliers : chaque établissement client reçoit sa propre
application et sa propre base de données, dans ses propres conteneurs Docker,
créés et pilotés depuis cette console.

> **1 établissement = 1 application = 1 base de données.**

---

## Les trois dépôts de la plateforme

WeTchah ERP ne fonctionne pas seul. Il orchestre deux autres dépôts :

| Dépôt | Rôle | Stack |
|---|---|---|
| **`wetchah_erp`** *(ce dépôt)* | La console qui fabrique et supervise les établissements | Laravel 12 + Blade, SQLite |
| [`wetchah_app`](https://github.com/Adrien-Stage/villa_b) | Le PMS livré à chaque établissement (chambres, restaurant, caisse…) | Laravel 12 + Blade, PostgreSQL |
| [`wetchah_site`](https://github.com/clyde237/site_villab) | Le site vitrine public, optionnel, d'un établissement | SvelteKit 2 / Svelte 5 |

Les deux derniers ne sont **jamais clonés** : ils sont publiés en images Docker sur
GHCR par leur CI, et cette console en tire (`docker pull`) un **digest figé** par
établissement.

---

## Les trois espaces de la console

Un seul déploiement, trois publics cloisonnés par rôle :

| Espace | URL | Rôle | À quoi ça sert |
|---|---|---|---|
| **TECH** | `/tech/*` | `tech_admin` | Créer les établissements, superviser les conteneurs, mettre à jour, sauvegarder, assister |
| **BUSINESS** | `/business/*` | `owner` | Le propriétaire consulte ses établissements : revenus, clients, employés, rapports |
| **ÉDITEUR** | `/espace-editeur` | `site_editor` | Éditer le contenu marketing du site vitrine d'un seul établissement |

L'espace éditeur a sa **propre page de connexion**, sans marquage ERP : un éditeur
n'a pas à savoir que la console d'administration existe.

---

## Démarrage rapide

Prérequis : Docker + Docker Compose, et le réseau partagé créé une fois.

```bash
docker network create pms
```

```bash
cp .env.docker .env && docker compose up -d --build
```

```bash
docker exec wetchah_erp-app php artisan migrate --seed
```

La console répond ensuite sur <http://localhost:8080>.

L'installation complète (secrets, comptes, images GHCR, chemins hôte) est décrite
dans **[docs/installation.md](docs/installation.md)**.

---

## Documentation

| Document | Contenu |
|---|---|
| **[Architecture](docs/architecture.md)** | Vue d'ensemble, conteneurs, réseau, flux de données, modèle de données |
| **[Installation](docs/installation.md)** | Déploiement de la console chez un client, de zéro à opérationnel |
| **[Configuration](docs/configuration.md)** | Référence de toutes les variables d'environnement et fichiers `config/` |
| **[Provisioning](docs/provisioning.md)** | Cycle de vie d'un établissement : création, mise à jour, suppression |
| **[Rôles et accès](docs/roles-et-acces.md)** | RBAC, les trois espaces, gestion des comptes, isolation multi-tenant |
| **[Exploitation](docs/exploitation.md)** | Sauvegardes, supervision, mode assistance, diagnostic, incidents courants |
| **[CMS du site vitrine](docs/cms-site-vitrine.md)** | Module `website`, schéma de contenu, API publique consommée par le site |
| **[Développement](docs/developpement.md)** | Environnement local, OPcache, assets, structure du code, dette connue |

Les fichiers `.md` à la racine (`PLAN_REALISATION_ARCHITECTURE.md`,
`INSTRUCTIONS_PROVISIONING.md`, `provisioning-guide.md`, `JOURNAL_DE_BORD.md`…)
sont des **documents de conception historiques**. Ils décrivent des décisions et
des états passés — certains ne reflètent plus le code. La documentation à jour est
celle de `docs/`.

---

## Structure du code

```
app/
├─ Http/Controllers/
│  ├─ AdminAuditController.php    Le contrôleur central (TECH + BUSINESS)
│  ├─ OwnerController.php         Registre des propriétaires
│  ├─ SiteEditorController.php    Espace éditeur + comptes éditeurs
│  └─ TenantUserController.php    Employés, écrits directement dans la base du tenant
├─ Http/Middleware/
│  └─ EnsureRoleAccess.php        RBAC + isolation multi-tenant (alias « role »)
├─ Models/                        Tenant, User, Role, AuditLog, TenantBackup,
│                                 BackupSchedule, AssistanceSession
├─ Services/
│  ├─ TenantProvisioningService.php  Toute la logique Docker
│  ├─ DockerRegistryService.php      Tags et digests GHCR
│  ├─ TenantDatabase.php             Accès PDO aux bases des établissements
│  ├─ TenantBackupService.php        pg_dump / restauration
│  ├─ BusinessReportingClient.php    Agrégation des API de reporting
│  └─ BusinessReportExporter.php     Export Excel des rapports
└─ Support/
   ├─ SiteContentSchema.php       Schéma déclaratif du CMS
   └─ TenantRoles.php             Catalogue des rôles de wetchah_app
```

Point d'entrée principal des routes : [`routes/web.php`](routes/web.php).

---

## Licence

Projet propriétaire. Tous droits réservés.
