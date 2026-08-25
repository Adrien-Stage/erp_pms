# Configuration

Toute la configuration passe par le `.env`, lu par les fichiers de `config/`.
[`.env.docker`](../.env.docker) sert de modèle pour un déploiement conteneurisé.

## Provisioning

Lu par [`config/provisioning.php`](../config/provisioning.php). C'est le bloc le
plus important : il décrit d'où viennent les images et où vivent les établissements.

| Variable | Défaut | Rôle |
|---|---|---|
| `TENANTS_BASE_PATH` | `/var/meka-erp/tenants` | **Chemin absolu sur la machine hôte** où sont écrits les Compose générés (sous-dossier `.compose/`) |
| `REGISTRY_IMAGE` | `ghcr.io/adrien-stage/villa_b` | Image de l'application établissement (`wetchah_app`) |
| `REGISTRY_IMAGE_WEB` | `ghcr.io/clyde237/site_villab` | Image du site vitrine (`wetchah_site`) |
| `CMS_CONTAINER_NAME` | `wetchah_erp-app` | Nom du conteneur de la console, injecté comme `CMS_API_URL` dans les conteneurs web |
| `DOCKER_NETWORK` | `pms` | Réseau Docker partagé — créé automatiquement s'il n'existe pas |
| `REPORTING_SECRET` | *(vide)* | Jeton de service de l'API de reporting business |
| `PORT_RANGE_APP_START` | `8081` | Premier port applicatif **suggéré** dans le formulaire |
| `PORT_RANGE_DB_START` | `5434` | Premier port de base suggéré |
| `PULL_STALL_TIMEOUT` | `120` | Secondes sans progression au-delà desquelles un `docker pull` est considéré bloqué |
| `PULL_MAX_SECONDS` | `900` | Durée maximale d'une tentative de pull |

> **`TENANTS_BASE_PATH` est un chemin hôte, pas un chemin conteneur.** La console
> écrit les Compose via le socket Docker : c'est le démon Docker de l'hôte qui les
> lira. Un chemin interne au conteneur produirait un `docker compose up` sur un
> fichier introuvable. Sous Windows/WSL2 : `/c/Users/user/Herd/tenants`.

> **La plage de ports n'est qu'une suggestion.** Le service de provisioning ne
> l'utilise pas — le port est choisi par l'administrateur TECH dans le formulaire,
> puis vérifié comme libre avant le démarrage.

### Bloc `postgres` — non utilisé

`config/provisioning.php` déclare aussi `POSTGRES_ADMIN_HOST/PORT/USER/PASS`. **Ce
bloc n'est référencé nulle part dans `app/`.** C'est un vestige de l'époque où la
console devait héberger les bases des établissements ; aujourd'hui chaque
établissement a son propre conteneur PostgreSQL. Ces variables peuvent être laissées
telles quelles ou supprimées.

## Mode assistance

Lu par [`config/assistance.php`](../config/assistance.php).

| Variable | Défaut | Rôle |
|---|---|---|
| `ASSISTANCE_SECRET` | *(vide)* | **Secret partagé** entre la console (qui signe les jetons) et chaque établissement (qui les vérifie) |
| `ASSISTANCE_TTL_MINUTES` | `30` | Durée de vie d'une session d'assistance |

Le secret est injecté dans chaque conteneur au provisioning. **Tant qu'il est vide,
l'ouverture d'une session d'assistance est refusée** avec un message explicite.

Le changer impose de régénérer les Compose et recréer les conteneurs — sinon les
établissements continuent de vérifier avec l'ancienne clé.

Générer une valeur solide :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

## Sauvegardes

Lu par [`config/backups.php`](../config/backups.php).

| Variable | Défaut | Rôle |
|---|---|---|
| `BACKUPS_HOST_PATH` | *(vide)* | Chemin du dossier de sauvegardes **tel que visible depuis l'hôte** |

Purement cosmétique : sert à afficher un chemin exploitable dans les messages de
confirmation. Laisser vide affiche le chemin interne au conteneur.

Exemple : `C:\Users\user\Herd\wetchah_erp\storage\app\private\backups`

## Notifications Web Push

Lues directement via `env()` dans le service de provisioning, puis injectées dans
chaque conteneur d'établissement.

| Variable | Rôle |
|---|---|
| `VAPID_SUBJECT` | `mailto:` de l'éditeur de l'application |
| `VAPID_PUBLIC_KEY` | Clé publique VAPID |
| `VAPID_PRIVATE_KEY` | Clé privée VAPID |

> Ces clés identifient **l'éditeur de l'application**, pas l'établissement : elles
> sont communes à tous les établissements. Un seul jeu à générer pour la plateforme.

## Base de données

La console tourne sur **SQLite** : `DB_CONNECTION=sqlite`, fichier
`database/database.sqlite`.

C'est délibéré et suffisant — la console stocke des métadonnées (une poignée de
tables), pas des transactions métier. Les données réelles vivent dans les bases
PostgreSQL des établissements.

> Le `docker-compose.yml` déclare bien un service `db` (PostgreSQL 16, port 5433),
> mais l'application ne s'y connecte pas. Voir [Architecture — dette connue](architecture.md#points-de-dette-connus).

## Application

| Variable | Valeur recommandée | Note |
|---|---|---|
| `APP_NAME` | `"WeTchah ERP"` | Affiché dans l'interface |
| `APP_ENV` | `production` en production | |
| `APP_DEBUG` | `false` en production | **Ne jamais laisser `true` en production** : les traces exposent les secrets |
| `APP_KEY` | généré | `php artisan key:generate` |
| `APP_URL` | `http://localhost:8080` | Adresse de la console |
| `APP_LOCALE` | `fr` | L'interface est en français |
| `APP_FALLBACK_LOCALE` | `fr` | |

## Messagerie

`MAIL_MAILER=resend` avec `RESEND_API_KEY` (paquet `resend/resend-laravel`).
`MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME` complètent la configuration.

## Assistant IA

| Variable | Rôle |
|---|---|
| `MISTRAL_API_KEY` | Clé API Mistral |
| `MISTRAL_MODEL` | Modèle, ex. `mistral-small-latest` |

> Le service correspondant (`AiToolsService`) **n'est appelé nulle part** dans la
> console. Ces variables sont sans effet ici — elles concernent le module `ai` de
> `wetchah_app`.

## Sessions, cache, files d'attente

La console fonctionne avec les pilotes fichier/base par défaut. Elle ne dépend ni de
Redis ni d'un worker : les variables `REDIS_*` présentes dans `.env.docker` ne sont
pas utilisées.

| Variable | Valeur |
|---|---|
| `SESSION_DRIVER` | `file` |
| `CACHE_STORE` | `file` |
| `QUEUE_CONNECTION` | `database` |

Un **planificateur** est en revanche nécessaire pour les sauvegardes automatiques
(voir [Exploitation](exploitation.md)).

## Récapitulatif : le minimum à définir

Pour un déploiement fonctionnel, ces variables doivent être renseignées
explicitement — les autres ont des valeurs par défaut acceptables :

```dotenv
APP_KEY=              # php artisan key:generate
APP_URL=              # adresse réelle de la console
APP_DEBUG=false       # en production

TENANTS_BASE_PATH=    # chemin HÔTE absolu
CMS_CONTAINER_NAME=   # nom du conteneur de la console
REPORTING_SECRET=     # secret partagé, aléatoire
ASSISTANCE_SECRET=    # secret partagé, aléatoire

VAPID_SUBJECT=
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
```

## Prendre en compte un changement

Après modification du `.env` :

```bash
docker exec wetchah_erp-app php artisan config:clear
```

En développement local, [`dev-refresh.ps1`](../dev-refresh.ps1) le fait déjà — c'est
nécessaire à cause d'OPcache, voir [Développement](developpement.md).

> Changer `ASSISTANCE_SECRET`, `REPORTING_SECRET` ou les clés VAPID ne suffit pas :
> ces valeurs sont **inscrites dans les Compose des établissements**. Il faut
> régénérer chaque Compose (via l'action Modules) et recréer les conteneurs.

## Pour aller plus loin

- [Installation](installation.md) — mise en place complète
- [Provisioning](provisioning.md) — usage détaillé de ces variables
