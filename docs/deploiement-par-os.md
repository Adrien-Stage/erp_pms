# Déploiement — processus complet, par système d'exploitation

Ce document décrit le déploiement de la console **de zéro à la page de connexion
fonctionnelle**, en séparant ce qui est identique partout de ce qui dépend
réellement du système hôte.

Il complète deux documents existants plutôt qu'il ne les remplace :

- [Installation](installation.md) — déploiement chez un client, secrets, mise en production
- [Développement](developpement.md) — travailler au quotidien sur le code

> **Deux étapes manquent aux guides ci-dessus** et sont la cause des deux pannes
> rencontrées lors de la migration Windows → Ubuntu du 28/08/2026 : la création du
> fichier SQLite (§2.8) et la compilation des assets (§2.9). Un clone frais ne
> démarre pas sans elles, quel que soit l'OS.

---

## 1. Ce qui change réellement d'un OS à l'autre

Le processus est identique à cinq points près. Tout le reste — réseau Docker,
`.env`, `docker compose up`, migrations, comptes — se fait avec les mêmes commandes.

| Point | Linux | Windows (Docker Desktop) | macOS (Docker Desktop) |
|---|---|---|---|
| **Moteur Docker** | Docker Engine natif | Docker Desktop + backend WSL2 | Docker Desktop |
| **Ligne de montage hôte** dans `docker-compose.yml` | `/var/wetchah/tenants:/var/wetchah/tenants` | `c:/Users/<user>/Herd:/c/Users/<user>/Herd` | `/Users/<user>/wetchah:/Users/<user>/wetchah` |
| **Propriété des fichiers** | **Réelle** — uid/gid traversent le montage | Simulée — aucun effet | Simulée — aucun effet |
| **Coût du bind mount** | Négligeable | **~870× un accès disque local** | Modéré (VirtioFS) |
| **Script de rafraîchissement** | à faire à la main (§3.7) | `dev-refresh.ps1` | à faire à la main (§3.7) |

Les deux lignes du milieu expliquent presque tous les incidents : la propriété des
fichiers ne mord **que sous Linux**, et le coût du bind mount ne mord **que sous
Windows**. Chaque OS a donc son piège propre, invisible depuis l'autre.

---

## 2. Tronc commun — les dix étapes

À exécuter dans cet ordre, sur tous les systèmes. Les étapes marquées 🔀 renvoient
à la section de votre OS.

### 2.1 Prérequis

| Élément | Version | Pourquoi |
|---|---|---|
| Docker Engine / Desktop | 20.10+ | La console pilote Docker via son socket |
| Docker Compose | v2 (plugin) | Génération et démarrage des établissements |
| Accès à `ghcr.io` | — | Images d'établissements (publiques, sans authentification) |
| Disque | ~2 Go + ~1 Go par établissement | Images et volumes |

**Ni PHP, ni Composer, ni Node ne sont requis sur l'hôte.** Ils vivent dans l'image
(PHP 8.4, Composer 2, Node 22). Toute commande `php artisan` passe par
`docker exec`.

### 2.2 Réseau Docker partagé

Une seule fois par machine. Le `docker-compose.yml` le déclare `external: true` :
il ne le crée pas, il s'attend à le trouver.

```bash
docker network create pms
```

### 2.3 Récupérer le code

```bash
git clone <url-du-depot> wetchah_erp
```

### 2.4 Configurer l'environnement

```bash
cp .env.docker .env
```

Les variables sensibles à l'OS sont `TENANTS_BASE_PATH` (chemin **hôte**, syntaxe
propre à votre système) et `APP_URL`. Le reste — secrets, VAPID, `CMS_CONTAINER_NAME`
— est décrit dans [Configuration](configuration.md).

### 2.5 🔀 Adapter la ligne de montage hôte

Le `docker-compose.yml` contient une ligne dont le chemin **doit** correspondre à
votre système :

```yaml
volumes:
  - /var/wetchah/tenants:/var/wetchah/tenants   # ← à adapter
```

Cette ligne n'a rien d'accessoire. La console écrit les `docker-compose` générés des
établissements dans `TENANTS_BASE_PATH/.compose/<slug>.yml`, **depuis l'intérieur du
conteneur**. Sans montage, ces fichiers disparaissent au premier
`docker compose up --build` — et la suppression d'un établissement, qui fait
`docker compose -f <ce fichier> down -v`, n'a plus rien à lire : elle tombe alors
sur le chemin de repli qui détruit les conteneurs un par un.

Le côté gauche du montage s'écrit dans la syntaxe de l'hôte, le côté droit dans
celle du conteneur. Sous Linux les deux sont identiques ; sous Windows non. Voir
§3.2, §4.2, §5.

`TENANTS_BASE_PATH` doit toujours contenir le chemin **tel que vu dans le
conteneur** — c'est-à-dire le côté droit.

### 2.6 🔀 Créer le dossier des établissements

Sur l'hôte, au chemin choisi ci-dessus. **Sous Linux, ses droits comptent** (§3.3) ;
ailleurs non.

### 2.7 Construire et démarrer

```bash
docker compose up -d --build
```

Trois services sont déclarés : `app` (8080 → 80), `db` (5433, déclaré mais inutilisé
— la console tourne sur SQLite) et `vite` (profil `dev`, ne démarre pas par défaut).

### 2.8 ⚠️ Créer et initialiser la base SQLite

`database/database.sqlite` est ignoré par git (`database/.gitignore` = `*.sqlite*`).
**Un clone frais n'en contient jamais.** Sans ce fichier, la page de connexion
s'affiche mais l'authentification échoue sur
`Database file at path [...] does not exist`.

```bash
docker exec wetchah_erp-app touch /var/www/html/database/database.sqlite
```

🔀 Sous Linux uniquement, ajuster les droits avant de migrer — voir §3.4.

```bash
docker exec wetchah_erp-app php artisan migrate --seed
```

### 2.9 ⚠️ Compiler les assets front

`public/build/` est également ignoré par git. Sans lui, **toute page rendue par
Blade renvoie une erreur 500** :
`Vite manifest not found at: /var/www/html/public/build/manifest.json`.

```bash
docker exec wetchah_erp-app sh -lc 'cd /var/www/html && npm run build'
```

Durée : quelques secondes sous Linux et macOS, environ deux minutes sous Windows —
c'est le bind mount, pas Vite. 🔀 Sous Linux, rendre ensuite les fichiers à votre
utilisateur (§3.4).

À refaire après tout `git pull` touchant `resources/`, ou après un `npm install`.

### 2.10 Vérifier

```bash
curl -o /dev/null -w '%{http_code}\n' http://localhost:8080/login
```

`200` attendu. Puis vérifier que la console pilote bien Docker — sans quoi aucun
établissement ne pourra être créé :

```bash
docker exec wetchah_erp-app docker ps
```

Et l'accès au registre :

```bash
docker exec wetchah_erp-app docker pull ghcr.io/adrien-stage/villa_b:latest
```

Se connecter enfin sur <http://localhost:8080> avec `admin` / `admin`.

### 2.11 Sécuriser les comptes par défaut

Le seeder crée `admin`/`admin` et `owner`/`owner`. **Le compte `admin` donne accès
au socket Docker de l'hôte.** Sur toute machine autre qu'un poste de développement
personnel, la procédure de remplacement est décrite dans
[Installation §7](installation.md).

---

## 3. Linux (Ubuntu / Debian)

C'est la configuration de référence depuis la migration d'août 2026.

### 3.1 Installer Docker

Le paquet `docker.io` des dépôts Ubuntu est souvent trop ancien pour le plugin
Compose v2. Utiliser le dépôt officiel Docker, puis se donner l'accès au démon :

```bash
sudo usermod -aG docker $USER
```

Refermer la session pour que le groupe prenne effet, puis vérifier :

```bash
docker compose version
```

### 3.2 La ligne de montage

Hôte et conteneur portent le même chemin — c'est la forme la plus lisible :

```yaml
- /var/wetchah/tenants:/var/wetchah/tenants
```

```dotenv
TENANTS_BASE_PATH=/var/wetchah/tenants
```

### 3.3 Le dossier des établissements et ses droits

Docker crée le dossier hôte s'il n'existe pas — **en `root:root` `755`**. La console
tourne en `www-data` (uid 33) : elle ne pourra alors pas y écrire, et le
provisioning échouera sur « Impossible de créer le répertoire ». C'est un échec
propre à Linux : sous Docker Desktop, la propriété est simulée et le problème
n'existe pas.

```bash
sudo mkdir -p /var/wetchah/tenants
```

```bash
sudo chown 33:33 /var/wetchah/tenants
```

### 3.4 ⚠️ La propriété des fichiers — le piège n°1

Sous Linux, un bind mount **transmet réellement les uid/gid**. Trois conséquences,
toutes invisibles depuis Windows :

**`docker exec` tourne en root.** Toute commande qui écrit dans le projet crée des
fichiers `root:root` sur votre disque, que votre éditeur ne pourra plus modifier.
Deux parades, selon le cas :

```bash
docker exec wetchah_erp-app su -s /bin/bash www-data -c 'cd /var/www/html && php artisan migrate'
```

```bash
docker exec wetchah_erp-app chown -R 1000:1000 /var/www/html/public/build
```

*(Remplacer `1000:1000` par votre `id -u`:`id -g` si besoin.)*

**SQLite écrit à côté de sa base.** Les fichiers `-wal`, `-shm` et `-journal` sont
créés **dans le dossier** `database/`, pas dans le fichier. Un dossier non
inscriptible par `www-data` produit un `attempt to write a readonly database` en
pleine écriture — bien plus déroutant qu'une erreur au démarrage :

```bash
docker exec wetchah_erp-app sh -lc 'chown 1000:33 /var/www/html/database /var/www/html/database/database.sqlite && chmod 775 /var/www/html/database && chmod 664 /var/www/html/database/database.sqlite'
```

Le dossier reste à vous, le groupe `www-data` peut écrire : les deux côtés
fonctionnent.

**git refuse le dépôt depuis le conteneur.** root y voit des fichiers appartenant à
l'uid 1000 et déclenche sa protection *dubious ownership*, ce qui pollue la sortie
de Composer :

```bash
docker exec wetchah_erp-app git config --global --add safe.directory /var/www/html
```

### 3.5 ⚠️ L'entrypoint ouvre le socket Docker de l'hôte

[`docker/app/entrypoint.sh`](../docker/app/entrypoint.sh) exécute `chmod 666` sur
`/var/run/docker.sock`. Sous Docker Desktop, ce socket est un relais confiné à la
machine virtuelle. **Sous Linux, c'est le vrai socket de l'hôte** : le `chmod` le
rend accessible en écriture à *tout* utilisateur local de la machine — soit
l'équivalent d'un accès root.

Constaté après démarrage : `srw-rw-rw-`, là où l'installation par défaut de Docker
laisse `srw-rw----` `root:docker`.

Sur un poste de développement personnel, c'est acceptable. **Sur une machine
partagée ou un serveur, ça ne l'est pas.** Le `chmod` est d'ailleurs inutile dès
lors que l'entrypoint a correctement aligné le GID du groupe `docker` interne, ce
qui est le cas ici.

### 3.6 OPcache : un réglage hérité de Windows

`opcache.validate_timestamps=0` existe parce qu'un `stat()` coûtait ~4,3 ms à
travers le pont Windows↔Linux, soit ~4,1 s par requête sur 950 fichiers
([php-opcache.ini](../docker/app/php-opcache.ini)).

**Sous Linux, ce coût disparaît** : le montage est natif. Le réglage reste en place
— il n'a pas été rejoué depuis la migration — mais il n'a plus de justification de
performance, et il impose de recharger PHP-FPM à chaque modification de code.

Le passer à `1` rendrait les modifications immédiatement visibles et supprimerait
tout le cycle §3.7. **Ce changement n'a pas été mesuré sur ce poste** : il touche
un fichier partagé par toute l'équipe, dont une partie peut encore être sous
Windows. À traiter comme un chantier à part, pas comme un réglage local.

### 3.7 Équivalent de `dev-refresh.ps1`

Le script est du PowerShell : il ne tourne pas ici. Voici les mêmes enchaînements.

**Après une modification de code PHP ou Blade** (obligatoire tant que §3.6 n'est
pas tranché) :

```bash
docker exec wetchah_erp-app sh -lc 'php artisan view:clear && php artisan route:clear && php artisan config:clear && supervisorctl restart php-fpm && curl -s -o /dev/null http://localhost/login'
```

**Après une modification de CSS/JS ou l'ajout de classes Tailwind :**

```bash
docker exec wetchah_erp-app sh -lc 'cd /var/www/html && npm run build && chown -R 1000:1000 public/build'
```

**Après un `composer require` ou un `npm install`** — les dépendances viennent de
volumes nommés qui ne se mettent pas à jour tout seuls :

```bash
docker compose build app && docker compose down && docker volume rm pms_pms_vendor pms_pms_node_modules && docker compose up -d
```

L'entrypoint compare l'empreinte des verrous figée au build à celle des fichiers
montés, et signale au démarrage un volume devenu obsolète.

### 3.8 Autres distributions

- **Fedora / RHEL / CentOS** — SELinux bloque les bind mounts par défaut. Ajouter
  le suffixe `:z` à chaque montage du `docker-compose.yml` (`- .:/var/www/html:z`).
- **Docker en mode rootless** — non supporté. Le socket est ailleurs
  (`$XDG_RUNTIME_DIR/docker.sock`) et le modèle « le conteneur pilote le Docker de
  l'hôte » ne fonctionne pas tel quel.

---

## 4. Windows (Docker Desktop + WSL2)

Configuration historique du projet, à laquelle une bonne partie de l'architecture
doit sa forme.

### 4.1 Installer Docker Desktop

Backend **WSL2 obligatoire** (pas Hyper-V) : le backend Hyper-V n'expose pas le
socket Docker de la manière qu'attend la console.

Dans Docker Desktop → Settings → Resources → File Sharing, déclarer le dossier
parent du projet **et** celui de `TENANTS_BASE_PATH`.

### 4.2 La ligne de montage

Côté gauche en syntaxe Windows, côté droit en syntaxe conteneur :

```yaml
- c:/Users/<user>/Herd:/c/Users/<user>/Herd
```

```dotenv
TENANTS_BASE_PATH=/c/Users/<user>/Herd/tenants
```

Noter que le montage porte sur le dossier **parent** (`Herd`), et que
`TENANTS_BASE_PATH` désigne un sous-dossier — c'est la forme utilisée jusqu'ici.

### 4.3 Le coût du bind mount, et ce qu'il a imposé

Un `stat()` sur le pont Windows↔Linux coûte ~4,3 ms contre ~0,005 ms sur le disque
du conteneur. Trois pièces de l'architecture n'existent que pour ça :

| Pièce | Ce qu'elle évite |
|---|---|
| Volumes nommés `pms_vendor` / `pms_node_modules` | Lire 10 000 fichiers de `vendor/` à travers le pont |
| `opcache.validate_timestamps=0` | ~4,1 s de `stat()` par requête |
| Assets servis compilés plutôt que par le dev server | Un CSS mettant jusqu'à 95 s à être servi |

**Ne pas les retirer sous Windows.** Ce sont des correctifs mesurés, pas des
préférences — le détail des mesures est dans [Développement](developpement.md).

Alternative de fond : placer le dépôt **dans le système de fichiers WSL2**
(`\\wsl$\Ubuntu\home\<user>\wetchah_erp`) plutôt que sous `C:\Users\`. Le pont
disparaît et les performances rejoignent celles de Linux. En contrepartie,
l'intégration avec les outils Windows natifs (Herd notamment) devient plus lourde.

### 4.4 Outillage

```powershell
.\dev-refresh.ps1
```

```powershell
.\dev-refresh.ps1 -Assets
```

```powershell
.\dev-refresh.ps1 -Deps
```

### 4.5 Pièges spécifiques

- **Plages de ports réservées.** Hyper-V et WSL2 réservent silencieusement des
  plages TCP. Un port d'établissement peut être refusé sans explication —
  le vérifier avec `netsh interface ipv4 show excludedportrange protocol=tcp`.
- **Fins de ligne.** `.gitattributes` impose `eol=lf` sur tout le dépôt : les
  scripts shell arrivent corrects dans l'image. Ne pas forcer
  `core.autocrlf=true` par-dessus.
- **La propriété des fichiers ne veut rien dire.** Tout ce qui est décrit en §3.4
  est sans objet ici — et c'est précisément pour ça que la migration vers Linux a
  révélé ces problèmes d'un coup.

---

## 5. macOS (Docker Desktop)

> **Non validé.** Aucun poste de l'équipe ne tourne sous macOS à ce jour. Cette
> section liste les points à vérifier, pas une procédure éprouvée.

- Autoriser le socket : Settings → Advanced → **« Allow the default Docker socket
  to be used »**. Sans cette option, `docker exec wetchah_erp-app docker ps` échoue
  et aucun établissement ne peut être provisionné.
- Ligne de montage : `- /Users/<user>/wetchah:/Users/<user>/wetchah`, avec
  `TENANTS_BASE_PATH` identique. Le dossier doit être déclaré dans File Sharing.
- Coût du bind mount intermédiaire entre Linux et Windows avec VirtioFS. Les
  volumes nommés de `vendor/` et `node_modules/` restent utiles.
- Sur Apple Silicon, l'image `php:8.4-fpm` existe en arm64 ; **les images GHCR des
  établissements sont à vérifier** — si elles sont publiées en amd64 seulement,
  elles tourneront sous émulation, ou pas du tout.

---

## Annexe A — Les artefacts non versionnés

Trois éléments nécessaires au fonctionnement ne sont **jamais** dans un clone.
C'est la check-list à dérouler sur toute nouvelle machine, quel que soit l'OS :

| Artefact | Ignoré par | Symptôme s'il manque |
|---|---|---|
| `.env` | `.gitignore` | `No application encryption key has been specified` |
| `public/build/` | `.gitignore` | HTTP 500 — `Vite manifest not found` |
| `database/database.sqlite` | `database/.gitignore` | `Database file at path [...] does not exist` |

`vendor/` et `node_modules/` sont eux aussi ignorés, mais **fournis par l'image** via
volumes nommés : rien à faire.

## Annexe B — Équivalences de commandes

| Action | Linux / macOS | Windows |
|---|---|---|
| Rafraîchir après édition de code | §3.7, commande 1 | `.\dev-refresh.ps1` |
| Recompiler les assets | §3.7, commande 2 | `.\dev-refresh.ps1 -Assets` |
| Reconstruire les dépendances | §3.7, commande 3 | `.\dev-refresh.ps1 -Deps` |
| Ouvrir un shell dans le conteneur | `docker exec -it wetchah_erp-app bash` | identique |
| Suivre les journaux | `docker logs -f wetchah_erp-app` | identique |

## Annexe C — Dépannage par symptôme

| Symptôme | Cause | Section |
|---|---|---|
| 500 — `Vite manifest not found` | Assets jamais compilés | §2.9 |
| `Database file at path [...] does not exist` | Fichier SQLite absent | §2.8 |
| `attempt to write a readonly database` | Dossier `database/` non inscriptible par `www-data` | §3.4 |
| Fichiers `root:root` dans le projet | `docker exec` tourne en root | §3.4 |
| « Impossible de créer le répertoire » au provisioning | `TENANTS_BASE_PATH` non inscriptible | §3.3 |
| `docker ps` échoue depuis le conteneur | Socket non monté ou non autorisé | §2.10, §5 |
| Une modification de code reste invisible | `opcache.validate_timestamps=0` | §3.6, §3.7 |
| Port refusé sans explication (Windows) | Plage réservée par Hyper-V | §4.5 |
| `dubious ownership` dans la sortie de Composer | git refuse un dépôt d'un autre uid | §3.4 |
