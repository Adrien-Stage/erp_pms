# Installation

Ce guide décrit le déploiement de la console d'administration chez un client, de
zéro jusqu'au premier établissement créé.

## Prérequis

| Élément | Version | Pourquoi |
|---|---|---|
| **Docker Engine** | 20.10+ | La console pilote Docker via son socket |
| **Docker Compose** | v2 (plugin) | Génération et démarrage des établissements |
| **Accès à `ghcr.io`** | — | Téléchargement des images d'établissements |
| Disque | ~2 Go + ~1 Go/établissement | Images et volumes |

Les images GHCR sont **publiques** : aucune authentification n'est requise pour le
`docker pull`.

> **La console doit tourner sur une machine dédiée.** Elle monte le socket Docker,
> ce qui équivaut à un accès root sur l'hôte.

## 1. Réseau Docker partagé

À créer **une seule fois**, avant tout démarrage. La console et tous les
établissements y vivront ensemble.

```bash
docker network create pms
```

Le `docker-compose.yml` le déclare `external: true` : il ne le crée pas, il s'attend
à le trouver.

## 2. Récupérer le code

```bash
git clone <url-du-depot> wetchah_erp
```

## 3. Configurer l'environnement

```bash
cp .env.docker .env
```

Puis éditer le `.env`. Le minimum à renseigner :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<adresse-du-serveur>:8080

# Chemin ABSOLU sur la machine HÔTE — pas dans le conteneur
TENANTS_BASE_PATH=/var/wetchah/tenants

# Doit correspondre au container_name du service app
CMS_CONTAINER_NAME=wetchah_erp-app

# Secrets partagés avec tous les établissements
REPORTING_SECRET=<aléatoire>
ASSISTANCE_SECRET=<aléatoire>

# Clés Web Push (communes à la plateforme)
VAPID_SUBJECT=mailto:support@exemple.com
VAPID_PUBLIC_KEY=<clé publique>
VAPID_PRIVATE_KEY=<clé privée>
```

Générer les secrets :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Référence complète : [Configuration](configuration.md).

## 4. Préparer le dossier des établissements

Le chemin déclaré dans `TENANTS_BASE_PATH` doit exister sur l'hôte et être
inscriptible :

```bash
mkdir -p /var/wetchah/tenants
```

C'est là que la console écrira les `docker-compose` générés, dans un sous-dossier
`.compose/`.

## 5. Construire et démarrer

```bash
docker compose up -d --build
```

Trois services sont déclarés :

| Service | Conteneur | Port | Note |
|---|---|---|---|
| `app` | `wetchah_erp-app` | 8080 → 80 | Alias réseau `MEKA_ERP-app` |
| `db` | `pms-db` | 5433 → 5432 | Déclaré mais **non utilisé** — la console tourne sur SQLite |
| `vite` | `pms-vite` | 5173 | Profil `dev` uniquement, ne démarre pas par défaut |

L'entrypoint ajuste automatiquement les permissions du socket Docker et de
`storage/`. Un supervisord lance PHP-FPM, nginx **et le planificateur Laravel**.

## 6. Générer la clé et initialiser la base

```bash
docker exec wetchah_erp-app php artisan key:generate
```

```bash
docker exec wetchah_erp-app php artisan migrate --seed
```

Le seeder crée deux comptes de démonstration et un établissement fictif.

## 7. ⚠️ Sécuriser les comptes par défaut

Le `UserSeeder` crée deux comptes aux identifiants triviaux :

| Identifiant | Mot de passe | Rôle |
|---|---|---|
| `admin` | `admin` | `tech_admin` |
| `owner` | `owner` | `owner` |

> **Ces comptes ne doivent jamais survivre à une mise en production.** Le compte
> `admin` donne accès au socket Docker de l'hôte. Créer un vrai compte
> administrateur, puis supprimer ou désactiver ceux-ci.

Créer un compte administrateur réel :

```bash
docker exec -it wetchah_erp-app php artisan tinker
```

```php
App\Models\User::create([
    'name'      => 'Nom Prénom',
    'email'     => 'admin@exemple.com',
    'password'  => Hash::make('<mot de passe fort>'),
    'role'      => App\Models\User::ROLE_TECH_ADMIN,
    'is_active' => true,
]);
```

Puis supprimer les comptes de démonstration et l'établissement fictif :

```php
App\Models\Tenant::where('slug', 'demo-resort')->delete();
App\Models\User::whereIn('email', ['admin', 'owner'])->delete();
```

## 8. Vérifier

Ouvrir <http://localhost:8080> — la page de connexion doit s'afficher.

Vérifier ensuite que la console pilote bien Docker :

```bash
docker exec wetchah_erp-app docker ps
```

Si la liste des conteneurs s'affiche, l'accès au socket fonctionne. Sinon, l'ERP ne
pourra créer aucun établissement — voir Dépannage ci-dessous.

Vérifier enfin l'accès au registre :

```bash
docker exec wetchah_erp-app docker pull ghcr.io/adrien-stage/villa_b:latest
```

## 9. Créer le premier établissement

Se connecter en `tech_admin`, puis **Établissements → Créer**.

Points d'attention pour un premier essai :

- **`slug`** — détermine tout le nommage Docker et n'est pas modifiable en pratique.
- **`app_port`** — doit être libre sur l'hôte ; la console le vérifie avant de démarrer.
- **`db_name`** — unique entre établissements.
- **Modules** — cocher `website` active automatiquement `api`.

Le provisioning démarre dès la validation, avec les journaux en direct. Compter
quelques minutes au premier établissement (téléchargement de l'image), puis moins
ensuite : les couches Docker sont mutualisées.

Détail complet : [Provisioning](provisioning.md).

## Après l'installation

- **Sauvegardes** — planifier une sauvegarde par établissement. Le planificateur
  tourne déjà dans le conteneur, aucune entrée cron n'est nécessaire. Voir
  [Exploitation](exploitation.md).
- **Reverse proxy** — pour exposer la console et les établissements en HTTPS avec de
  vrais noms de domaine plutôt que des ports. Non fourni par le projet.
- **Sauvegarde de la console elle-même** — `database/database.sqlite` contient les
  établissements, les comptes et **les mots de passe des bases**. Le perdre signifie
  perdre le pilotage de tous les établissements, même si leurs conteneurs tournent
  encore.

## Dépannage

### `docker ps` échoue depuis le conteneur

Le socket n'est pas accessible. Vérifier qu'il est bien monté :

```bash
docker inspect wetchah_erp-app --format '{{json .Mounts}}'
```

L'entrypoint tente d'aligner le GID du groupe `docker` interne sur celui du socket
hôte. Sur certaines configurations (Docker Desktop, rootless), cet alignement peut
échouer — consulter les journaux de démarrage :

```bash
docker logs wetchah_erp-app
```

### « Impossible de créer le répertoire » au provisioning

`TENANTS_BASE_PATH` n'existe pas ou n'est pas inscriptible **sur l'hôte**. Rappel :
c'est un chemin hôte, pas un chemin conteneur.

### Le port 8080 est déjà pris

Modifier le mapping dans `docker-compose.yml` (`"8090:80"`) et ajuster `APP_URL` en
conséquence.

### Le pull d'image reste bloqué

Comportement connu sur réseau instable : la console détecte le blocage et relance
automatiquement, en reprenant les couches déjà téléchargées. Voir
[Provisioning — résilience du téléchargement](provisioning.md#résilience-du-téléchargement).

## Installation en développement local

Voir [Développement](developpement.md) — l'environnement local monte le code source
depuis l'hôte, ce qui impose des réglages OPcache particuliers.
