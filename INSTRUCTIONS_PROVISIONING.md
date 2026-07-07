# Instructions — Système de Provisioning Docker des Établissements
## HotelixOS · Projet `erp_pms` (admin global) + `villa_b` (template)

> Ces instructions décrivent tout ce qui a été conçu dans cette session de travail.
> Applique-les dans l'ordre dans ton environnement local (Antigravity / Herd / Windows).

---

## Contexte

Le système de provisioning permet de créer un établissement hôtelier depuis l'interface admin en :
1. Choisissant la source du code applicatif : **local** (projet déjà sur la machine) ou **GitHub** (clone automatique du repo `villa_b`)
2. Générant automatiquement un `docker-compose.yml` dédié (containers `app` + `db` isolés par établissement)
3. Démarrant les containers et exécutant les migrations/seeders
4. Diffusant les logs en temps réel dans l'UI via **Server-Sent Events (SSE)**

---

## Partie 1 — Projet admin (`erp_pms`)

### 1.1 Fichiers à créer / remplacer

#### `app/Services/TenantProvisioningService.php` ← **NOUVEAU**

Colle le contenu du fichier `TenantProvisioningService.php` fourni.

Ce service contient toute la logique Docker. Il est injecté par Laravel dans le contrôleur via l'injection de dépendances. **Ne pas modifier le contrôleur pour y remettre de la logique Docker** — tout passe par ce service.

---

#### `config/provisioning.php` ← **NOUVEAU**

Colle le contenu du fichier `provisioning.php` fourni dans `config/`.

Ce fichier lit les variables d'environnement suivantes :

| Variable | Description | Ton cas (Windows/Herd) |
|---|---|---|
| `TENANTS_BASE_PATH` | Chemin hôte absolu où stocker les codes clonés | `/c/Users/user/Herd/tenants` |
| `TEMPLATE_GITHUB_URL` | URL du repo template à cloner | `https://github.com/Adrien-Stage/villa_b.git` |
| `DOCKER_NETWORK` | Réseau Docker partagé entre tous les containers | `pms` |
| `POSTGRES_ADMIN_HOST` | Hôte PostgreSQL vu depuis le container admin | `db` |
| `POSTGRES_ADMIN_PORT` | Port PostgreSQL | `5432` |
| `POSTGRES_ADMIN_USER` | Superutilisateur PostgreSQL | `pms` |
| `POSTGRES_ADMIN_PASS` | Mot de passe superutilisateur | `secret` |
| `PORT_RANGE_APP_START` | Premier port app suggéré dans le formulaire | `8081` |
| `PORT_RANGE_DB_START` | Premier port DB suggéré | `5434` |

---

#### `.env` — Variables à ajouter

Ajoute ce bloc à la fin de ton `.env` (adapte `TENANTS_BASE_PATH` à ta machine) :

```dotenv
# ─── HotelixOS Provisioning ───────────────────────────────────────────────────
TENANTS_BASE_PATH=/c/Users/user/Herd/tenants
TEMPLATE_GITHUB_URL=https://github.com/Adrien-Stage/villa_b.git
DOCKER_NETWORK=pms
POSTGRES_ADMIN_HOST=db
POSTGRES_ADMIN_PORT=5432
POSTGRES_ADMIN_USER=pms
POSTGRES_ADMIN_PASS=secret
PORT_RANGE_APP_START=8081
PORT_RANGE_DB_START=5434
```

> **Note sur `TENANTS_BASE_PATH`** : c'est le chemin **sur ta machine hôte**, pas dans le container.
> Sous Windows avec WSL2, les chemins Windows s'écrivent `/c/Users/...` dans les volumes Docker.
> Ce dossier sera créé automatiquement s'il n'existe pas.

---

#### `app/Http/Controllers/AdminAuditController.php` ← **MODIFIÉ**

Remplace le fichier par le `AdminAuditController.php` fourni.

**Changements principaux par rapport à l'original :**

- `storeTenant()` : ne fait plus de `docker run` directement. Crée le `Tenant` en base avec `docker_status = 'creating'`, puis redirige vers `show` avec le flag `start_provisioning = true`.
- `provisionTenantStream()` : **nouvelle méthode SSE** — le client JS s'y connecte pour recevoir les logs en temps réel.
- `startTenant()`, `stopTenant()`, `restartTenant()` : délèguent au `TenantProvisioningService`.
- `healthCheckTenant()` : interroge le service et met à jour `docker_status` + `last_health_check` en base.
- Validation enrichie : `source_type` (required, in:local,github) et `source_path` (required_if:source_type,local).

---

#### `database/migrations/2026_06_28_100000_add_source_to_tenants_table.php` ← **NOUVEAU**

Colle le contenu de la migration fournie dans `database/migrations/`.

Elle ajoute deux colonnes à la table `tenants` :
- `source_type` (string, défaut `'github'`) : `'local'` ou `'github'`
- `source_path` (string, nullable) : chemin absolu hôte, uniquement si `source_type = 'local'`

**Puis lance la migration :**
```bash
php artisan migrate
```

---

#### `app/Models/Tenant.php` ← **MODIFIÉ**

Ajoute `source_type` et `source_path` dans le tableau `$fillable` :

```php
// Source du code applicatif
'source_type',  // 'local' | 'github'
'source_path',  // chemin absolu hôte (uniquement si source_type = 'local')
```

---

#### `routes/web.php` ← **MODIFIÉ**

Ajoute la route SSE dans le groupe `tech.` (juste après la route `provision`) :

```php
Route::post('/establishments/{tenant}/provision', [AdminAuditController::class, 'provisionTenant'])->name('establishments.provision');
Route::get('/establishments/{tenant}/provision/stream', [AdminAuditController::class, 'provisionTenantStream'])->name('establishments.provision.stream');
```

Le fichier complet fourni est déjà correct.

---

### 1.2 Vider le cache de config après ces changements

```bash
php artisan config:clear
php artisan route:clear
```

---

## Partie 2 — Template établissement (`villa_b`)

Ces fichiers sont à commiter dans le **repo `villa_b`**, pas dans `erp_pms`.

### 2.1 Fichiers à créer

#### `Dockerfile` ← **NOUVEAU** (à la racine de `villa_b`)

Colle le contenu du `Dockerfile.template` fourni.

Ce Dockerfile :
- Base PHP 8.3-fpm
- Installe nginx, supervisor, Git, extensions PHP (pdo_pgsql, zip, gd, mbstring)
- Installe Composer et Node.js 22
- Copie le code, installe les dépendances, build les assets Vite
- Lance `entrypoint.sh` au démarrage

---

#### `docker/nginx.conf` ← **NOUVEAU**

Colle le contenu du `nginx.conf.template` fourni dans `docker/nginx.conf`.

Config nginx minimaliste pour une app Laravel : `try_files` vers `index.php`, cache des assets statiques, blocage des fichiers sensibles.

---

#### `docker/supervisord.conf` ← **NOUVEAU**

Colle le contenu du `supervisord.conf.template` fourni dans `docker/supervisord.conf`.

Gère deux processus : `php-fpm` et `nginx`, avec logs séparés.

---

#### `docker/entrypoint.sh` ← **NOUVEAU**

Colle le contenu de `entrypoint.sh` fourni dans `docker/entrypoint.sh`.

Ce script :
1. Attend que PostgreSQL soit disponible (`pg_isready`, 30 tentatives × 3s)
2. Génère une `APP_KEY` si absente
3. Lance `config:cache`, `route:cache`, `view:cache`
4. Démarre supervisord

> **Important** : rendre le script exécutable avant de commiter :
> ```bash
> chmod +x docker/entrypoint.sh
> git add Dockerfile docker/
> git commit -m "feat: add Docker support for HotelixOS provisioning"
> git push
> ```

---

### 2.2 Ce qui n'est PAS encore fait dans `villa_b`

Le template contient encore le trait `BelongsToTenant` et les colonnes `tenant_id` dans ses modèles et migrations (36 fichiers concernés). Ce nettoyage est une tâche séparée (Phase 2 du plan d'implémentation).

Pour l'instant, le provisioning fonctionne quand même — la BD est isolée par container, donc le `tenant_id` ne cause pas de conflit. Le nettoyage est à faire avant la mise en production.

---

## Partie 3 — Ce qui reste à faire côté UI

### 3.1 Formulaire de création (`create.blade.php`)

Ajouter dans l'étape 2 (Configuration technique) un champ de sélection de la source :

```html
<!-- Source du template applicatif -->
<div x-data="{ sourceType: 'github' }">
    <label>Source du code applicatif</label>

    <div>
        <label>
            <input type="radio" name="source_type" value="github"
                   x-model="sourceType" checked>
            GitHub (clone automatique depuis villa_b)
        </label>
        <label>
            <input type="radio" name="source_type" value="local"
                   x-model="sourceType">
            Local (projet déjà présent sur la machine hôte)
        </label>
    </div>

    <!-- Champ conditionnel : visible uniquement si source_type = 'local' -->
    <div x-show="sourceType === 'local'" x-cloak>
        <label for="source_path">Chemin absolu hôte</label>
        <input type="text" name="source_path" id="source_path"
               placeholder="ex: /c/Users/user/Herd/villab"
               class="...">
        <p class="text-xs text-slate-400">
            Chemin sur la machine hôte (pas dans le container Docker).
            Windows/WSL2 : <code>/c/Users/user/Herd/nom-du-projet</code>
        </p>
    </div>
</div>
```

---

### 3.2 Page de l'établissement (`show.blade.php`)

Ajouter un widget SSE qui se connecte automatiquement au stream de provisioning quand `$tenant->docker_status === 'creating'` (ou quand la session contient `start_provisioning`).

```html
@if(session('start_provisioning') || $tenant->docker_status === 'creating')
<div id="provisioning-log" class="...">
    <h3>Provisioning en cours…</h3>
    <div id="log-output" class="font-mono text-sm bg-slate-900 text-slate-200 p-4 rounded overflow-y-auto h-64"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const logOutput = document.getElementById('log-output');
    const streamUrl = '{{ route("tech.establishments.provision.stream", $tenant) }}';

    const evtSource = new EventSource(streamUrl);

    evtSource.onmessage = function (event) {
        const data = JSON.parse(event.data);
        const line = document.createElement('div');
        line.className = {
            'success': 'text-green-400',
            'error':   'text-red-400',
            'warning': 'text-yellow-400',
            'info':    'text-slate-300',
        }[data.level] || 'text-slate-300';
        line.textContent = `[${data.time}] ${data.message}`;
        logOutput.appendChild(line);
        logOutput.scrollTop = logOutput.scrollHeight;

        if (data.step === 'finished' || data.step === 'error') {
            evtSource.close();
            // Recharger la page après 2s pour afficher le statut à jour
            setTimeout(() => location.reload(), 2000);
        }
    };

    evtSource.onerror = function () {
        evtSource.close();
    };
});
</script>
@endif
```

---

## Résumé des fichiers par projet

### `erp_pms` (admin)

| Fichier | Action |
|---|---|
| `app/Services/TenantProvisioningService.php` | Créer |
| `config/provisioning.php` | Créer |
| `database/migrations/2026_06_28_100000_add_source_to_tenants_table.php` | Créer |
| `app/Http/Controllers/AdminAuditController.php` | Remplacer |
| `app/Models/Tenant.php` | Modifier (`$fillable`) |
| `routes/web.php` | Modifier (ajouter route SSE) |
| `.env` | Modifier (ajouter variables provisioning) |
| `resources/views/admin/tenants/create.blade.php` | Modifier (champ source_type + source_path) |
| `resources/views/admin/tenants/show.blade.php` | Modifier (widget SSE) |

### `villa_b` (template)

| Fichier | Action |
|---|---|
| `Dockerfile` | Créer |
| `docker/nginx.conf` | Créer |
| `docker/supervisord.conf` | Créer |
| `docker/entrypoint.sh` | Créer + `chmod +x` |

---

## Flux complet (rappel)

```
Admin TECH remplit le formulaire
    ↓
storeTenant() → crée Tenant (docker_status='creating') → redirect show
    ↓
show.blade.php détecte start_provisioning → ouvre EventSource SSE
    ↓
provisionTenantStream() → TenantProvisioningService::provision()
    ├─ resolveSourcePath()     → local: vérifie chemin / github: git clone
    ├─ ensureDockerImage()     → docker build hotelixos-template (si absent)
    ├─ generateDockerCompose() → écrit TENANTS_BASE_PATH/.compose/{slug}.yml
    ├─ startContainers()       → docker compose up -d (app + db)
    ├─ waitForDatabase()       → pg_isready jusqu'à 2 min
    └─ runMigrations()         → php artisan migrate + db:seed dans le container
    ↓
Tenant mis à jour (docker_status='running', provisioned_at=now)
    ↓
SSE envoie 'finished' → JS recharge la page
```
