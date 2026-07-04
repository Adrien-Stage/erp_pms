<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TenantProvisioningService
{
    private const GITHUB_URL_DEFAULT  = 'https://github.com/Adrien-Stage/villa_b.git';
    private const DOCKER_IMAGE_PREFIX = 'meka-erp-template';

    // ── Détection OS ─────────────────────────────────────────────────────────

    private static function isWindows(): bool
    {
        return str_starts_with(PHP_OS_FAMILY, 'Windows');
    }

    // ── Helpers shell ─────────────────────────────────────────────────────────

    private function exec(string $cmd): string
    {
        Log::debug("[Provisioning] exec: {$cmd}");
        return (string) shell_exec($cmd);
    }

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

    // ── Vérification existence dossier (cross-platform) ───────────────────────

    private function dirExists(string $path): bool
    {
        if (self::isWindows()) {
            $result = trim($this->exec('if exist ' . escapeshellarg(rtrim($path, '\\/') . '\\') . ' (echo yes) else echo no'));
        } else {
            $result = trim($this->exec('test -d ' . escapeshellarg($path) . ' && echo yes || echo no'));
        }
        return $result === 'yes';
    }

    // ── Vérification existence fichier (cross-platform) ───────────────────────

    private function fileExists(string $path): bool
    {
        if (self::isWindows()) {
            $result = trim($this->exec('if exist ' . escapeshellarg($path) . ' (echo yes) else echo no'));
        } else {
            $result = trim($this->exec('test -f ' . escapeshellarg($path) . ' && echo yes || echo no'));
        }
        return $result === 'yes';
    }

    // ── Création dossier (cross-platform) ─────────────────────────────────────

    private function ensureDir(string $path): void
    {
        $quoted = escapeshellarg($path);
        if (self::isWindows()) {
            $this->exec('if not exist ' . $quoted . ' mkdir ' . $quoted);
        } else {
            $this->exec('mkdir -p ' . $quoted);
        }
    }

    // ── Suppression dossier récursive (cross-platform) ────────────────────────

    private function rmDir(string $path): void
    {
        $quoted = escapeshellarg($path);
        if (self::isWindows()) {
            $this->exec('rmdir /S /Q ' . $quoted . ' 2>nul');
        } else {
            $this->exec('rm -rf ' . $quoted);
        }
    }

    // ── Suppression fichier (cross-platform) ──────────────────────────────────

    private function rmFile(string $path): void
    {
        $quoted = escapeshellarg($path);
        if (self::isWindows()) {
            $this->exec('del /F /Q ' . $quoted . ' 2>nul');
        } else {
            $this->exec('rm -f ' . $quoted);
        }
    }

    // ── Copie fichier (cross-platform) ────────────────────────────────────────

    private function copyFile(string $from, string $to): void
    {
        $qFrom = escapeshellarg($from);
        $qTo   = escapeshellarg($to);
        if (self::isWindows()) {
            $this->exec('copy /Y ' . $qFrom . ' ' . $qTo . ' >nul 2>&1');
        } else {
            $this->exec('cp ' . $qFrom . ' ' . $qTo);
        }
    }

    // ── Copie dossier récursive (cross-platform) ──────────────────────────────

    private function copyDir(string $from, string $to): void
    {
        $qFrom = escapeshellarg(rtrim($from, '\\/'));
        $qTo   = escapeshellarg($to);
        if (self::isWindows()) {
            $this->exec('xcopy ' . $qFrom . ' ' . $qTo . ' /E /I /Y >nul 2>&1');
        } else {
            $this->exec('cp -R ' . $qFrom . '/. ' . $qTo . '/ 2>&1');
        }
    }

    // ── Set env + run sur Windows ─────────────────────────────────────────────

    private function withGitEnv(string $cmd): string
    {
        if (self::isWindows()) {
            return 'set GIT_TERMINAL_PROMPT=0 && ' . $cmd;
        }
        return 'export GIT_TERMINAL_PROMPT=0 && ' . $cmd;
    }

    // ── Entrée principale ─────────────────────────────────────────────────────

    public function provision(Tenant $tenant, callable $log): void
    {
        $slug = $tenant->slug;

        $log('start', "Démarrage du provisioning pour « {$tenant->name} » (slug: {$slug})");

        $sourcePath = $this->resolveSourcePath($tenant, $log);
        $imageName  = $this->ensureDockerImage($sourcePath, $log);
        $composePath = $this->generateDockerCompose($tenant, $sourcePath, $imageName, $log);
        $this->startContainers($tenant, $composePath, $log);
        $this->waitForDatabase($tenant, $log);
        $this->runMigrations($tenant, $log);

        $tenant->update([
            'docker_status'         => 'running',
            'docker_app_container'  => 'meka-erp-' . $slug . '-app',
            'docker_db_container'   => 'meka-erp-' . $slug . '-db',
            'provisioned_at'        => now(),
        ]);

        $log('done', "✅ Établissement « {$tenant->name} » provisionné et opérationnel sur le port {$tenant->app_port}", 'success');
    }

    // ── Étape 1 : Source du code (clone GitHub) ───────────────────────────────

    private function resolveSourcePath(Tenant $tenant, callable $log): string
    {
        return $this->resolveGithub($tenant, $log);
    }

    private function resolveGithub(Tenant $tenant, callable $log): string
    {
        $baseDir   = rtrim(config('provisioning.tenants_base_path'), '/\\');
        $targetDir = $baseDir . '/' . $tenant->slug;
        $githubUrl = config('provisioning.template_github_url', self::GITHUB_URL_DEFAULT);

        $alreadyCloned = $this->dirExists($targetDir . '/.git');

        if ($alreadyCloned) {
            $log('source', "📁 Dossier déjà cloné, mise à jour (git pull)…", 'info');
            $pullCmd    = $this->withGitEnv('git -C ' . escapeshellarg($targetDir) . ' pull --quiet 2>&1');
            $pullResult = trim($this->exec($pullCmd));
            if (str_contains($pullResult, 'Fatal') || str_contains($pullResult, 'fatal') || str_contains($pullResult, 'Could not resolve')) {
                $log('source', "⚠️ Échec de la mise à jour (git pull). Utilisation de la version existante.", 'warning');
            }
        } else {
            $log('source', "⬇️  Clonage du template depuis GitHub…", 'info');

            $cloneCmd = $this->withGitEnv(
                'git clone --depth=1 ' . escapeshellarg($githubUrl) . ' ' . escapeshellarg($targetDir) . ' 2>&1'
            );

            try {
                $this->execOrFail($cloneCmd, "Échec du git clone");
            } catch (\Exception $e) {
                $localFallback = rtrim((string) config('provisioning.local_fallback_path', ''), '/\\');
                $hasFallback   = $localFallback !== '' && $this->dirExists($localFallback);

                if ($hasFallback) {
                    $log('source', "⚠️ Échec du clone GitHub. Repli sur le template local : {$localFallback}", 'warning');
                    $this->ensureDir($targetDir);
                    $this->copyDir($localFallback, $targetDir);
                } else {
                    throw $e;
                }
            }
        }

        $log('source', "✅ Code source prêt dans : {$targetDir}", 'success');
        return $targetDir;
    }

    // ── Étape 2 : Image Docker ────────────────────────────────────────────────

    private function ensureDockerImage(string $sourcePath, callable $log): string
    {
        $imageName     = self::DOCKER_IMAGE_PREFIX;
        $dockerfilePath = $sourcePath . '/Dockerfile';

        if (!$this->fileExists($dockerfilePath)) {
            throw new RuntimeException(
                "Aucun Dockerfile trouvé dans « {$sourcePath} ». " .
                "Vérifiez que le Dockerfile du template est bien commité."
            );
        }

        $imageExists = trim($this->exec('docker images -q ' . escapeshellarg($imageName) . ' 2>nul'));
        if (!empty($imageExists)) {
            $log('image', "🐳 Image Docker « {$imageName} » déjà présente — réutilisation.", 'info');
            return $imageName;
        }

        $log('image', "🔨 Construction de l'image Docker « {$imageName} »…", 'info');

        $buildCmd = 'docker build -t ' . escapeshellarg($imageName) .
                    ' -f ' . escapeshellarg($dockerfilePath) .
                    ' ' . escapeshellarg($sourcePath) . ' 2>&1';

        $output = $this->execOrFail($buildCmd, "Échec du docker build");

        $lines = array_filter(explode("\n", $output));
        foreach (array_slice($lines, -5) as $line) {
            if (!empty(trim($line))) {
                $log('image', "  » {$line}", 'info');
            }
        }

        $log('image', "✅ Image « {$imageName} » construite avec succès.", 'success');
        return $imageName;
    }

    // ── Étape 3 : docker-compose par tenant ──────────────────────────────────

    private function generateDockerCompose(Tenant $tenant, string $sourcePath, string $imageName, callable $log): string
    {
        $baseDir     = rtrim(config('provisioning.tenants_base_path'), '/\\');
        $composeDir  = $baseDir . '/.compose';
        $composePath = $composeDir . '/' . $tenant->slug . '.yml';

        $this->ensureDir($composeDir);

        $appContainer = 'meka-erp-' . $tenant->slug . '-app';
        $dbContainer  = 'meka-erp-' . $tenant->slug . '-db';
        $network      = config('provisioning.docker_network', 'pms');
        $dbName       = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
        $dbUser       = $tenant->db_username  ?? 'pms';
        $dbPass       = $tenant->db_password  ?? 'secret';
        $appPort      = $tenant->app_port;
        $dbPort       = $tenant->db_port ?? 5432;
        $appKey       = 'base64:' . base64_encode(random_bytes(32));
        $appUrl       = 'http://' . $tenant->slug . '.localhost';
        $currency     = $tenant->currency ?? 'XAF';
        $settingsJson = addslashes(json_encode($tenant->settings ?? []));
        $modulesJson  = addslashes(json_encode($tenant->modules  ?? []));

        $yaml = <<<YAML
services:

  {$appContainer}:
    image: {$imageName}
    container_name: {$appContainer}
    restart: unless-stopped
    ports:
      - "{$appPort}:80"
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
      - meka_erp_{$tenant->slug}_pgdata:/var/lib/postgresql/data
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
  meka_erp_{$tenant->slug}_pgdata:

YAML;

        $tmp = sys_get_temp_dir() . '/' . $tenant->slug . '.yml';
        file_put_contents($tmp, $yaml);
        $this->copyFile($tmp, $composePath);

        $log('compose', "📄 docker-compose généré : {$composePath}", 'info');
        return $composePath;
    }

    // ── Étape 4 : Démarrage des containers ───────────────────────────────────

    private function startContainers(Tenant $tenant, string $composePath, callable $log): void
    {
        $log('docker', "🚀 Démarrage des containers (app + db)…", 'info');

        $network   = config('provisioning.docker_network', 'pms');
        $netExists = trim($this->exec('docker network ls --filter name=^' . $network . '$ --format "{{.Name}}"'));
        if (empty($netExists)) {
            $log('docker', "🌐 Création du réseau Docker « {$network} »…", 'info');
            $this->execOrFail("docker network create {$network} 2>&1", "Impossible de créer le réseau Docker {$network}");
        }

        $appContainer = 'meka-erp-' . $tenant->slug . '-app';
        $dbContainer  = 'meka-erp-' . $tenant->slug . '-db';

        $this->exec('docker rm -f ' . escapeshellarg($appContainer) . ' 2>nul');
        $this->exec('docker rm -f ' . escapeshellarg($dbContainer)  . ' 2>nul');

        $output = $this->execOrFail(
            'docker compose -f ' . escapeshellarg($composePath) . ' up -d 2>&1',
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
        $dbContainer = 'meka-erp-' . $tenant->slug . '-db';
        $dbUser      = $tenant->db_username ?? 'pms';
        $dbName      = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);

        $log('db', "⏳ Attente de la disponibilité de PostgreSQL…", 'info');

        for ($i = 1; $i <= 24; $i++) {
            $ready = trim($this->exec(
                'docker exec ' . escapeshellarg($dbContainer) .
                ' pg_isready -U ' . escapeshellarg($dbUser) .
                ' -d ' . escapeshellarg($dbName) . ' 2>nul && echo ok || echo wait'
            ));

            if ($ready === 'ok') {
                $log('db', "✅ PostgreSQL prêt après {$i} tentative(s).", 'success');
                return;
            }

            if ($i < 24) {
                $log('db', "  Tentative {$i}/24 — pas encore prêt, attente 5s…", 'info');
                sleep(5);
            }
        }

        throw new RuntimeException("PostgreSQL non disponible après 2 minutes pour le tenant « {$tenant->slug} ».");
    }

    // ── Étape 6 : Migrations ──────────────────────────────────────────────────

    private function runMigrations(Tenant $tenant, callable $log): void
    {
        $appContainer = 'meka-erp-' . $tenant->slug . '-app';

        $log('migrate', "⏳ Attente du démarrage de l'application (migrations auto via entrypoint)…", 'info');

        // L'entrypoint du template exécute automatiquement migrate + seed.
        // On attend simplement que le container soit stable (30 tentatives × 5s = 2.5 min max).
        for ($i = 1; $i <= 30; $i++) {
            $status = trim($this->exec(
                'docker inspect -f "{{.State.Status}}" ' . escapeshellarg($appContainer) . ' 2>&1'
            ));

            if ($status === 'running') {
                // Vérifier que nginx répond
                $httpCheck = trim($this->exec(
                    'docker exec ' . escapeshellarg($appContainer) . ' curl -s -o /dev/null -w "%{http_code}" http://localhost/ 2>&1'
                ));

                if ($httpCheck === '200' || $httpCheck === '302') {
                    $log('migrate', "✅ Application opérationnelle (HTTP {$httpCheck}).", 'success');
                    return;
                }

                $log('migrate', "  Tentative {$i}/30 — container running, HTTP: {$httpCheck}…", 'info');
            } else {
                $log('migrate', "  Tentative {$i}/30 — container status: {$status}…", 'info');
            }

            if ($i < 30) {
                sleep(5);
            }
        }

        // Même si le health check n'est pas concluant, on continue
        // (les migrations peuvent prendre du temps avec les seeders)
        $log('migrate', "⚠️ Timeout du health check — le container est peut-être encore en cours d'initialisation.", 'warning');
    }

    // ── Actions post-provisioning ─────────────────────────────────────────────

    public function start(Tenant $tenant, callable $log): void
    {
        $appContainer = 'meka-erp-' . $tenant->slug . '-app';
        $check = trim($this->exec(
            'docker ps -a --filter name=^' . $appContainer . '$ --format "{{.Names}}"'
        ));

        if (empty($check)) {
            $log('start', "Container introuvable — re-provisioning…", 'warning');
            $this->provision($tenant, $log);
            return;
        }

        $this->exec('docker start ' . escapeshellarg($appContainer) . ' 2>&1');
        $this->exec('docker start ' . escapeshellarg('meka-erp-' . $tenant->slug . '-db') . ' 2>&1');
        $log('start', "✅ Containers démarrés.", 'success');
    }

    public function stop(Tenant $tenant, callable $log): void
    {
        $this->exec('docker stop ' . escapeshellarg('meka-erp-' . $tenant->slug . '-app') . ' 2>nul');
        $this->exec('docker stop ' . escapeshellarg('meka-erp-' . $tenant->slug . '-db') . ' 2>nul');
        $log('stop', "✅ Containers arrêtés.", 'success');
    }

    public function restart(Tenant $tenant, callable $log): void
    {
        $this->exec('docker restart ' . escapeshellarg('meka-erp-' . $tenant->slug . '-db') . ' 2>nul');
        $this->exec('docker restart ' . escapeshellarg('meka-erp-' . $tenant->slug . '-app') . ' 2>nul');
        $log('restart', "✅ Containers redémarrés.", 'success');
    }

    public function health(Tenant $tenant): array
    {
        $appContainer = 'meka-erp-' . $tenant->slug . '-app';
        $dbContainer  = 'meka-erp-' . $tenant->slug . '-db';

        $appStatus = trim($this->exec(
            'docker inspect -f "{{.State.Status}}" ' . escapeshellarg($appContainer) . ' 2>nul'
        ));
        $dbStatus  = trim($this->exec(
            'docker inspect -f "{{.State.Status}}" ' . escapeshellarg($dbContainer) . ' 2>nul'
        ));

        if ($appStatus === '') { $appStatus = 'absent'; }
        if ($dbStatus === '') { $dbStatus = 'absent'; }

        return [
            'app_container' => $appContainer,
            'db_container'  => $dbContainer,
            'app_status'    => $appStatus,
            'db_status'     => $dbStatus,
            'healthy'       => $appStatus === 'running' && $dbStatus === 'running',
            'url'           => $appStatus === 'running' ? 'http://' . $tenant->slug . '.localhost' : null,
        ];
    }

    public function delete(Tenant $tenant, callable $log): void
    {
        $slug = $tenant->slug;
        $log('delete', "Suppression de l'établissement « {$tenant->name} » (slug: {$slug})");

        $baseDir     = rtrim(config('provisioning.tenants_base_path'), '/\\');
        $composePath = $baseDir . '/.compose/' . $slug . '.yml';

        $log('docker', "Arrêt et destruction des conteneurs et volumes…", 'info');

        if ($this->fileExists($composePath)) {
            $this->exec('docker compose -f ' . escapeshellarg($composePath) . ' down -v 2>&1');
            $this->rmFile($composePath);
            $log('compose', "Configuration docker-compose supprimée.", 'success');
        } else {
            $appContainer = 'meka-erp-' . $slug . '-app';
            $dbContainer  = 'meka-erp-' . $slug . '-db';
            $this->exec('docker rm -f ' . escapeshellarg($appContainer) . ' 2>nul');
            $this->exec('docker rm -f ' . escapeshellarg($dbContainer)  . ' 2>nul');
            $this->exec('docker volume rm meka_erp_' . $slug . '_pgdata 2>nul');
            $log('docker', "Conteneurs orphelins et volume supprimés.", 'warning');
        }

        $targetDir = $baseDir . '/' . $slug;
        if (str_starts_with($targetDir, $baseDir) && $slug !== '' && $slug !== '.compose') {
            if ($this->dirExists($targetDir)) {
                $log('source', "Suppression du répertoire des sources : {$targetDir}…", 'info');
                $this->rmDir($targetDir);
                $log('source', "Répertoire des sources supprimé.", 'success');
            }
        }

        $log('done', "✅ Établissement « {$tenant->name} » supprimé.", 'success');
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}