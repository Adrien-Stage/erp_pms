# Provisioning des établissements

Le provisioning est le cœur de la console : créer, à partir d'un formulaire, un
établissement complet et autonome — image, conteneurs, base, migrations — sans
qu'aucune intervention manuelle sur le serveur ne soit nécessaire.

Toute la logique Docker vit dans un seul service :
[`app/Services/TenantProvisioningService.php`](../app/Services/TenantProvisioningService.php).

> **Règle de conception.** Le contrôleur ne contient aucune commande Docker. Il
> valide, enregistre, et délègue. Toute logique d'infrastructure passe par ce
> service — c'est ce qui permet de la tester et de la faire évoluer sans toucher à
> l'UI.

## Le principe : pull, pas clone

Historiquement, créer un établissement clonait le dépôt `villa_b` puis construisait
l'image localement. C'était lent et coûteux en espace disque : chaque établissement
retéléchargeait le code source complet.

Aujourd'hui, la console **tire une image prébuildée** depuis GHCR, publiée
automatiquement par la CI de `wetchah_app` à chaque push sur `main`. Le dépôt n'est
plus jamais cloné.

Mieux : chaque établissement est **épinglé sur un digest exact**
(`Tenant::docker_image_tag = sha256:…`), pas sur un tag mouvant.

> Un nouveau build de `wetchah_app` **n'impacte aucun établissement existant**. La
> mise à jour est toujours un acte explicite, décidé établissement par établissement,
> et réversible — on peut réépingler une version antérieure.

## Créer un établissement

### Le formulaire

`TECH → Établissements → Créer` ([`create.blade.php`](../resources/views/admin/tenants/create.blade.php)),
en quatre volets validés par
[`storeTenant()`](../app/Http/Controllers/AdminAuditController.php) :

| Volet | Champs | Notes |
|---|---|---|
| **Propriétaire** | Nouveau (nom, e-mail, téléphone, société, nationalité, mot de passe) ou existant | Un nouveau propriétaire est créé avec le rôle `owner` |
| **Technique** | `db_name`, `app_port`, `db_username`, `db_password`, `db_port` | `db_name` et `app_port` sont **uniques** entre établissements |
| **Établissement** | `name`, `slug`, pays, ville, `currency`, adresse, téléphone, e-mail, logo | Le `slug` est **unique** et détermine tout le nommage Docker |
| **Apparence & modules** | 7 couleurs de thème (`#RRGGBB`), cases des modules | `website` force `api` |

À la validation, le `Tenant` est créé en base avec `docker_status = 'creating'`, puis
la page de l'établissement s'ouvre et **le provisioning démarre aussitôt** via SSE.

> Le port applicatif est proposé à partir de `PORT_RANGE_APP_START` (8081 par défaut),
> mais c'est bien l'administrateur TECH qui le choisit — la console ne l'attribue pas
> automatiquement.

### Les six étapes

[`provision()`](../app/Services/TenantProvisioningService.php) enchaîne :

```
1. pullDockerImage()      Résout « latest » en digest, l'épingle, tire l'image
2. pullWebImage()         Idem pour le site vitrine — si module « website »
3. generateDockerCompose() Écrit {TENANTS_BASE_PATH}/.compose/{slug}.yml
4. startContainers()       Vérifie réseau + ports, docker compose up -d
5. waitForDatabase()       Attend le HEALTHCHECK PostgreSQL (24 × 5 s)
6. runMigrations()         Attend que l'app réponde en HTTP (30 × 5 s)
```

Puis le `Tenant` passe en `docker_status = 'running'` avec ses noms de conteneurs et
`provisioned_at`.

### Les journaux en direct (SSE)

L'interface n'affiche pas une barre de progression fictive : elle reçoit les
**vrais journaux** du service, ligne à ligne, via *Server-Sent Events* sur
`GET /tech/establishments/{tenant}/provision/stream`.

Chaque appel `$log($step, $message, $level)` devient un événement SSE. Les mêmes
flux servent la mise à jour de version et celle du site vitrine.

## Le Compose généré

[`generateDockerCompose()`](../app/Services/TenantProvisioningService.php) écrit un
fichier par établissement dans `{TENANTS_BASE_PATH}/.compose/{slug}.yml`.

Chaque fichier déclare son **propre nom de projet** (`name: meka-erp-{slug}`). Sans
ça, tous les Compose du dossier partageraient le même projet et se verraient
mutuellement comme « orphelins » — un `down` sur l'un pouvant alors emporter les
autres.

### Service `app`

Image épinglée par digest, port publié sur l'hôte, et l'environnement complet de
l'établissement :

| Variable | Contenu |
|---|---|
| `APP_NAME`, `APP_KEY`, `APP_URL` | `APP_URL` = `http://localhost:{app_port}`, pour que les URLs d'images pointent vers une adresse réellement joignable |
| `DB_*` | Pointe vers le conteneur `db` de l'établissement |
| `TENANT_SLUG`, `TENANT_CURRENCY` | Identité |
| `TENANT_SETTINGS`, `TENANT_MODULES` | JSON — thème, pays, ville, modules actifs |
| `ASSISTANCE_SECRET` | Vérification des jetons du mode assistance |
| `REPORTING_SECRET` | Protection de l'API de reporting business |
| `VAPID_*` | Notifications Web Push (clés de l'éditeur, communes) |

L'`APP_KEY` est **stable entre deux provisionings** : elle est relue depuis le
Compose existant s'il y en a un. La régénérer invaliderait toutes les sessions et
les données chiffrées de l'établissement.

Volume : `meka_erp_{slug}_storage` monté sur `storage/app/public`.

> **Le logo importé côté console reste côté console.** Il n'est pas transmis au
> conteneur : `settings` est envoyé *sans* la clé `logo`. Le gérant importe son
> propre logo depuis les paramètres de son application.

### Service `db`

PostgreSQL 16, volume `meka_erp_{slug}_pgdata`, `HEALTHCHECK` via `pg_isready`.

> **La base n'est jamais publiée sur l'hôte.** L'application la joint par le réseau
> Docker interne, la console par le nom du conteneur, les sauvegardes par
> `docker exec`. L'exposer créait des conflits de ports sans aucun bénéfice — la
> colonne `db_port` ne sert plus que de repli quand la console tourne hors conteneur.

### Service `web` — seulement si module `website`

Image `wetchah_site`, port `{web_port}:3000` (par défaut `app_port + 1000`).

| Variable | Valeur | Rôle |
|---|---|---|
| `TENANT_SLUG` | slug | Identifie l'établissement auprès du CMS |
| `CMS_API_URL` | `http://{CMS_CONTAINER_NAME}` | Contenu marketing, servi par la console |
| `TENANT_API_URL` | `http://meka-erp-{slug}-app` | Chambres et menu, servis par l'établissement |
| `ORIGIN` | `http://localhost:{web_port}` | **Requis** par SvelteKit adapter-node pour valider les POST — sans lui, toute soumission de formulaire est rejetée en « cross-site » |

## Résilience du téléchargement

GHCR redirige le téléchargement des couches vers une URL Azure signée de courte
durée. Sur un réseau lent ou instable, ce transfert peut **se figer sans jamais
rendre la main** : `docker pull` reste ouvert mais ne progresse plus. Un simple
`exec()` bloquant attendrait alors indéfiniment — c'est le symptôme historique
« bloqué sur Récupération de l'image ».

[`runMonitoredPull()`](../app/Services/TenantProvisioningService.php) surveille donc
le pull plutôt que de l'attendre :

- tant qu'il **progresse**, on le laisse faire — une connexion lente n'est pas un échec ;
- s'il ne produit plus rien pendant `PULL_STALL_TIMEOUT` (120 s), il est considéré
  bloqué, **tué**, puis relancé ;
- 4 tentatives, avec une pause croissante (5 s, 10 s, 15 s) ;
- garde-fou absolu par tentative : `PULL_MAX_SECONDS` (900 s).

Chaque reprise **repart des couches déjà téléchargées** : les tentatives font
avancer le transfert, elles ne le recommencent pas. Un battement de cœur régulier
est émis vers le flux SSE pour que l'interface ne paraisse jamais figée et que la
connexion survive à un proxy coupant les flux inactifs.

## Prévention des conflits de ports

Avant tout `docker compose up`,
[`assertHostPortFree()`](../app/Services/TenantProvisioningService.php) parcourt les
ports publiés par les conteneurs en cours et échoue **tôt**, avec le nom du
conteneur fautif :

> Le port applicatif 8081 est déjà utilisé par le conteneur « meka-erp-hotel-a-app ».
> Choisissez un autre port dans le formulaire de l'établissement.

Si Docker échoue malgré tout sur un bind, l'erreur brute est traduite en message
actionnable plutôt que remontée telle quelle.

## Opérations sur un établissement

| Opération | Route | Effet |
|---|---|---|
| **Provisionner** | `POST .../provision` + flux SSE | Les six étapes ci-dessus |
| **Démarrer** | `POST .../start` | `docker start`. **Si le conteneur est introuvable, re-provisionne intégralement** |
| **Arrêter** | `POST .../stop` | `docker stop` sur app, db et web |
| **Redémarrer** | `POST .../restart` | `docker restart` — db d'abord, puis app |
| **Santé** | `GET .../health` | État des conteneurs, `healthy` si tous `running` |
| **Versions** | `GET .../versions` | Tags disponibles sur GHCR, `latest` en tête |
| **Mettre à jour** | `GET .../update-version/stream` | Réépingle le digest, **recrée le seul conteneur app** |
| **Mettre à jour le site** | `GET .../update-website/stream` | Idem pour le conteneur web |
| **Modules** | `POST .../modules` | Régénère le Compose et recrée les conteneurs concernés |
| **Supprimer** | `DELETE /tech/establishments/{tenant}` | `docker compose down -v` + suppression du fichier Compose |

### Mise à jour de version

`update()` réépingle `docker_image_tag` sur le digest du tag choisi, régénère le
Compose et relance `docker compose up -d`.

> **La base de données et son volume ne sont jamais touchés.** Seul le conteneur
> applicatif est recréé ; son entrypoint rejoue les migrations au démarrage.

C'est réversible : réappliquer un tag antérieur redescend l'établissement de version.

### Application des modules

`applyModules()` ne recrée pas tout : il régénère le Compose avec la nouvelle liste
de modules et relance `up -d`. Docker ne recrée que ce qui a changé.

Si `website` a été **désactivé**, le conteneur web est explicitement supprimé
(`docker rm -f`) avant la régénération, et `docker_web_container` remis à `null`.

### Suppression

`delete()` lance `docker compose down -v` — ce qui détruit **les conteneurs et les
volumes**, donc **toutes les données de l'établissement** — puis supprime le fichier
Compose.

Si le fichier a disparu, un chemin de repli supprime les conteneurs et volumes par
leur nom conventionnel. Un dernier filet supprime le conteneur web s'il avait été
laissé orphelin.

> ⚠️ **Irréversible.** Aucune sauvegarde n'est prise automatiquement avant
> suppression. Prendre un backup manuel au préalable si les données comptent.

## Diagnostic d'un provisioning en échec

En cas d'erreur, `docker_status` passe à `error` et le flux SSE porte le message.

1. **Consulter les journaux SSE** dans la page de l'établissement — le message
   d'erreur est explicite pour les cas connus (port occupé, réseau, pull).
2. **`TECH → Support → Diagnostic`** (`supportDiagnostic`) donne l'état des
   conteneurs et les dernières lignes de journaux.
3. **Inspecter le Compose généré** : `{TENANTS_BASE_PATH}/.compose/{slug}.yml`.
4. **Relancer le provisioning** — l'opération est idempotente : les conteneurs
   existants sont supprimés (`docker rm -f`) avant recréation, et le pull reprend
   les couches déjà téléchargées.

| Symptôme | Cause probable |
|---|---|
| Bloqué sur « Récupération de l'image » | Réseau instable — le mécanisme de reprise devrait s'en charger ; sinon vérifier l'accès à `ghcr.io` |
| « Port … déjà utilisé » | Un autre établissement ou service occupe le port : en choisir un autre |
| « PostgreSQL non disponible après 2 minutes » | Le conteneur db ne démarre pas — vérifier `docker logs meka-erp-{slug}-db` |
| Timeout du health check applicatif | Les migrations et seeders prennent parfois plus de 2,5 min au premier démarrage. Le provisioning continue quand même ; vérifier ensuite l'état |
| Le site vitrine rejette les formulaires | `ORIGIN` incorrect — le `web_port` a changé sans régénération du Compose |

## Pour aller plus loin

- [Architecture](architecture.md) — conteneurs, réseau, conventions de nommage
- [Configuration](configuration.md) — `REGISTRY_IMAGE`, `TENANTS_BASE_PATH`, secrets
- [Exploitation](exploitation.md) — sauvegardes, supervision, mode assistance
