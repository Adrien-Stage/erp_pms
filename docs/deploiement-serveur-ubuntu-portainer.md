# Déploiement de l'ERP sur un serveur Ubuntu avec Docker et Portainer

> **Version pas à pas, sans jargon : [GUIDE_DEPLOIEMENT_ERP_WETCHAH.pdf](GUIDE_DEPLOIEMENT_ERP_WETCHAH.pdf)** — mêmes étapes, avec captures, cas d'usage et dépannage. Le présent document en est la référence technique.

Ce guide installe la console WeTchah ERP sur un **serveur Ubuntu** où **Docker** et
**Portainer** sont déjà installés, de la machine nue jusqu'au premier établissement
créé. Il se lit dans l'ordre ; chaque étape se termine par une vérification.

Il complète [Installation](installation.md) (le principe) et
[Déploiement par système d'exploitation](deploiement-par-os.md) (le poste de
développement). Ici, tout est pensé pour un **serveur** : pas de code monté depuis
l'hôte, redémarrage automatique, secrets hors dépôt, sauvegardes.

> **Ce qui a été validé.** Les commandes ont été exécutées pour de vrai sur un Docker
> Engine Linux, dans un bac à sable isolé : construction de l'image, démarrage de la
> pile, contrôle de santé, migration, création du compte, socket Docker laissé en
> `root:docker`, `docker ps` sous `www-data`, génération d'un Compose d'établissement
> avec l'adresse publique, URL d'assets derrière un proxy HTTPS, sauvegarde à chaud,
> archive du volume, persistance après recréation du conteneur. Le provisioning
> complet d'un établissement (téléchargement des images) et **les écrans de Portainer**
> n'ont **pas** été rejoués : les libellés Portainer peuvent varier d'une version à
> l'autre.

---

## 1. Ce que l'on déploie

```
                         Serveur Ubuntu
┌──────────────────────────────────────────────────────────────────────────────┐
│  Portainer (déjà installé)  ── pilote ──►  Docker Engine                      │
│                                              ▲                                │
│   Pile « wetchah-erp »                       │ /var/run/docker.sock           │
│   ┌──────────────────────────┐               │                                │
│   │ wetchah_erp-app  :8080   │───────────────┘   crée et supervise            │
│   │  nginx + php-fpm +       │                                                │
│   │  planificateur Laravel   │      ┌────────────────────────────────────┐   │
│   │  SQLite (volume)         │ ───► │ Établissements (hors Portainer)    │   │
│   └──────────────────────────┘      │  meka-erp-hotel-a-app  :8081       │   │
│            réseau Docker « pms » ◄──│  meka-erp-hotel-a-db   (PostgreSQL)│   │
│                                     │  meka-erp-hotel-b-app  :8082  …    │   │
│                                     └────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────┘
```

- **La console** (`wetchah_erp-app`) est le seul service de la pile. Elle tourne sur
  SQLite : pas de base PostgreSQL à installer pour elle.
- **Les établissements** ne sont **pas** déployés par Portainer : c'est la console qui
  les crée, un couple application + base par établissement, en lançant
  `docker compose` à travers le socket Docker. Portainer les affichera comme des
  conteneurs « hors pile » (§9).
- **Tout vit sur le réseau Docker `pms`**, que l'on crée une fois à la main.

> ### ⚠️ Accès au socket Docker = accès root
>
> La console monte `/var/run/docker.sock` : qui contrôle la console contrôle le
> serveur. Trois règles :
>
> 1. **Une machine dédiée** à la plateforme, pas un serveur qui héberge autre chose.
> 2. L'espace TECH (`/tech`) est réservé à des comptes de confiance, avec des mots de
>    passe forts — jamais `admin`/`admin` (§7).
> 3. Sur un serveur, la pile désactive l'ouverture générale du socket
>    (`ERP_OUVRIR_SOCKET_DOCKER=0`) : le socket reste `root:docker`, seul le groupe
>    `docker` y accède. En développement, l'entrypoint le rend lisible par tous
>    (`chmod 666`) — **ne pas reproduire cela sur un serveur**.

---

## 2. Prérequis

### Serveur

| Élément | Minimum | Confortable |
|---|---|---|
| Système | Ubuntu Server 22.04 ou 24.04 LTS, 64 bits | idem |
| Processeur | 2 cœurs | 4 cœurs |
| Mémoire | 4 Go | 8 Go et plus |
| Disque | 40 Go | 100 Go SSD |
| Réseau | accès sortant à `ghcr.io` (images) et `github.com` (code) | IP fixe ou nom DNS |

Dimensionnement : la console seule tient dans ~0,5 Go. Chaque établissement ajoute
~0,7 à 1 Go de mémoire (application + PostgreSQL) et ~1 Go de disque, plus ses
données. Les images de la plateforme pèsent ~2 Go la première fois.

### Logiciels (déjà en place d'après votre énoncé)

- **Docker Engine 20.10+** avec le plugin **Compose v2** ;
- **Portainer** (CE ou BE), connecté à ce Docker local.

Vérifier :

```bash
docker --version
```

```bash
docker compose version
```

```bash
docker ps --filter name=portainer --format '{{.Names}}  {{.Status}}  {{.Ports}}'
```

> **Ni PHP, ni Composer, ni Node sur le serveur.** Tout est dans l'image
> (PHP 8.4, Composer 2, Node 22).

### Accès

- Un utilisateur du serveur **membre du groupe `docker`** (ou `sudo`) :

  ```bash
  sudo usermod -aG docker $USER
  ```

  Refermer la session pour que le groupe prenne effet.
- L'accès au dépôt `wetchah_erp` : s'il est **privé** sur GitHub, prévoir un **jeton
  d'accès personnel** (*Fine-grained*, lecture seule du contenu du dépôt) ou une clé
  de déploiement.
- Les images des établissements (`ghcr.io/adrien-stage/villa_b`,
  `ghcr.io/clyde237/site_villab`) sont **publiques** : aucune authentification.

---

## 3. Préparer le serveur

### 3.1 Mise à jour et fuseau horaire

```bash
sudo apt update && sudo apt upgrade -y
```

```bash
sudo timedatectl set-timezone Africa/Douala
```

Le fuseau compte : sauvegardes planifiées et journaux se règlent sur l'horloge.

### 3.2 Pare-feu

Si `ufw` est actif, ouvrir **avant** de déployer (adapter à votre plan de ports) :

| Port | Usage |
|---|---|
| 22/tcp | SSH — déjà ouvert |
| 9443/tcp (ou 9000) | Portainer — déjà ouvert |
| 8080/tcp | La console ERP |
| 8081–8199/tcp | Applications des établissements (un port par établissement) |
| 9081–9199/tcp | Sites vitrines des établissements (module `website`, port app + 1000) |

```bash
sudo ufw allow 8080/tcp
```

```bash
sudo ufw allow 8081:8199/tcp
```

```bash
sudo ufw allow 9081:9199/tcp
```

> **Docker contourne `ufw`.** Un port *publié* par Docker est joignable même si `ufw`
> ne l'ouvre pas, car Docker écrit ses propres règles `iptables`. Pour réellement
> restreindre l'accès (par exemple limiter la console à votre réseau), il faut
> filtrer dans la chaîne `DOCKER-USER`, ou publier le port sur une adresse précise
> (`"127.0.0.1:8080:80"`) derrière un reverse proxy (§10).

### 3.3 Réseau Docker partagé

À créer **une seule fois**. La pile le déclare `external: true` : elle s'attend à le
trouver, et tous les établissements s'y brancheront.

```bash
docker network create pms
```

Si le message répond « already exists », c'est bon.

### 3.4 Dossier des établissements

La console écrit les fichiers Compose générés dans ce dossier, **depuis le conteneur**,
et le démon Docker de l'hôte les relit : le chemin doit être **le même des deux
côtés**.

```bash
sudo mkdir -p /var/wetchah/tenants
```

La console tourne en `www-data` (uid 33). Docker crée un dossier hôte en
`root:root` : sans le `chown` ci-dessous, le provisioning échouera sur « Impossible
de créer le répertoire ».

```bash
sudo chown 33:33 /var/wetchah/tenants
```

> **Ce dossier contient des secrets.** Les Compose générés portent les clés
> `APP_KEY`, les mots de passe de base et les secrets de chaque établissement. Droits
> stricts, et **à sauvegarder** (§11).

### 3.5 Vérifier l'accès aux registres

```bash
docker pull ghcr.io/adrien-stage/villa_b:latest
```

Si le téléchargement échoue ou se fige, régler le réseau (proxy, DNS) **avant** de
continuer : sans cela, aucun établissement ne pourra être créé.

---

## 4. Construire l'image de la console

Deux manières de déployer la pile dans Portainer (§6). Dans les deux cas, on
commence par comprendre ce que l'on construit.

### 4.1 Récupérer le code

```bash
sudo mkdir -p /opt/wetchah && sudo chown $USER: /opt/wetchah
```

```bash
git clone https://github.com/Adrien-Stage/erp_pms.git /opt/wetchah/wetchah_erp
```

*(Dépôt privé : `git clone https://<utilisateur>:<jeton>@github.com/Adrien-Stage/erp_pms.git …`,
puis retirer le jeton de l'URL avec `git remote set-url origin https://github.com/Adrien-Stage/erp_pms.git`.)*

```bash
cd /opt/wetchah/wetchah_erp && git checkout main
```

> Le dépôt embarque deux binaires (`docker/app/bin/docker.tgz` et
> `docker/app/bin/docker-compose`, ~140 Mo au total) : le client Docker que la
> console utilise. Le clone est donc volumineux — c'est voulu.

### 4.2 Construire

```bash
cd /opt/wetchah/wetchah_erp
```

```bash
docker compose -f docker-compose.prod.yml build
```

Durée : une dizaine de minutes la première fois (système, extensions PHP,
`composer install`, `npm ci`, compilation des assets), quelques minutes ensuite grâce
au cache. L'image obtenue s'appelle `wetchah-erp:latest`.

```bash
docker image ls wetchah-erp
```

> **L'image est autonome** : code, `vendor/`, `node_modules/` et assets compilés
> (`public/build/`) y sont. Contrairement au poste de développement, il n'y a aucun
> code monté depuis le serveur, et donc rien à compiler à la main après coup.

### 4.3 Générer la clé de l'application

La clé chiffre les sessions et les données sensibles de la console. **Elle ne doit
jamais changer** une fois la console en service, et il faut la conserver à part.

```bash
docker run --rm --entrypoint php wetchah-erp:latest artisan key:generate --show
```

La commande affiche `base64:…`. *(L'option `--entrypoint php` évite que le script de
démarrage de l'image n'imprime ses propres messages avant la clé.)* La noter dans un gestionnaire de mots de passe : c'est
la valeur de `APP_KEY` (§5).

---

## 5. Préparer la configuration

Le modèle est [`.env.production.example`](../.env.production.example). Les variables
sont celles de la pile : on ne crée **pas** de fichier `.env` dans le conteneur.

| Variable | À renseigner | Rôle |
|---|---|---|
| `APP_KEY` | **obligatoire** (§4.3) | Clé de chiffrement |
| `APP_URL` | l'adresse de la console, ex. `http://192.0.2.10:8080` | URL de référence |
| `ERP_PORT` | `8080` | Port publié sur le serveur |
| `TENANTS_PUBLIC_HOST` | **IP ou nom DNS du serveur**, ex. `192.0.2.10` | Adresse sous laquelle un navigateur atteint les établissements |
| `TENANTS_BASE_PATH` | `/var/wetchah/tenants` (§3.4) | Dossier hôte des Compose générés |
| `TZ` | `Africa/Douala` | Fuseau |
| `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` | clés Web Push de l'éditeur | Notifications des établissements |
| `MAIL_MAILER`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` | messagerie | `log` suffit pour démarrer |
| `TRUSTED_PROXIES`, `SESSION_SECURE_COOKIE` | voir §10 | Seulement derrière un reverse proxy HTTPS |

> **`TENANTS_PUBLIC_HOST` est le réglage qui distingue un serveur d'un poste.** Sans
> lui, tous les liens « Ouvrir » de la console, l'`APP_URL` donnée à chaque
> établissement, l'`ORIGIN` du site vitrine et l'adresse d'entrée du mode assistance
> pointent vers `localhost` — c'est-à-dire vers *le poste de l'administrateur*. Le
> site vitrine rejetterait en outre toutes ses soumissions de formulaire.

Clés VAPID (une fois pour la plateforme, à conserver) :

```bash
docker run --rm node:22-slim npx --yes web-push generate-vapid-keys
```

> Les clés VAPID sont **inscrites dans le Compose de chaque établissement** : les
> changer plus tard impose de régénérer chaque Compose et de recréer les conteneurs.
> Les générer une bonne fois.

> **Caractère `$`.** Dans un mot de passe ou une valeur destinée à la pile, un `$` doit
> s'écrire `$$` (interpolation Compose). Une clé `APP_KEY` en base64 n'en contient pas.

---

## 6. Déployer la pile dans Portainer

Dans Portainer : choisir l'environnement **local**, puis **Stacks → Add stack**.
Nom de la pile : **`wetchah-erp`**.

### 6.1 Méthode A — depuis le dépôt Git (recommandée)

Portainer clone le dépôt, construit l'image sur le serveur et suit les mises à jour.

1. **Build method : Repository.**
2. **Repository URL** : `https://github.com/Adrien-Stage/erp_pms`.
3. **Repository reference** : `refs/heads/main`.
4. **Compose path** : `docker-compose.prod.yml`.
5. **Authentication** (dépôt privé) : activer, saisir le nom d'utilisateur GitHub et le
   jeton d'accès personnel.
6. **Environment variables** : ouvrir le mode avancé et coller le contenu de
   `.env.production.example` rempli (§5).
7. *(Facultatif)* **GitOps updates** : Portainer peut redéployer quand `main` change.
   **À éviter en production** tant que vous ne maîtrisez pas la mise à jour (§12) :
   un redéploiement reconstruit l'image et redémarre la console.
8. **Deploy the stack.**

Le premier déploiement construit l'image : le journal de Portainer reste silencieux
plusieurs minutes. Patienter.

### 6.2 Méthode B — image construite en ligne de commande

Si Portainer ne peut pas construire (dépôt inaccessible depuis Portainer, par
exemple), l'image du §4.2 existe déjà sur le serveur. Dans **Add stack → Web
editor**, coller le contenu de `docker-compose.prod.yml` **en retirant le bloc
`build:`** (l'image `wetchah-erp:latest` est déjà là), puis renseigner les variables
d'environnement (§5) et **Deploy the stack**.

### 6.3 Vérifier le démarrage

Dans Portainer : **Containers** → `wetchah_erp-app` doit passer **running**, puis
**healthy** au bout d'une minute (le contrôle de santé appelle `/login`).

En ligne de commande :

```bash
docker ps --filter name=wetchah_erp-app --format '{{.Names}}  {{.Status}}'
```

```bash
docker logs wetchah_erp-app 2>&1 | tail -20
```

Les journaux doivent montrer l'ajustement du socket Docker, la préparation de la base
SQLite puis « Application prête… — /login répond ».

---

## 7. Initialiser la base et créer le vrai compte administrateur

> **Ne pas lancer le seeder en production.** Il crée des comptes `admin`/`admin` et
> `owner`/`owner`, dont le premier donne accès au socket Docker du serveur. On migre
> **sans** `--seed`, puis on crée le compte réel.

### 7.1 Les migrations

```bash
docker exec wetchah_erp-app php artisan migrate --force
```

`--force` est requis en production. Le fichier de base a été créé par l'entrypoint
dans le volume (`/var/www/html/storage/database/database.sqlite`).

### 7.2 Le compte administrateur

```bash
docker exec -it -u www-data wetchah_erp-app php artisan erp:creer-admin
```

La commande demande le nom, l'adresse e-mail et le mot de passe (12 caractères minimum, saisi deux fois, non affiché).
`--reinitialiser` change le mot de passe d'un compte existant. Pas de `tinker` : il garde l'historique des commandes.

---

## 8. Vérifier

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8080/login
```

`200` attendu. Depuis un autre poste, ouvrir `http://<adresse-du-serveur>:8080` : la page
de connexion s'affiche ; se connecter avec le compte du §7.2.

Puis vérifier que la console **pilote bien Docker** — sans cela, elle ne peut créer
aucun établissement :

```bash
docker exec wetchah_erp-app docker ps
```

```bash
docker exec -u www-data wetchah_erp-app docker ps
```

La **seconde** commande est la bonne : le provisioning s'exécute sous `www-data`. Si elle
répond `permission denied` alors que la première réussit, voir §13.

Enfin, l'accès au registre depuis la console :

```bash
docker exec -u www-data wetchah_erp-app docker pull ghcr.io/adrien-stage/villa_b:latest
```

| Contrôle | Attendu |
|---|---|
| `GET /login` | 200 |
| `docker ps` sous `www-data` | liste des conteneurs |
| Socket Docker de l'hôte | `srw-rw----  root  docker` (voir ci-dessous) |
| Redémarrage du serveur | la console remonte seule (`restart: unless-stopped`) |

```bash
ls -l /var/run/docker.sock
```

Si le socket affiche `srw-rw-rw-`, la variable `ERP_OUVRIR_SOCKET_DOCKER=0` n'est pas
prise en compte : le serveur est exposé à tout utilisateur local. Corriger avant de
poursuivre.

---

## 9. Créer le premier établissement

Se connecter en `tech_admin`, puis **Établissements → Créer**.

| Champ | Conseil |
|---|---|
| `slug` | Détermine tout le nommage Docker et ne se change plus : court, minuscules, sans espace |
| `app_port` | Un port libre **dans la plage ouverte au pare-feu** (§3.2) ; la console vérifie qu'il est libre avant de démarrer |
| `db_name` | Unique entre établissements |
| Modules | Cocher `website` active automatiquement `api` |

Le provisioning démarre aussitôt, journaux en direct. Compter quelques minutes au
premier établissement (téléchargement des images), puis moins. Détail :
[Provisioning](provisioning.md).

Une fois terminé :

- le lien « Ouvrir » de la console doit pointer vers `http://<TENANTS_PUBLIC_HOST>:<port>` ;
- dans Portainer, les conteneurs `meka-erp-<slug>-app` et `meka-erp-<slug>-db` apparaissent
  **hors de toute pile** (étiquette *Unmanaged* / « Limited ») : c'est normal.

> **Ne pas gérer les établissements depuis Portainer.** Arrêt, mise à jour de version,
> modules, suppression et sauvegarde passent par la console : elle tient le registre de
> chaque établissement (port, digest épinglé, secrets). Un conteneur supprimé ou mis à
> jour à la main dans Portainer désynchronise la console. Portainer reste idéal pour
> **lire** : journaux, état, consommation.

Les volumes de chaque établissement s'appellent `meka-erp-<slug>_meka_erp_<slug>_pgdata` et
`meka-erp-<slug>_meka_erp_<slug>_storage`. **Ne jamais les supprimer** depuis Portainer (« Volumes »).

---

## 10. HTTPS et nom de domaine (optionnel, recommandé)

Sans reverse proxy, tout circule en clair sur des ports. Pour exposer la console en
HTTPS avec un vrai nom, placer un reverse proxy devant — **Nginx Proxy Manager**, Caddy
ou Traefik, que Portainer installe en quelques clics. Le projet n'en fournit pas.

Configuration côté console, en variables de la pile :

```dotenv
APP_URL=https://erp.exemple.cm
TRUSTED_PROXIES=*
SESSION_SECURE_COOKIE=true
```

`TRUSTED_PROXIES` apprend à Laravel à lire `X-Forwarded-Proto` : sans lui, la console
croit répondre en HTTP et produit des URL d'assets en `http://` que le navigateur
bloque (contenu mixte). Mettre `*` si le proxy est le seul à joindre la console ;
sinon, lister ses adresses.

Côté proxy :

- cible : `wetchah_erp-app`, port **80**, sur le réseau Docker `pms` (brancher le proxy
  sur `pms` pour joindre le conteneur par son nom) ;
- **autoriser les connexions longues** : le provisioning diffuse ses journaux en
  *Server-Sent Events* pendant plusieurs minutes. Désactiver le tamponnage de réponse
  et porter le délai de lecture à au moins 600 s ;
- ne publier le port 8080 que sur `127.0.0.1` une fois le proxy en service
  (`ports: ["127.0.0.1:8080:80"]` dans la pile).

> **Limite actuelle : les établissements restent en HTTP sur leur port.** La console
> génère leur `APP_URL` et l'`ORIGIN` de leur site en `http://<hôte>:<port>`. Les
> placer derrière le proxy en HTTPS demande de faire évoluer le générateur ; en
> attendant, les utilisateurs d'un hôtel ouvrent `http://<hôte>:<port>`.

---

## 11. Sauvegardes : ce qu'il faut protéger

Les sauvegardes que fait la console (menu **Sauvegardes**) concernent les **bases des
établissements**, et sont écrites **sur le disque de la console** — le même serveur.
Elles protègent d'une fausse manipulation, pas de la perte du serveur.

Il faut donc sauvegarder **hors du serveur** quatre éléments :

| Élément | Où | Pourquoi |
|---|---|---|
| Volume `wetchah-erp_erp_storage` | Docker | Base SQLite de la console (établissements, comptes, **mots de passe des bases**), journaux, **sauvegardes des établissements** |
| `/var/wetchah/tenants` | hôte | Les Compose des établissements : leurs `APP_KEY` et secrets. **Perdus, ils rendent les données chiffrées irrécupérables** |
| Volumes `meka-erp-<slug>_meka_erp_<slug>_pgdata` / `_storage` | Docker | Les données réelles de chaque hôtel (en plus des dumps de la console) |
| La valeur de `APP_KEY` et les clés VAPID | gestionnaire de mots de passe | Sans `APP_KEY`, la base SQLite de la console n'est plus lisible |

### Tout en une commande

`scripts/sauvegarder-erp.sh` fait les trois copies ci-dessous (base, volume, dossier des établissements) et garde les 14 dernières ; voir son en-tête pour la planifier avec `cron`.

### Sauvegarde à chaud de la base de la console

Copie cohérente de SQLite, sans arrêter la console :

```bash
docker exec -u www-data wetchah_erp-app php -r '$p = new PDO("sqlite:/var/www/html/storage/database/database.sqlite"); $f = "/var/www/html/storage/app/private/console-" . date("Ymd-His") . ".sqlite"; $p->exec("VACUUM INTO \"$f\""); echo $f, PHP_EOL;'
```

La commande affiche le chemin du fichier. Le récupérer :

```bash
docker cp wetchah_erp-app:/var/www/html/storage/app/private/console-20261015-020000.sqlite /srv/sauvegardes/
```

### Archive des volumes et des Compose

```bash
sudo mkdir -p /srv/sauvegardes
```

```bash
docker run --rm -v wetchah-erp_erp_storage:/donnees:ro -v /srv/sauvegardes:/sortie alpine tar czf /sortie/console-storage-$(date +%F).tgz -C /donnees .
```

```bash
sudo tar czf /srv/sauvegardes/tenants-$(date +%F).tgz -C /var/wetchah tenants
```

À planifier (`crontab -e`) puis à **copier vers une autre machine** (`rsync`, `rclone`,
stockage objet). Une sauvegarde qui reste sur le serveur n'en est pas une.

### Restaurer la console sur un serveur neuf

1. Refaire §3 à §6 avec la **même** `APP_KEY`.
2. Arrêter la pile, restaurer le volume :

   ```bash
   docker run --rm -v wetchah-erp_erp_storage:/donnees -v /srv/sauvegardes:/sortie alpine sh -c 'cd /donnees && tar xzf /sortie/console-storage-AAAA-MM-JJ.tgz'
   ```
3. Restaurer `/var/wetchah/tenants` à son chemin, avec `chown -R 33:33`.
4. Redémarrer la pile. Les établissements, eux, reviennent en restaurant leurs volumes
   puis leurs dumps (menu **Sauvegardes → Restaurer**).

---

## 12. Mettre à jour la console

> **Sauvegarder d'abord** (§11). Une mise à jour peut contenir des migrations.

On met à jour par **versions d'image numérotées** plutôt qu'en réécrivant `latest` :
chaque version reste disponible, et le retour arrière est une simple variable.

### 12.1 Méthode B (image construite sur le serveur)

```bash
cd /opt/wetchah/wetchah_erp && git pull
```

```bash
ERP_VERSION=$(date +%F) docker compose -f docker-compose.prod.yml build
```

Cela produit `wetchah-erp:2026-10-16` (la date du jour). Dans Portainer, **Stacks →
`wetchah-erp` → Editor**, remplacer la variable d'environnement `ERP_VERSION` par cette
valeur (en l'ajoutant si elle n'existe pas), puis **Update the stack**. Docker recrée le
conteneur sur la nouvelle image : la console est indisponible une à deux minutes, les
établissements ne sont pas touchés. Puis migrer :

```bash
docker exec wetchah_erp-app php artisan migrate --force
```

### 12.2 Méthode A (dépôt Git suivi par Portainer)

**Stacks → `wetchah-erp` → Pull and redeploy.** Portainer relit `main`, reconstruit
l'image et recrée le conteneur. Lancer ensuite la migration (commande ci-dessus).
Le retour arrière demande alors de rebâtir depuis un commit précédent : pour des
versions numérotées, préférer la méthode B.

### 12.3 Revenir en arrière

Méthode B : remettre l'ancienne valeur de `ERP_VERSION` dans la pile et
**Update the stack**. Si des migrations avaient été jouées, restaurer aussi la base de
la console (§11) : une migration n'est pas toujours réversible.

> **Le code n'est relu qu'au démarrage du conteneur.** Le serveur tourne avec
> `opcache.validate_timestamps=0`. Une image neuve suffit ; ne jamais modifier le code
> *dans* un conteneur qui tourne.

---

## 13. Dépannage par symptôme

| Symptôme | Cause probable | Que faire |
|---|---|---|
| Le déploiement Portainer échoue avec « APP_KEY est obligatoire » | Variable absente de la pile | Renseigner `APP_KEY` (§4.3), redéployer |
| La page de connexion répond 500 | Assets absents, ou base non migrée | `docker logs wetchah_erp-app` ; relancer `migrate --force` (§7.1) |
| « Database file … does not exist » / « readonly database » | Volume ou droits de `storage/database` | L'entrypoint les prépare : recréer le conteneur ; vérifier que `DB_DATABASE` n'a pas été modifiée |
| `docker ps` sous `www-data` : *permission denied* | Le groupe `docker` interne n'est pas aligné sur celui du socket | `docker logs wetchah_erp-app` (message de l'entrypoint) ; l'hôte ne doit pas être en Docker *rootless* (non supporté) |
| « Impossible de créer le répertoire » au provisioning | `TENANTS_BASE_PATH` absent ou non inscriptible sur l'hôte | `sudo chown 33:33 /var/wetchah/tenants` (§3.4) |
| Le provisioning échoue sur un `docker compose up` : fichier introuvable | Chemin différent des deux côtés du montage | `TENANTS_BASE_PATH` doit être **identique** côté hôte et côté conteneur (déjà le cas dans la pile) |
| Les liens « Ouvrir » mènent à `localhost` | `TENANTS_PUBLIC_HOST` non renseigné | Le définir, redéployer ; régénérer le Compose d'un établissement existant (action **Modules** ou mise à jour de version) |
| Le site vitrine d'un établissement rejette ses formulaires | `ORIGIN` incorrect (ancien Compose) | Après avoir défini `TENANTS_PUBLIC_HOST`, régénérer le Compose de l'établissement |
| « Port … déjà utilisé » | Le port choisi est pris | En choisir un autre dans la plage |
| Établissement injoignable depuis un autre poste | Pare-feu ou port hors plage | §3.2 ; tester `curl http://<hôte>:<port>` depuis le poste |
| Le provisioning se fige sur « Récupération de l'image » | Réseau lent vers `ghcr.io` | La console relance seule (reprise des couches) ; sinon vérifier §3.5 |
| Le journal du provisioning se coupe derrière un proxy | Tamponnage ou délai court | §10 : désactiver le tamponnage, lecture ≥ 600 s |
| Assets en `http://` bloqués derrière HTTPS | `TRUSTED_PROXIES` absent | §10 |
| Après `git pull`, rien ne change | Image non reconstruite, ou pile restée sur l'ancien `ERP_VERSION` | Reconstruire (§12.1) puis changer `ERP_VERSION` dans la pile |
| Le disque se remplit | Images et journaux accumulés | `docker system df` ; `docker image prune` **sans** `-a` (les images des établissements sont épinglées par digest) |

### Commandes de diagnostic

```bash
docker logs -f wetchah_erp-app
```

```bash
docker exec wetchah_erp-app tail -n 50 /var/www/html/storage/logs/laravel.log
```

```bash
docker exec wetchah_erp-app docker ps --filter name=meka-erp
```

```bash
docker inspect wetchah_erp-app --format '{{json .State.Health}}'
```

---

## 14. Liste de contrôle

**Serveur**

- [ ] Ubuntu à jour, fuseau `Africa/Douala`
- [ ] Docker, Compose v2 et Portainer répondent
- [ ] Pare-feu : 8080, 8081–8199, 9081–9199 ouverts (et rien d'autre de superflu)
- [ ] `docker pull ghcr.io/adrien-stage/villa_b:latest` réussit
- [ ] Réseau `pms` créé
- [ ] `/var/wetchah/tenants` créé, propriétaire `33:33`

**Console**

- [ ] Image `wetchah-erp:latest` construite
- [ ] `APP_KEY` générée **et conservée hors du serveur**
- [ ] Clés VAPID générées et conservées
- [ ] Pile `wetchah-erp` déployée, conteneur *healthy*
- [ ] `TENANTS_PUBLIC_HOST` renseigné
- [ ] `migrate --force` exécuté, **sans** seeder
- [ ] Compte administrateur réel créé ; aucun compte `admin`/`admin`
- [ ] Socket Docker `srw-rw----` après démarrage
- [ ] Redémarrage du serveur testé

**Exploitation**

- [ ] Sauvegarde hors serveur planifiée : volume de la console, `/var/wetchah/tenants`, volumes des établissements
- [ ] `APP_KEY`, clés VAPID et mots de passe dans un gestionnaire de secrets
- [ ] Premier établissement créé, lien « Ouvrir » correct
- [ ] (Recommandé) reverse proxy HTTPS devant la console

---

## Annexe — Ce que change `docker-compose.prod.yml` par rapport au développement

| Point | `docker-compose.yml` (développement) | `docker-compose.prod.yml` (serveur) |
|---|---|---|
| Code | Monté depuis l'hôte (`.:/var/www/html`) | Dans l'image, aucun montage |
| `vendor/`, `node_modules/` | Volumes nommés par-dessus le montage | Dans l'image |
| Assets (`public/build/`) | Compilés à la main sur l'hôte | Compilés à la construction de l'image |
| Base de la console | `database/database.sqlite` dans le dépôt | `storage/database/database.sqlite`, dans un volume |
| PostgreSQL `db` et `vite` | Déclarés (non utilisés par la console) | Absents |
| Environnement | `APP_ENV=local`, `.env` | `APP_ENV=production`, `APP_DEBUG=false`, variables de la pile |
| Redémarrage | Manuel | `unless-stopped` + contrôle de santé |
| Socket Docker | Ouvert à tous (`chmod 666`) | Réservé au groupe `docker` |
| Adresses des établissements | `localhost` | `TENANTS_PUBLIC_HOST` |

## Pour aller plus loin

- [Installation](installation.md) — le principe, et la sécurisation des comptes
- [Configuration](configuration.md) — référence de toutes les variables
- [Provisioning](provisioning.md) — cycle de vie d'un établissement
- [Exploitation](exploitation.md) — sauvegardes, supervision, mode assistance
