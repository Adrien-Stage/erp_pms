<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * TenantProvisioningService
 *
 * Orchestre la création complète d'un établissement dans Docker :
 *   1. Récupère le code source (local ou clone GitHub)
 *   2. Génère le docker-compose.yml du tenant
 *   3. Injecte la configuration (.env)
 *   4. Lance les containers (app + db)
 *   5. Exécute les migrations + seeders
 *   6. Met à jour le statut du Tenant en base
 *
 * Toutes les étapes émettent des événements via le générateur $log()
 * pour permettre un feedback SSE en temps réel depuis l'UI.
 *
 * VARIABLES D'ENVIRONNEMENT REQUISES DANS .env (admin) :
 *   TENANTS_BASE_PATH   — chemin hôte absolu où stocker les codes des tenants
 *                         ex: /c/Users/user/Herd (Windows/WSL2)
 *                             /home/ubuntu/hotelixos (Linux)
 *   TEMPLATE_GITHUB_URL — URL du dépôt template (défaut: repo villa_b)
 *   DOCKER_NETWORK      — réseau Docker partagé (défaut: pms)
 *   POSTGRES_ADMIN_HOST — hôte PostgreSQL vu depuis le container admin (défaut: db)
 *   POSTGRES_ADMIN_PORT — port PostgreSQL (défaut: 5432)
 *   POSTGRES_ADMIN_USER — superutilisateur PostgreSQL (défaut: pms)
 *   POSTGRES_ADMIN_PASS — mot de passe superutilisateur (défaut: secret)
 */
class TenantProvisioningService
{
    // ── Constantes ────────────────────────────────────────────────────────────

    private const GITHUB_URL_DEFAULT  = 'https://github.com/Adrien-Stage/villa_b.git';
    private const DOCKER_IMAGE_PREFIX = 'hotelixos-template';
    private const APP_CONTAINER_TPL   = 'hotelixos-{slug}-app';
    private const DB_CONTAINER_TPL    = 'hotelixos-{slug}-db';

    // ── Entrée principale ─────────────────────────────────────────────────────

    /**
     * Provisionne complètement un établissement.
     *
     * @param  Tenant    $tenant  L'enregistrement Tenant déjà créé en SQLite
     * @param  callable  $log     fn(string $step, string $message, string $level = 'info')
     *                            Niveaux : info | success | warning | error
     * @throws RuntimeException   En cas d'échec bloquant
     */
    public function provision(Tenant $tenant, callable $log): void
    {
        $slug = $tenant->slug;

        $log('start', "Démarrage du provisioning pour « {$tenant->name} » (slug: {$slug})");

        // 1. Résoudre le chemin source du code applicatif
        $sourcePath = $this->resolveSourcePath($tenant, $log);

        // 2. Construire ou vérifier l'image Docker du template
        $imageName = $this->ensureDockerImage($sourcePath, $log);

        // 3. Générer le docker-compose dédié au tenant
        $composePath = $this->generateDockerCompose($tenant, $sourcePath, $imageName, $log);

        // 4. Démarrer les containers
        $this->startContainers($tenant, $composePath, $log);

        // 5. Attendre que la DB soit prête
        $this->waitForDatabase($tenant, $log);

        // 6. Exécuter les migrations et seeders
        $this->runMigrations($tenant, $log);

        // 7. Mise à jour finale du Tenant
        $tenant->update([
            'docker_status'  => 'running',
            'docker_app_container' => $this->containerName($slug, 'app'),
            'docker_db_container'  => $this->containerName($slug, 'db'),
            'provisioned_at' => now(),
        ]);

        $log('done', "✅ Établissement « {$tenant->name} » provisionné et opérationnel sur le port {$tenant->app_port}", 'success');
    }

    // ── Étape 1 : Source du code ──────────────────────────────────────────────

    /**
     * Retourne le chemin absolu (sur l'hôte) vers le code source du template.
     *
     * - source_type = 'local'  → utilise source_path tel quel (vérifie existence)
     * - source_type = 'github' → clone dans TENANTS_BASE_PATH/{slug}
     * - source_type = null     → clone depuis GitHub par défaut
     */
    private function resolveSourcePath(Tenant $tenant, callable $log): string
    {
        $type = $tenant->source_type ?? 'github';

        if ($type === 'local') {
            return $this->resolveLocal($tenant, $log);
        }

        return $this->resolveGithub($tenant, $log);
    }

    private function resolveLocal(Tenant $tenant, callable $log): string
    {
        $path = rtrim($tenant->source_path ?? '', '/\\');

        if (empty($path)) {
            throw new RuntimeException("source_type=local mais source_path est vide pour le tenant « {$tenant->slug} ».");
        }

        // Le chemin est sur l'hôte — on vérifie via un exec rapide
        $exists = $this->exec("test -d " . escapeshellarg($path) . " && echo yes || echo no");

        if (trim($exists) !== 'yes') {
            throw new RuntimeException("Le chemin local « {$path} » n'existe pas ou n'est pas accessible depuis le container admin.");
        }

        $log('source', "📁 Code source local détecté : {$path}", 'info');
        return $path;
    }

    private function resolveGithub(Tenant $tenant, callable $log): string
    {
        $baseDir    = rtrim(config('provisioning.tenants_base_path'), '/\\');
        $targetDir  = "{$baseDir}/{$tenant->slug}";
        $githubUrl  = config('provisioning.template_github_url', self::GITHUB_URL_DEFAULT);

        // Si le dossier existe déjà (re-provisioning), on fait un pull
        $alreadyCloned = trim($this->exec("test -d " . escapeshellarg("{$targetDir}/.git") . " && echo yes || echo no"));

        if ($alreadyCloned === 'yes') {
            $log('source', "📁 Dossier déjà cloné, mise à jour (git pull)…", 'info');
            $pullCmd = "export GIT_TERMINAL_PROMPT=0 && git -C " . escapeshellarg($targetDir) . " pull --quiet 2>&1";
            $pullResult = trim($this->exec($pullCmd));
            if (str_contains($pullResult, 'Fatal') || str_contains($pullResult, 'fatal') || str_contains($pullResult, 'Could not resolve')) {
                $log('source', "⚠️ Échec de la mise à jour (git pull) : Dépôt privé ou hors-ligne. Utilisation de la version existante.", 'warning');
            }
        } else {
            $log('source', "⬇️  Clonage du template depuis GitHub…", 'info');
            
            // Fail fast on credential prompt
            $cloneCmd = "export GIT_TERMINAL_PROMPT=0 && git clone --depth=1 " . escapeshellarg($githubUrl) . " " . escapeshellarg($targetDir) . " 2>&1";
            
            try {
                $this->execOrFail($cloneCmd, "Échec du git clone");
            } catch (\Exception $e) {
                // Check if local template fallback is available
                $localFallback = rtrim((string) config('provisioning.local_fallback_path', ''), '/\\');

                if ($localFallback !== '') {
                    $hasFallback = trim($this->exec("test -d " . escapeshellarg($localFallback) . " && echo yes || echo no"));
                } else {
                    $hasFallback = 'no';
                }

                if ($hasFallback === 'yes') {
                    $log('source', "⚠️ Échec du clone GitHub (dépôt privé ou hors-ligne). Repli automatique sur le template local : {$localFallback}", 'warning');

                    // Copy host folder files to target directory
                    $this->exec("mkdir -p " . escapeshellarg($targetDir));
                    $this->exec("cp -R " . escapeshellarg($localFallback) . "/. " . escapeshellarg($targetDir) . "/ 2>&1");
                } else {
                    throw $e; // Re-throw if no fallback
                }
            }
        }

        $log('source', "✅ Code source prêt dans : {$targetDir}", 'success');
        return $targetDir;
    }

    // ── Étape 2 : Image Docker ────────────────────────────────────────────────

    /**
     * Construit l'image Docker depuis le Dockerfile du template.
     * Si l'image existe déjà (même digest), on la réutilise.
     *
     * L'image est partagée entre tous les tenants (ils ont le même code de base).
     * Seul le .env change par tenant — injecté via docker-compose.
     */
    private function ensureDockerImage(string $sourcePath, callable $log): string
    {
        $imageName    = self::DOCKER_IMAGE_PREFIX;
        $dockerfilePath = "{$sourcePath}/Dockerfile";

        // Vérifier que le Dockerfile existe dans le template
        $hasDockerfile = trim($this->exec("test -f " . escapeshellarg($dockerfilePath) . " && echo yes || echo no"));

        if ($hasDockerfile !== 'yes') {
            throw new RuntimeException(
                "Aucun Dockerfile trouvé dans « {$sourcePath} ». " .
                "Vérifiez que le Dockerfile du template est bien commité dans le dépôt."
            );
        }

        // Vérifier si l'image existe déjà
        $imageExists = trim($this->exec("docker images -q " . escapeshellarg($imageName) . " 2>/dev/null"));

        if (!empty($imageExists)) {
            $log('image', "🐳 Image Docker « {$imageName} » déjà présente — réutilisation.", 'info');
            return $imageName;
        }

        $log('image', "🔨 Construction de l'image Docker « {$imageName} »…", 'info');

        $buildCmd = "docker build -t " . escapeshellarg($imageName) .
                    " -f " . escapeshellarg($dockerfilePath) .
                    " " . escapeshellarg($sourcePath) . " 2>&1";

        $output = $this->execOrFail($buildCmd, "Échec du docker build");

        // Logger les dernières lignes du build pour debug
        $lines = array_filter(explode("\n", $output));
        $tail  = array_slice($lines, -5);
        foreach ($tail as $line) {
            if (!empty(trim($line))) {
                $log('image', "  » {$line}", 'info');
            }
        }

        $log('image', "✅ Image « {$imageName} » construite avec succès.", 'success');
        return $imageName;
    }

    // ── Étape 3 : docker-compose par tenant ──────────────────────────────────

    /**
     * Génère un docker-compose.yml dédié au tenant dans TENANTS_BASE_PATH/.compose/
     * et retourne le chemin du fichier généré.
     */
    private function generateDockerCompose(Tenant $tenant, string $sourcePath, string $imageName, callable $log): string
    {
        $baseDir     = rtrim(config('provisioning.tenants_base_path'), '/\\');
        $composeDir  = "{$baseDir}/.compose";
        $composePath = "{$composeDir}/{$tenant->slug}.yml";

        // Créer le dossier .compose s'il n'existe pas
        $this->exec("mkdir -p " . escapeshellarg($composeDir));

        $appContainer = $this->containerName($tenant->slug, 'app');
        $dbContainer  = $this->containerName($tenant->slug, 'db');
        $network      = config('provisioning.docker_network', 'pms');
        $dbName       = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
        $dbUser       = $tenant->db_username  ?? 'pms';
        $dbPass       = $tenant->db_password  ?? 'secret';
        $appPort      = $tenant->app_port;
        $dbPort       = $tenant->db_port ?? 5432;
        $appKey       = 'base64:' . base64_encode(random_bytes(32));
        $appUrl       = "http://{$tenant->slug}.localhost";
        $currency     = $tenant->currency ?? 'XAF';

        // Sérialiser les settings pour les passer en env
        $settingsJson = addslashes(json_encode($tenant->settings ?? []));
        $modulesJson  = addslashes(json_encode($tenant->modules  ?? []));

        $yaml = <<<YAML
# docker-compose généré automatiquement par HotelixOS
# Tenant : {$tenant->name} (slug: {$tenant->slug})
# Généré le : {$this->now()}

services:

  {$appContainer}:
    image: {$imageName}
    container_name: {$appContainer}
    restart: unless-stopped
    ports:
      - "{$appPort}:80"
    volumes:
      - {$sourcePath}:/var/www/html
    environment:
      APP_NAME: "{$tenant->name}"
      APP_ENV: production
      APP_DEBUG: "false"
      APP_KEY: "{$appKey}"
      APP_URL: "{$appUrl}"
      DB_CONNECTION: pgsql
      DB_HOST: {$dbContainer}
      DB_PORT: 5432
      DB_DATABASE: {$dbName}
      DB_USERNAME: {$dbUser}
      DB_PASSWORD: {$dbPass}
      TENANT_SLUG: "{$tenant->slug}"
      TENANT_CURRENCY: "{$currency}"
      TENANT_SETTINGS: '{$settingsJson}'
      TENANT_MODULES: '{$modulesJson}'
      SESSION_DRIVER: database
      CACHE_STORE: database
      QUEUE_CONNECTION: database
    depends_on:
      {$dbContainer}:
        condition: service_healthy
    networks:
      - {$network}

  {$dbContainer}:
    image: postgres:16
    container_name: {$dbContainer}
    restart: unless-stopped
    ports:
      - "{$dbPort}:5432"
    environment:
      POSTGRES_DB: {$dbName}
      POSTGRES_USER: {$dbUser}
      POSTGRES_PASSWORD: {$dbPass}
    volumes:
      - hotelixos_{$tenant->slug}_pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U {$dbUser} -d {$dbName}"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks:
      - {$network}

networks:
  {$network}:
    external: true

volumes:
  hotelixos_{$tenant->slug}_pgdata:

YAML;

        file_put_contents('/tmp/' . $tenant->slug . '.yml', $yaml);
        $this->exec("cp /tmp/{$tenant->slug}.yml " . escapeshellarg($composePath));

        $log('compose', "📄 docker-compose généré : {$composePath}", 'info');
        return $composePath;
    }

    // ── Étape 4 : Démarrage des containers ───────────────────────────────────

    private function startContainers(Tenant $tenant, string $composePath, callable $log): void
    {
        $log('docker', "🚀 Démarrage des containers (app + db)…", 'info');

        // S'assurer que le réseau Docker existe
        $network = config('provisioning.docker_network', 'pms');
        $netExists = trim($this->exec("docker network ls --filter name=^{$network}$ --format '{{.Name}}'"));
        if (empty($netExists)) {
            $log('docker', "🌐 Création du réseau Docker « {$network} »…", 'info');
            $this->execOrFail("docker network create {$network} 2>&1", "Impossible de créer le réseau Docker {$network}");
        }

        // Arrêter les anciens containers éventuels (re-provisioning)
        $appContainer = $this->containerName($tenant->slug, 'app');
        $dbContainer  = $this->containerName($tenant->slug, 'db');
        $this->exec("docker rm -f " . escapeshellarg($appContainer) . " 2>/dev/null || true");
        $this->exec("docker rm -f " . escapeshellarg($dbContainer)  . " 2>/dev/null || true");

        // Lancer avec docker-compose
        $output = $this->execOrFail(
            "docker compose -f " . escapeshellarg($composePath) . " up -d 2>&1",
            "Échec du docker compose up"
        );

        foreach (array_filter(explode("\n", $output)) as $line) {
            if (!empty(trim($line))) {
                $log('docker', "  » {$line}", 'info');
            }
        }

        $log('docker', "✅ Containers démarrés.", 'success');
    }

    // ── Étape 5 : Attendre la DB ──────────────────────────────────────────────

    private function waitForDatabase(Tenant $tenant, callable $log): void
    {
        $dbContainer = $this->containerName($tenant->slug, 'db');
        $dbUser      = $tenant->db_username ?? 'pms';
        $dbName      = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);

        $log('db', "⏳ Attente de la disponibilité de PostgreSQL…", 'info');

        $maxAttempts = 24; // 24 × 5s = 2 min max
        for ($i = 1; $i <= $maxAttempts; $i++) {
            $ready = trim($this->exec(
                "docker exec " . escapeshellarg($dbContainer) .
                " pg_isready -U " . escapeshellarg($dbUser) .
                " -d " . escapeshellarg($dbName) . " 2>/dev/null && echo ok || echo wait"
            ));

            if ($ready === 'ok') {
                $log('db', "✅ PostgreSQL prêt après {$i} tentative(s).", 'success');
                return;
            }

            if ($i < $maxAttempts) {
                $log('db', "  Tentative {$i}/{$maxAttempts} — pas encore prêt, attente 5s…", 'info');
                sleep(5);
            }
        }

        throw new RuntimeException("PostgreSQL non disponible après 2 minutes pour le tenant « {$tenant->slug} ».");
    }

    // ── Étape 6 : Migrations ──────────────────────────────────────────────────

    private function runMigrations(Tenant $tenant, callable $log): void
    {
        $appContainer = $this->containerName($tenant->slug, 'app');

        $log('migrate', "🗄️  Exécution des migrations…", 'info');

        $migrateOutput = $this->exec(
            "docker exec " . escapeshellarg($appContainer) .
            " php artisan migrate --force --no-interaction 2>&1"
        );

        foreach (array_filter(explode("\n", $migrateOutput)) as $line) {
            if (!empty(trim($line))) {
                $log('migrate', "  » {$line}", 'info');
            }
        }

        $log('migrate', "✅ Migrations terminées.", 'success');

        // Seeders (optionnel : ne bloque pas si ça échoue)
        $log('seed', "🌱 Exécution des seeders…", 'info');
        $seedOutput = $this->exec(
            "docker exec " . escapeshellarg($appContainer) .
            " php artisan db:seed --force --no-interaction 2>&1"
        );

        foreach (array_filter(explode("\n", $seedOutput)) as $line) {
            if (!empty(trim($line))) {
                $log('seed', "  » {$line}", 'info');
            }
        }

        $log('seed', "✅ Seeders terminés.", 'success');
    }

    // ── Actions post-provisioning (start / stop / restart / health) ───────────

    public function start(Tenant $tenant, callable $log): void
    {
        $appContainer = $this->containerName($tenant->slug, 'app');
        $check = trim($this->exec("docker ps -a --filter name=^/{$appContainer}$ --format '{{.Names}}'"));

        if (empty($check)) {
            // Container n'existe pas → re-provisioning complet
            $log('start', "Container introuvable — re-provisioning…", 'warning');
            $this->provision($tenant, $log);
            return;
        }

        $this->exec("docker start " . escapeshellarg($appContainer) . " 2>&1");
        $dbContainer = $this->containerName($tenant->slug, 'db');
        $this->exec("docker start " . escapeshellarg($dbContainer) . " 2>&1");

        $log('start', "✅ Containers démarrés.", 'success');
    }

    public function stop(Tenant $tenant, callable $log): void
    {
        $appContainer = $this->containerName($tenant->slug, 'app');
        $dbContainer  = $this->containerName($tenant->slug, 'db');

        $this->exec("docker stop " . escapeshellarg($appContainer) . " 2>/dev/null || true");
        $this->exec("docker stop " . escapeshellarg($dbContainer)  . " 2>/dev/null || true");

        $log('stop', "✅ Containers arrêtés.", 'success');
    }

    public function restart(Tenant $tenant, callable $log): void
    {
        $appContainer = $this->containerName($tenant->slug, 'app');
        $dbContainer  = $this->containerName($tenant->slug, 'db');

        $this->exec("docker restart " . escapeshellarg($dbContainer)  . " 2>/dev/null || true");
        $this->exec("docker restart " . escapeshellarg($appContainer) . " 2>/dev/null || true");

        $log('restart', "✅ Containers redémarrés.", 'success');
    }

    /**
     * Retourne l'état du container app + db.
     */
    public function health(Tenant $tenant): array
    {
        $appContainer = $this->containerName($tenant->slug, 'app');
        $dbContainer  = $this->containerName($tenant->slug, 'db');

        $appStatus = trim($this->exec(
            "docker inspect -f '{{.State.Status}}' " . escapeshellarg($appContainer) . " 2>/dev/null || echo 'absent'"
        ));
        $dbStatus  = trim($this->exec(
            "docker inspect -f '{{.State.Status}}' " . escapeshellarg($dbContainer) . " 2>/dev/null || echo 'absent'"
        ));

        $appRunning = $appStatus === 'running';
        $dbRunning  = $dbStatus  === 'running';

        return [
            'app_container' => $appContainer,
            'db_container'  => $dbContainer,
            'app_status'    => $appStatus,
            'db_status'     => $dbStatus,
            'healthy'       => $appRunning && $dbRunning,
            'url'           => $appRunning ? "http://{$tenant->slug}.localhost" : null,
        ];
    }

    /**
     * Supprime complètement un établissement (conteneurs Docker, volumes et configuration compose).
     */
    public function delete(Tenant $tenant, callable $log): void
    {
        $slug = $tenant->slug;
        $log('delete', "Suppression de l'établissement « {$tenant->name} » (slug: {$slug})");

        $baseDir     = rtrim(config('provisioning.tenants_base_path'), '/\\');
        $composePath = "{$baseDir}/.compose/{$slug}.yml";

        // 1. Arrêt et suppression des conteneurs et des volumes Docker
        $log('docker', "Arrêt et destruction des conteneurs app/db et des volumes associés…", 'info');
        $hasComposeFile = trim($this->exec("test -f " . escapeshellarg($composePath) . " && echo yes || echo no"));

        if ($hasComposeFile === 'yes') {
            $this->exec("docker compose -f " . escapeshellarg($composePath) . " down -v 2>&1");
            $this->exec("rm -f " . escapeshellarg($composePath));
            $log('compose', "Configuration docker-compose supprimée.", 'success');
        } else {
            // Repli au cas où : on force la suppression des conteneurs par nom
            $appContainer = $this->containerName($slug, 'app');
            $dbContainer  = $this->containerName($slug, 'db');
            $this->exec("docker rm -f " . escapeshellarg($appContainer) . " 2>/dev/null || true");
            $this->exec("docker rm -f " . escapeshellarg($dbContainer)  . " 2>/dev/null || true");
            $this->exec("docker volume rm hotelixos_{$slug}_pgdata 2>/dev/null || true");
            $log('docker', "Conteneurs orphelins et volume PostgreSQL supprimés par force.", 'warning');
        }

        // 2. Supprimer le dossier du code source local uniquement s'il est sous tenants_base_path
        $targetDir = "{$baseDir}/{$slug}";
        $isManagedPath = str_starts_with($targetDir, $baseDir) && ($slug !== '') && ($slug !== '.compose');
        if ($isManagedPath) {
            $hasTargetDir = trim($this->exec("test -d " . escapeshellarg($targetDir) . " && echo yes || echo no"));
            if ($hasTargetDir === 'yes') {
                $log('source', "Suppression du répertoire des sources : {$targetDir}…", 'info');
                $this->exec("rm -rf " . escapeshellarg($targetDir));
                $log('source', "Répertoire des sources supprimé.", 'success');
            }
        }

        $log('done', "✅ Établissement « {$tenant->name} » supprimé de l'infrastructure Docker.", 'success');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function containerName(string $slug, string $type): string
    {
        return "hotelixos-{$slug}-{$type}";
    }

    /**
     * Exécute une commande shell et retourne la sortie.
     * N'échoue pas — utiliser execOrFail() pour les étapes critiques.
     */
    private function exec(string $cmd): string
    {
        Log::debug("[Provisioning] exec: {$cmd}");
        return (string) shell_exec($cmd);
    }

    /**
     * Exécute une commande et lève une exception si le code de retour est non-zéro.
     */
    private function execOrFail(string $cmd, string $errorMessage): string
    {
        Log::debug("[Provisioning] execOrFail: {$cmd}");
        $output = '';
        $returnCode = 0;

        exec($cmd, $lines, $returnCode);
        $output = implode("\n", $lines);

        if ($returnCode !== 0) {
            Log::error("[Provisioning] FAILED ({$returnCode}): {$cmd}\n{$output}");
            throw new RuntimeException("{$errorMessage}\nSortie: {$output}");
        }

        return $output;
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}