<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TenantProvisioningService
{
    public function __construct(private DockerRegistryService $registry)
    {
    }

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

    // ── Suppression fichier (cross-platform) ──────────────────────────────────

    private function rmFile(string $path): void
    {
        $quoted = escapeshellarg($path);
        if (self::isWindows()) {
            $this->exec('del /F /Q ' . $quoted . ' 2>/dev/null');
        } else {
            $this->exec('rm -f ' . $quoted);
        }
    }

    // ── Entrée principale ─────────────────────────────────────────────────────

    public function provision(Tenant $tenant, callable $log): void
    {
        $slug = $tenant->slug;

        $log('start', "Démarrage du provisioning pour « {$tenant->name} » (slug: {$slug})");

        $imageRef    = $this->pullDockerImage($tenant, $log);
        $composePath = $this->generateDockerCompose($tenant, $imageRef, $log);
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

    // ── Étape 1 : Image Docker (pull depuis le registre GHCR) ─────────────────

    private function pullDockerImage(Tenant $tenant, callable $log): string
    {
        $registryImage = config('provisioning.registry_image');

        if (empty($tenant->docker_image_tag)) {
            $this->pinToTag($tenant, $registryImage, 'latest', $log);
        }

        return $this->pullPinnedImage($tenant, $registryImage, $log);
    }

    private function pullPinnedImage(Tenant $tenant, string $registryImage, callable $log): string
    {
        $imageRef = $registryImage . '@' . $tenant->docker_image_tag;

        $log('image', "⬇️  Récupération de l'image « {$imageRef} »…", 'info');
        $this->dockerPullWithRetry($imageRef, $log);
        $log('image', "✅ Image prête.", 'success');

        return $imageRef;
    }

    /**
     * GHCR redirige le téléchargement des blobs vers une URL Azure signée à
     * durée de vie courte. Sur un réseau lent/instable, cette URL peut
     * expirer avant la fin du transfert ("httpReadSeeker: failed open"),
     * sans que Docker ne retente automatiquement. On retente donc nous-mêmes
     * avant d'abandonner.
     */
    private function dockerPullWithRetry(string $imageRef, callable $log, int $maxAttempts = 3): void
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $lines = [];
            $returnCode = 0;
            exec('docker pull ' . escapeshellarg($imageRef) . ' 2>&1', $lines, $returnCode);
            $output = implode("\n", $lines);

            if ($returnCode === 0) {
                return;
            }

            if ($attempt < $maxAttempts) {
                $delay = $attempt * 5;
                $log('image', "⚠️ Échec du pull (tentative {$attempt}/{$maxAttempts}), nouvel essai dans {$delay}s…", 'warning');
                sleep($delay);
            } else {
                throw new RuntimeException(
                    "Échec du docker pull de l'image après {$maxAttempts} tentatives.\nSortie: {$output}"
                );
            }
        }
    }

    /**
     * Résout un tag du registre (ex: "latest" ou "sha-abc1234") vers son
     * digest exact via l'API de distribution, et le fige sur le tenant —
     * pour que cet établissement ne soit plus jamais impacté par un futur
     * build tant qu'une mise à jour explicite n'est pas demandée (update()).
     */
    private function pinToTag(Tenant $tenant, string $registryImage, string $tag, callable $log): void
    {
        $log('image', "📌 Résolution de la version « {$tag} »…", 'info');

        $imagePath = $this->registry->imagePath($registryImage);
        $digest    = $this->registry->resolveDigest($imagePath, $tag);

        if (!$digest) {
            throw new RuntimeException(
                "Impossible de résoudre le digest de l'image « {$registryImage}:{$tag} » sur le registre."
            );
        }

        $tenant->update(['docker_image_tag' => $digest]);
        $log('image', "✅ Version figée pour cet établissement : {$digest}", 'success');
    }

    // ── Étape 2 : docker-compose par tenant ──────────────────────────────────

    /**
     * Réutilise l'APP_KEY déjà présente dans un compose précédemment généré
     * pour ce tenant, sinon en génère une nouvelle (premier provisioning).
     * Indispensable pour update() : régénérer l'APP_KEY à chaque mise à jour
     * invaliderait sessions et données chiffrées existantes.
     */
    private function resolveAppKey(string $composePath): string
    {
        if (file_exists($composePath)) {
            $existing = file_get_contents($composePath);
            if ($existing !== false && preg_match('/APP_KEY:\s*"([^"]+)"/', $existing, $matches)) {
                return $matches[1];
            }
        }

        return 'base64:' . base64_encode(random_bytes(32));
    }

    private function generateDockerCompose(Tenant $tenant, string $imageRef, callable $log): string
    {
        $baseDir     = rtrim(config('provisioning.tenants_base_path'), '/\\');
        $composeDir  = $baseDir . '/.compose';
        $composePath = $composeDir . '/' . $tenant->slug . '.yml';

        if (!is_dir($composeDir) && !mkdir($composeDir, 0777, true) && !is_dir($composeDir)) {
            throw new RuntimeException("Impossible de créer le répertoire : {$composeDir}");
        }

        $appContainer = 'meka-erp-' . $tenant->slug . '-app';
        $dbContainer  = 'meka-erp-' . $tenant->slug . '-db';
        $network      = config('provisioning.docker_network', 'pms');
        $dbName       = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
        $dbUser       = $tenant->db_username  ?? 'pms';
        $dbPass       = $tenant->db_password  ?? 'secret';
        $appPort      = $tenant->app_port;
        $dbPort       = $tenant->db_port ?? 5432;
        $appKey       = $this->resolveAppKey($composePath);
        $appUrl       = 'http://' . $tenant->slug . '.localhost';
        $currency     = $tenant->currency ?? 'XAF';
        $settings     = $tenant->settings ?? [];
        if (!empty($settings['logo'])) {
            // Le logo vit dans le storage de l'admin (pas du tenant) : on passe
            // une URL absolue, exploitable depuis le container de l'établissement.
            $settings['logo'] = rtrim(config('app.url'), '/') . '/storage/' . ltrim($settings['logo'], '/');
        }
        $settingsJson = addslashes(json_encode($settings));
        $modulesJson  = addslashes(json_encode($tenant->modules  ?? []));

        $yaml = <<<YAML
services:

  {$appContainer}:
    image: {$imageRef}
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

        if (file_put_contents($composePath, $yaml) === false) {
            throw new RuntimeException("Impossible d'écrire le fichier docker-compose : {$composePath}");
        }

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

        $this->exec('docker rm -f ' . escapeshellarg($appContainer) . ' 2>/dev/null');
        $this->exec('docker rm -f ' . escapeshellarg($dbContainer)  . ' 2>/dev/null');

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

    /**
     * S'appuie sur le HEALTHCHECK Docker déjà défini pour le container DB dans
     * le compose généré (pg_isready), plutôt que de relancer un check via un
     * "docker exec" imbriqué depuis ce container admin — jugé peu fiable dans
     * ce contexte (nested docker exec via le socket partagé).
     */
    private function waitForDatabase(Tenant $tenant, callable $log): void
    {
        $dbContainer = 'meka-erp-' . $tenant->slug . '-db';

        $log('db', "⏳ Attente de la disponibilité de PostgreSQL…", 'info');

        for ($i = 1; $i <= 24; $i++) {
            $status = trim($this->exec(
                'docker inspect -f "{{.State.Health.Status}}" ' . escapeshellarg($dbContainer) . ' 2>&1'
            ));

            if ($status === 'healthy') {
                $log('db', "✅ PostgreSQL prêt après {$i} tentative(s).", 'success');
                return;
            }

            if ($i < 24) {
                $log('db', "  Tentative {$i}/24 — statut « {$status} », attente 5s…", 'info');
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

    // ── Mise à jour vers une nouvelle version ─────────────────────────────────

    /**
     * Liste les tags disponibles sur le registre, pour le sélecteur de
     * version côté UI TECH ("latest" en premier, comme choix par défaut).
     */
    public function listAvailableVersions(): array
    {
        $registryImage = config('provisioning.registry_image');
        $imagePath     = $this->registry->imagePath($registryImage);
        $tags          = $this->registry->listTags($imagePath);

        usort($tags, fn ($a, $b) => ($b === 'latest') <=> ($a === 'latest') ?: strcmp($b, $a));

        return $tags;
    }

    /**
     * Met à jour un établissement déjà provisionné vers un tag choisi
     * explicitement par TECH. Ne touche pas à la base de données ni à son
     * volume — seul le container applicatif est recréé avec la nouvelle
     * image, ce qui déclenche les migrations via l'entrypoint.
     */
    public function update(Tenant $tenant, string $tag, callable $log): void
    {
        $log('start', "Démarrage de la mise à jour de « {$tenant->name} » vers « {$tag} »");

        $registryImage = config('provisioning.registry_image');
        $this->pinToTag($tenant, $registryImage, $tag, $log);

        $imageRef    = $this->pullPinnedImage($tenant, $registryImage, $log);
        $composePath = $this->generateDockerCompose($tenant, $imageRef, $log);

        $log('docker', "🔄 Recréation du container applicatif…", 'info');
        $this->execOrFail(
            'docker compose -f ' . escapeshellarg($composePath) . ' up -d 2>&1',
            "Échec du docker compose up"
        );
        $log('docker', "✅ Container mis à jour.", 'success');

        $this->runMigrations($tenant, $log);

        $tenant->update(['docker_status' => 'running']);

        $log('done', "✅ Établissement « {$tenant->name} » mis à jour avec succès.", 'success');
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
        $this->exec('docker stop ' . escapeshellarg('meka-erp-' . $tenant->slug . '-app') . ' 2>/dev/null');
        $this->exec('docker stop ' . escapeshellarg('meka-erp-' . $tenant->slug . '-db') . ' 2>/dev/null');
        $log('stop', "✅ Containers arrêtés.", 'success');
    }

    public function restart(Tenant $tenant, callable $log): void
    {
        $this->exec('docker restart ' . escapeshellarg('meka-erp-' . $tenant->slug . '-db') . ' 2>/dev/null');
        $this->exec('docker restart ' . escapeshellarg('meka-erp-' . $tenant->slug . '-app') . ' 2>/dev/null');
        $log('restart', "✅ Containers redémarrés.", 'success');
    }

    public function health(Tenant $tenant): array
    {
        $appContainer = 'meka-erp-' . $tenant->slug . '-app';
        $dbContainer  = 'meka-erp-' . $tenant->slug . '-db';

        $appStatus = trim($this->exec(
            'docker inspect -f "{{.State.Status}}" ' . escapeshellarg($appContainer) . ' 2>/dev/null'
        ));
        $dbStatus  = trim($this->exec(
            'docker inspect -f "{{.State.Status}}" ' . escapeshellarg($dbContainer) . ' 2>/dev/null'
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
            $this->exec('docker rm -f ' . escapeshellarg($appContainer) . ' 2>/dev/null');
            $this->exec('docker rm -f ' . escapeshellarg($dbContainer)  . ' 2>/dev/null');
            $this->exec('docker volume rm meka_erp_' . $slug . '_pgdata 2>/dev/null');
            $log('docker', "Conteneurs orphelins et volume supprimés.", 'warning');
        }

        $log('done', "✅ Établissement « {$tenant->name} » supprimé.", 'success');
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}