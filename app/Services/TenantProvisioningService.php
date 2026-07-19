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
        $webImageRef = $this->hasWebsiteModule($tenant) ? $this->pullWebImage($tenant, $log) : null;
        $composePath = $this->generateDockerCompose($tenant, $imageRef, $webImageRef, $log);
        $this->startContainers($tenant, $composePath, $log);
        $this->waitForDatabase($tenant, $log);
        $this->runMigrations($tenant, $log);

        $tenant->update([
            'docker_status'         => 'running',
            'docker_app_container'  => 'meka-erp-' . $slug . '-app',
            'docker_db_container'   => 'meka-erp-' . $slug . '-db',
            'docker_web_container'  => $webImageRef !== null ? 'meka-erp-' . $slug . '-web' : null,
            'provisioned_at'        => now(),
        ]);

        $log('done', "✅ Établissement « {$tenant->name} » provisionné et opérationnel sur le port {$tenant->app_port}", 'success');
    }

    /**
     * Le module "website" est stocké dans Tenant::modules au même titre que
     * les autres modules métier — il pilote le provisioning du 3e container
     * "web" (site vitrine) en plus de TENANT_MODULES côté container app.
     */
    private function hasWebsiteModule(Tenant $tenant): bool
    {
        return in_array('website', $tenant->modules ?? [], true);
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
     * durée de vie courte. Sur un réseau lent/instable, ce transfert peut se
     * *figer* sans jamais rendre la main : `docker pull` reste ouvert mais ne
     * progresse plus, et un simple `exec()` bloquant attendrait alors
     * indéfiniment (c'est le symptôme « figé sur Récupération de l'image »).
     *
     * On surveille donc le pull : tant qu'il progresse, on le laisse faire (une
     * connexion lente n'est pas un échec) ; s'il ne produit plus rien pendant
     * un certain temps, on le considère bloqué, on le tue et on retente. Docker
     * reprend alors les couches déjà téléchargées, donc chaque tentative fait
     * avancer le transfert.
     */
    private function dockerPullWithRetry(string $imageRef, callable $log, int $maxAttempts = 4): void
    {
        $stallSeconds = max(30, (int) config('provisioning.pull_stall_timeout', 120));
        $hardCap      = max($stallSeconds, (int) config('provisioning.pull_max_seconds', 900));

        $lastOutput = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            [$ok, $lastOutput, $reason] = $this->runMonitoredPull($imageRef, $stallSeconds, $hardCap, $log);

            if ($ok) {
                return;
            }

            if ($attempt < $maxAttempts) {
                $delay = $attempt * 5;
                $log('image', "⚠️ Téléchargement interrompu ({$reason}) — tentative {$attempt}/{$maxAttempts}. "
                    . "Les couches déjà reçues sont conservées ; reprise dans {$delay}s…", 'warning');
                sleep($delay);
            }
        }

        throw new RuntimeException(
            "Échec du téléchargement de l'image après {$maxAttempts} tentatives. "
            . "Vérifiez la connexion internet puis relancez la mise à jour "
            . "(la reprise repart des couches déjà téléchargées).\n\nDernière sortie :\n{$lastOutput}"
        );
    }

    /**
     * Lance un `docker pull` surveillé. Rend [succès, sortie, raison d'échec].
     *
     * Émet un battement de cœur régulier vers le flux SSE : l'interface ne
     * paraît jamais figée, et la connexion reste active (utile derrière un
     * proxy qui couperait un flux inactif).
     *
     * @return array{0: bool, 1: string, 2: string}
     */
    private function runMonitoredPull(string $imageRef, int $stallSeconds, int $hardCap, callable $log): array
    {
        // Le préfixe `exec` fait de `docker` le processus fils *direct* (au lieu
        // d'un shell parent qui laisserait docker orphelin) : on peut ainsi
        // réellement le tuer s'il se fige. La redirection 2>&1 est appliquée
        // avant que exec ne remplace le shell.
        $cmd = 'exec docker pull ' . escapeshellarg($imageRef) . ' 2>&1';

        $pipes = [];
        $process = @proc_open($cmd, [1 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return [false, "Impossible de lancer « docker pull ».", 'lancement impossible'];
        }

        stream_set_blocking($pipes[1], false);

        $buffer       = '';
        $tail         = '';
        $start        = time();
        $lastActivity = time();
        $lastBeat     = time();

        while (true) {
            $read = [$pipes[1]];
            $write = $except = [];
            // Rend la main dès qu'il y a des données, ou au bout d'1 seconde.
            $ready = @stream_select($read, $write, $except, 1);

            if ($ready === false) {
                // Interruption par un signal : on refait un tour.
                continue;
            }

            if ($ready > 0) {
                $chunk = fread($pipes[1], 65536);
                if ($chunk !== '' && $chunk !== false) {
                    $buffer      .= $chunk;
                    $lastActivity = time();

                    $chunkLines = preg_split('/[\r\n]+/', trim($chunk));
                    $lastLine   = is_array($chunkLines) ? end($chunkLines) : '';
                    if (is_string($lastLine) && $lastLine !== '') {
                        $tail = mb_substr($lastLine, 0, 120);
                    }
                }
            }

            $status = proc_get_status($process);

            if (!$status['running']) {
                $rest = stream_get_contents($pipes[1]);
                if (is_string($rest) && $rest !== '') {
                    $buffer .= $rest;
                }
                @fclose($pipes[1]);
                @proc_close($process);

                if ($status['exitcode'] === 0) {
                    return [true, $buffer, ''];
                }

                return [false, $buffer, "code de sortie {$status['exitcode']}"];
            }

            $now = time();

            // Bloqué : plus aucune progression depuis trop longtemps.
            if ($now - $lastActivity >= $stallSeconds) {
                $this->terminateProcess($process, $pipes);
                return [false, $buffer, "aucune progression depuis {$stallSeconds}s"];
            }

            // Garde-fou : une seule tentative ne doit pas durer sans fin.
            if ($now - $start >= $hardCap) {
                $this->terminateProcess($process, $pipes);
                return [false, $buffer, "durée maximale atteinte ({$hardCap}s)"];
            }

            // Battement de cœur toutes les ~12 s.
            if ($now - $lastBeat >= 12) {
                $elapsed = $now - $start;
                $suffix  = $tail !== '' ? " · {$tail}" : '';
                $log('image', "⏳ Téléchargement en cours… ({$elapsed}s){$suffix}", 'info');
                $lastBeat = $now;
            }
        }

        // Inatteignable : chaque sortie de boucle retourne explicitement.
    }

    /**
     * Tue proprement le processus de pull : SIGTERM, puis SIGKILL s'il ne rend
     * pas la main rapidement.
     */
    private function terminateProcess($process, array $pipes): void
    {
        @proc_terminate($process, 15);

        for ($i = 0; $i < 10; $i++) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            usleep(300000);
        }

        $status = proc_get_status($process);
        if ($status['running']) {
            @proc_terminate($process, 9);
        }

        if (isset($pipes[1]) && is_resource($pipes[1])) {
            @fclose($pipes[1]);
        }
        @proc_close($process);
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

    /**
     * Équivalent de pullDockerImage()/pinToTag() pour l'image du site vitrine
     * (wetchah_site), sur un registre distinct de l'image applicative — le
     * digest est figé séparément (Tenant::web_image_tag) pour ne pas coupler
     * les mises à jour des deux images.
     */
    private function pullWebImage(Tenant $tenant, callable $log): string
    {
        $registryImage = config('provisioning.registry_image_web');

        if (empty($tenant->web_image_tag)) {
            $log('image', "📌 Résolution de la version du site « latest »…", 'info');

            $imagePath = $this->registry->imagePath($registryImage);
            $digest    = $this->registry->resolveDigest($imagePath, 'latest');

            if (!$digest) {
                throw new RuntimeException(
                    "Impossible de résoudre le digest de l'image « {$registryImage}:latest » sur le registre."
                );
            }

            $tenant->update(['web_image_tag' => $digest]);
            $log('image', "✅ Version du site figée pour cet établissement : {$digest}", 'success');
        }

        return $this->pullPinnedWebImage($tenant, $log);
    }

    private function pullPinnedWebImage(Tenant $tenant, callable $log): string
    {
        $imageRef = config('provisioning.registry_image_web') . '@' . $tenant->web_image_tag;

        $log('image', "⬇️  Récupération de l'image du site « {$imageRef} »…", 'info');
        $this->dockerPullWithRetry($imageRef, $log);
        $log('image', "✅ Image du site prête.", 'success');

        return $imageRef;
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

    /**
     * Échappe une valeur pour l'insérer dans une chaîne YAML *simple-quote*
     * ('...'): seule la quote simple littérale doit être doublée.
     */
    private function yamlSingleQuoteEscape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function generateDockerCompose(Tenant $tenant, string $imageRef, ?string $webImageRef, callable $log): string
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
        $appKey       = $this->resolveAppKey($composePath);
        // URL navigable depuis le navigateur (port mappé sur l'hôte) : sert
        // de base à asset()/config('app.url') pour que les images (chambres,
        // logo...) exposées à un service externe comme le site vitrine
        // pointent vers une URL réellement joignable, pas le hostname interne.
        $appUrl       = 'http://localhost:' . $appPort;
        $currency     = $tenant->currency ?? 'XAF';
        // Le logo importé côté admin reste côté admin (storage propre à erp_pms) :
        // pas de lien inter-conteneurs. Le manager importe son propre logo depuis
        // les paramètres de l'application (stocké dans le storage du tenant).
        $settings     = collect($tenant->settings ?? [])->except('logo')->all();
        // Ces valeurs sont injectées dans une chaîne YAML *simple-quote*, qui ne
        // traite pas le backslash comme un caractère d'échappement (contrairement
        // aux chaînes double-quote) — seule une quote simple littérale doit être
        // doublée. addslashes() ici produirait un JSON qui ne se décode jamais
        // correctement côté tenant (bug historique : TENANT_SETTINGS/TENANT_MODULES
        // n'ont jamais pu être json_decode() correctement).
        $settingsJson = $this->yamlSingleQuoteEscape(json_encode($settings));
        $modulesJson  = $this->yamlSingleQuoteEscape(json_encode($tenant->modules ?? []));
        // Secret partagé pms <-> tenant pour vérifier les jetons d'assistance
        // (Support > Mode assistance). Injecté ici pour que le container
        // dispose de la même clé que celle qui signe les jetons côté pms.
        $assistanceSecret = (string) config('assistance.secret');
        // Clés VAPID communes pour les notifications Web Push des tenants
        // (identifient l'éditeur de l'application, pas l'établissement).
        $vapidSubject = (string) env('VAPID_SUBJECT', 'mailto:admin@meka-erp.local');
        $vapidPublic  = (string) env('VAPID_PUBLIC_KEY', '');
        $vapidPrivate = (string) env('VAPID_PRIVATE_KEY', '');
        // Secret de service pour que la console business de pms consomme
        // l'API de reporting (données financières) de cet établissement.
        $reportingSecret = (string) env('REPORTING_SECRET', '');

        $appService = <<<YAML
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
      ASSISTANCE_SECRET: "{$assistanceSecret}"
      VAPID_SUBJECT: "{$vapidSubject}"
      VAPID_PUBLIC_KEY: "{$vapidPublic}"
      VAPID_PRIVATE_KEY: "{$vapidPrivate}"
      REPORTING_SECRET: "{$reportingSecret}"
      SESSION_DRIVER: database
      CACHE_STORE: database
      QUEUE_CONNECTION: database
    volumes:
      - meka_erp_{$tenant->slug}_storage:/var/www/html/storage/app/public
    depends_on:
      {$dbContainer}:
        condition: service_healthy
    networks:
      - {$network}

YAML;

        // La base n'est JAMAIS exposée sur l'hôte : l'app la joint via le
        // réseau Docker interne (DB_HOST), pms via le nom du container, les
        // sauvegardes via `docker exec`. L'exposer créait des conflits de
        // port sur l'hôte (ex: 5432 déjà pris par le PostgreSQL local) sans
        // aucun bénéfice.
        $dbService = <<<YAML
  {$dbContainer}:
    image: postgres:16
    container_name: {$dbContainer}
    restart: unless-stopped
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

YAML;

        $services = [$appService, $dbService];

        if ($webImageRef !== null) {
            $webContainer = 'meka-erp-' . $tenant->slug . '-web';
            $webPort      = $tenant->web_port ?? ($appPort + 1000);
            $cmsContainer = config('provisioning.cms_container', 'MEKA_ERP-app');

            $services[] = <<<YAML
  {$webContainer}:
    image: {$webImageRef}
    container_name: {$webContainer}
    restart: unless-stopped
    ports:
      - "{$webPort}:3000"
    environment:
      TENANT_SLUG: "{$tenant->slug}"
      CMS_API_URL: "http://{$cmsContainer}"
      TENANT_API_URL: "http://{$appContainer}"
      # Origine publique du site (navigateur) : requise par adapter-node
      # SvelteKit pour valider les POST de formulaire (protection CSRF),
      # sinon toute soumission est rejetée en "cross-site".
      ORIGIN: "http://localhost:{$webPort}"
    depends_on:
      - {$appContainer}
    networks:
      - {$network}

YAML;
        }

        // Nom de projet Docker propre à chaque établissement : sans lui, tous
        // les composes du dossier .compose partagent le même projet et se
        // voient mutuellement comme « orphelins » — un down/up sur l'un
        // pouvait alors impacter les autres. Isole chaque établissement.
        $projectName = 'meka-erp-' . $tenant->slug;

        $yaml = "name: {$projectName}\n\nservices:\n\n" . implode("\n", $services)
            . "\nnetworks:\n  {$network}:\n    external: true\n\nvolumes:\n  meka_erp_{$tenant->slug}_pgdata:\n  meka_erp_{$tenant->slug}_storage:\n";

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
        $webContainer = 'meka-erp-' . $tenant->slug . '-web';

        $this->exec('docker rm -f ' . escapeshellarg($appContainer) . ' 2>/dev/null');
        $this->exec('docker rm -f ' . escapeshellarg($dbContainer)  . ' 2>/dev/null');
        $this->exec('docker rm -f ' . escapeshellarg($webContainer) . ' 2>/dev/null');

        // Contrôle préventif : le port applicatif (et web) exposé sur l'hôte
        // doit être libre. On échoue tôt avec un message clair plutôt que sur
        // une erreur Docker obscure en plein milieu du provisioning.
        $this->assertHostPortFree((int) $tenant->app_port, 'applicatif', $log);
        if ($this->hasWebsiteModule($tenant)) {
            $this->assertHostPortFree((int) ($tenant->web_port ?? ($tenant->app_port + 1000)), 'du site vitrine', $log);
        }

        try {
            $output = $this->execOrFail(
                'docker compose -f ' . escapeshellarg($composePath) . ' up -d 2>&1',
                "Échec du docker compose up"
            );
        } catch (RuntimeException $e) {
            // Traduit une erreur de bind de port en message actionnable.
            if (preg_match('/Ports are not available|bind:|address already in use|port is already allocated/i', $e->getMessage())) {
                throw new RuntimeException(
                    "Un port choisi pour cet établissement est déjà utilisé sur la machine. "
                    . "Modifiez le port applicatif (et, si activé, le port du site) dans le formulaire, puis relancez le provisioning."
                );
            }
            throw $e;
        }

        foreach (array_filter(explode("\n", $output)) as $line) {
            if (!empty(trim($line))) {
                $log('docker', "  » {$line}", 'info');
            }
        }

        $log('docker', "✅ Containers démarrés.", 'success');
    }

    /**
     * Vérifie qu'aucun container ne publie déjà ce port sur l'hôte. Détecte
     * les conflits avec d'autres établissements/services avant de lancer le
     * compose (message clair plutôt qu'échec Docker opaque).
     */
    private function assertHostPortFree(int $port, string $label, callable $log): void
    {
        if ($port <= 0) {
            return;
        }

        // Liste les ports publiés par les containers en cours, cherche ":{port}->".
        $ports = $this->exec('docker ps --format "{{.Names}} {{.Ports}}" 2>/dev/null');
        foreach (array_filter(explode("\n", $ports)) as $line) {
            if (preg_match('/(?::|\b)' . $port . '->/', $line)) {
                $name = trim(strtok($line, ' '));
                throw new RuntimeException(
                    "Le port {$label} {$port} est déjà utilisé par le container « {$name} ». "
                    . "Choisissez un autre port dans le formulaire de l'établissement."
                );
            }
        }

        $log('docker', "✅ Port {$label} {$port} disponible.", 'info');
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

        $imageRef = $this->pullPinnedImage($tenant, $registryImage, $log);

        // Le tag de l'application est mis à jour indépendamment du site vitrine —
        // l'image "web" n'est pas re-résolue ici, seulement réutilisée si déjà
        // pinnée (le digest ne change pas, donc aucun nouveau pull nécessaire).
        $webImageRef = ($this->hasWebsiteModule($tenant) && !empty($tenant->web_image_tag))
            ? config('provisioning.registry_image_web') . '@' . $tenant->web_image_tag
            : null;

        $composePath = $this->generateDockerCompose($tenant, $imageRef, $webImageRef, $log);

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

    /**
     * Met à jour le site vitrine d'un établissement vers la dernière image
     * publiée (tag "latest" du registre web) : ré-épingle web_image_tag,
     * pull, régénère le compose et recrée uniquement le container "web"
     * (app et base de données intacts). Retourne false si le site est déjà
     * sur la dernière version (aucune action effectuée).
     */
    public function updateWeb(Tenant $tenant, callable $log): bool
    {
        if (empty($tenant->docker_image_tag)) {
            throw new RuntimeException("Établissement non provisionné — aucun site à mettre à jour.");
        }

        if (!$this->hasWebsiteModule($tenant)) {
            throw new RuntimeException("Le module « Site web » n'est pas actif pour cet établissement.");
        }

        $log('start', "Mise à jour du site vitrine de « {$tenant->name} »");

        $registryImage = config('provisioning.registry_image_web');
        $imagePath     = $this->registry->imagePath($registryImage);
        $digest        = $this->registry->resolveDigest($imagePath, 'latest');

        if (!$digest) {
            throw new RuntimeException(
                "Impossible de résoudre le digest de l'image « {$registryImage}:latest » sur le registre."
            );
        }

        if ($digest === $tenant->web_image_tag) {
            $log('done', "✅ Le site est déjà à la dernière version.", 'success');
            return false;
        }

        $tenant->update(['web_image_tag' => $digest]);
        $log('image', "✅ Nouvelle version du site figée : {$digest}", 'success');

        $webImageRef = $this->pullPinnedWebImage($tenant, $log);
        $appImageRef = config('provisioning.registry_image') . '@' . $tenant->docker_image_tag;
        $composePath = $this->generateDockerCompose($tenant, $appImageRef, $webImageRef, $log);

        $log('docker', "🔄 Recréation du container du site…", 'info');
        $this->execOrFail(
            'docker compose -f ' . escapeshellarg($composePath) . ' up -d 2>&1',
            "Échec du docker compose up"
        );
        $log('docker', "✅ Container du site mis à jour.", 'success');

        $tenant->update([
            'docker_status'        => 'running',
            'docker_web_container' => 'meka-erp-' . $tenant->slug . '-web',
        ]);

        $log('done', "✅ Site vitrine de « {$tenant->name} » mis à jour.", 'success');

        return true;
    }

    /**
     * Applique un changement de modules (Tenant::modules déjà sauvegardé par
     * l'appelant) : régénère le compose avec la même image (aucun pull) et
     * recrée uniquement le container applicatif pour que TENANT_MODULES soit
     * repris en compte par l'entrypoint. Base de données/volume intacts.
     *
     * Gère aussi l'activation/désactivation du module "website" : pull (et
     * pin si première activation) de l'image du site quand il devient actif,
     * suppression explicite du container "web" quand il devient inactif —
     * "docker compose up -d" ne retire pas de lui-même un service disparu du
     * fichier généré.
     */
    public function applyModules(Tenant $tenant, callable $log): void
    {
        if (empty($tenant->docker_image_tag)) {
            throw new RuntimeException("Établissement non provisionné — aucune image à recréer.");
        }

        $log('start', "Application des modules pour « {$tenant->name} »");

        $registryImage = config('provisioning.registry_image');
        $imageRef      = $registryImage . '@' . $tenant->docker_image_tag;

        $wantsWebsite = $this->hasWebsiteModule($tenant);
        $webContainer = 'meka-erp-' . $tenant->slug . '-web';
        $webImageRef  = null;

        if ($wantsWebsite) {
            $webImageRef = $this->pullWebImage($tenant, $log);
        } else {
            $this->exec('docker rm -f ' . escapeshellarg($webContainer) . ' 2>/dev/null');
        }

        $composePath = $this->generateDockerCompose($tenant, $imageRef, $webImageRef, $log);

        $log('docker', "🔄 Recréation du container applicatif…", 'info');
        $this->execOrFail(
            'docker compose -f ' . escapeshellarg($composePath) . ' up -d 2>&1',
            "Échec du docker compose up"
        );
        $log('docker', "✅ Container recréé.", 'success');

        $tenant->update([
            'docker_status'         => 'running',
            'docker_web_container'  => $wantsWebsite ? $webContainer : null,
        ]);

        $log('done', "✅ Modules appliqués pour « {$tenant->name} ».", 'success');
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
        if ($tenant->docker_web_container) {
            $this->exec('docker start ' . escapeshellarg($tenant->docker_web_container) . ' 2>&1');
        }
        $log('start', "✅ Containers démarrés.", 'success');
    }

    public function stop(Tenant $tenant, callable $log): void
    {
        $this->exec('docker stop ' . escapeshellarg('meka-erp-' . $tenant->slug . '-app') . ' 2>/dev/null');
        $this->exec('docker stop ' . escapeshellarg('meka-erp-' . $tenant->slug . '-db') . ' 2>/dev/null');
        if ($tenant->docker_web_container) {
            $this->exec('docker stop ' . escapeshellarg($tenant->docker_web_container) . ' 2>/dev/null');
        }
        $log('stop', "✅ Containers arrêtés.", 'success');
    }

    public function restart(Tenant $tenant, callable $log): void
    {
        $this->exec('docker restart ' . escapeshellarg('meka-erp-' . $tenant->slug . '-db') . ' 2>/dev/null');
        $this->exec('docker restart ' . escapeshellarg('meka-erp-' . $tenant->slug . '-app') . ' 2>/dev/null');
        if ($tenant->docker_web_container) {
            $this->exec('docker restart ' . escapeshellarg($tenant->docker_web_container) . ' 2>/dev/null');
        }
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

        $result = [
            'app_container' => $appContainer,
            'db_container'  => $dbContainer,
            'app_status'    => $appStatus,
            'db_status'     => $dbStatus,
            'healthy'       => $appStatus === 'running' && $dbStatus === 'running',
            'url'           => $appStatus === 'running' ? 'http://' . $tenant->slug . '.localhost' : null,
        ];

        if ($tenant->docker_web_container) {
            $webStatus = trim($this->exec(
                'docker inspect -f "{{.State.Status}}" ' . escapeshellarg($tenant->docker_web_container) . ' 2>/dev/null'
            ));
            if ($webStatus === '') { $webStatus = 'absent'; }

            $result['web_container'] = $tenant->docker_web_container;
            $result['web_status']    = $webStatus;
            $result['healthy']       = $result['healthy'] && $webStatus === 'running';
        }

        return $result;
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
            $this->exec('docker volume rm meka_erp_' . $slug . '_storage 2>/dev/null');
            $log('docker', "Conteneurs orphelins et volumes supprimés.", 'warning');
        }

        // Filet de sécurité : le container "web" peut avoir été laissé orphelin
        // si le module website a été désactivé sans que docker_web_container
        // ait été nettoyé (ou si down -v n'a pas trouvé ce service dans le
        // compose au moment de la suppression).
        if ($tenant->docker_web_container) {
            $this->exec('docker rm -f ' . escapeshellarg($tenant->docker_web_container) . ' 2>/dev/null');
        }

        $log('done', "✅ Établissement « {$tenant->name} » supprimé.", 'success');
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}