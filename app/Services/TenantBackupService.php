<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantBackup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Sauvegarde de la base d'un établissement : lance pg_dump dans son container
 * PostgreSQL (via le socket Docker partagé, comme TenantProvisioningService),
 * puis archive le dump compressé sur le disque local de pms. Cross-container
 * safe : la sortie de pg_dump remonte au shell de pms où gzip et la
 * redirection fichier s'exécutent (pas de dépendance au FS du tenant).
 */
class TenantBackupService
{
    /** Répertoire des backups sur le disque 'local' (storage/app/...). */
    public const DIR = 'backups';

    /**
     * Prend une sauvegarde et l'enregistre. $trigger : 'manual' | 'scheduled'.
     */
    public function backup(Tenant $tenant, string $trigger = 'manual'): TenantBackup
    {
        $dbContainer = $tenant->docker_db_container ?: ('meka-erp-' . $tenant->slug . '-db');
        $dbName      = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
        $dbUser      = $tenant->db_username ?? 'pms';
        $dbPass      = $tenant->db_password ?? 'secret';

        $filename = sprintf('%s_%s.sql.gz', $tenant->slug, now()->format('Ymd_His'));
        $relPath  = self::DIR . '/' . $filename;
        $absPath  = Storage::disk('local')->path($relPath);

        Storage::disk('local')->makeDirectory(self::DIR);

        // Vérifie que le container DB tourne avant de tenter le dump
        $running = trim($this->exec(
            'docker inspect -f "{{.State.Running}}" ' . escapeshellarg($dbContainer) . ' 2>/dev/null'
        ));
        if ($running !== 'true') {
            return $this->recordFailure($tenant, $filename, $relPath, $trigger,
                "Le container de base de données « {$dbContainer} » n'est pas démarré.");
        }

        // pg_dump dans le container DB, gzip + redirection côté pms.
        // --clean --if-exists : le dump peut être rejoué sur une base existante.
        $cmd = 'docker exec -e PGPASSWORD=' . escapeshellarg($dbPass) . ' '
             . escapeshellarg($dbContainer)
             . ' pg_dump -U ' . escapeshellarg($dbUser) . ' -d ' . escapeshellarg($dbName)
             . ' --clean --if-exists --no-owner --no-privileges'
             . ' 2>/dev/null | gzip > ' . escapeshellarg($absPath);

        $returnCode = 0;
        $out = [];
        exec($cmd, $out, $returnCode);

        clearstatcache();
        $size = is_file($absPath) ? (int) filesize($absPath) : 0;

        // pg_dump vide (échec silencieux) => gzip produit ~20 octets d'en-tête
        if ($returnCode !== 0 || $size < 40) {
            if (is_file($absPath)) { @unlink($absPath); }
            return $this->recordFailure($tenant, $filename, $relPath, $trigger,
                "Échec du pg_dump (code {$returnCode}). Vérifiez les identifiants et l'état de la base.");
        }

        Log::info("[Backup] {$tenant->slug} -> {$filename} ({$size} o, {$trigger})");

        return TenantBackup::create([
            'tenant_id'  => $tenant->id,
            'filename'   => $filename,
            'path'       => $relPath,
            'size_bytes' => $size,
            'status'     => 'completed',
            'trigger'    => $trigger,
        ]);
    }

    /**
     * Chemin du fichier tel que visible depuis le PC hôte (le storage du
     * container est monté depuis l'hôte) — pour affichage dans les messages
     * de confirmation. Repli sur le chemin interne si BACKUPS_HOST_PATH
     * n'est pas configuré.
     */
    public function displayPath(TenantBackup $backup): string
    {
        $host = rtrim((string) config('backups.host_path'), '/\\');

        if ($host !== '') {
            $sep = str_contains($host, '\\') ? '\\' : '/';
            return $host . $sep . $backup->filename;
        }

        return Storage::disk('local')->path($backup->path);
    }

    /**
     * Dossier des backups côté PC hôte (pour les messages globaux).
     */
    public function displayDir(): string
    {
        $host = rtrim((string) config('backups.host_path'), '/\\');

        return $host !== '' ? $host : Storage::disk('local')->path(self::DIR);
    }

    /**
     * Restaure (importe) une sauvegarde dans la base de l'établissement :
     * rejoue le dump SQL via psql dans son container PostgreSQL. Le dump est
     * généré avec --clean --if-exists : les tables sont supprimées puis
     * recréées — les données actuelles sont INTÉGRALEMENT remplacées par
     * celles de la sauvegarde. Retourne [ok(bool), erreur(?string)].
     */
    public function restore(Tenant $tenant, string $relPath): array
    {
        $dbContainer = $tenant->docker_db_container ?: ('meka-erp-' . $tenant->slug . '-db');
        $dbName      = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
        $dbUser      = $tenant->db_username ?? 'pms';
        $dbPass      = $tenant->db_password ?? 'secret';

        $abs = Storage::disk('local')->path($relPath);
        if (!is_file($abs)) {
            return [false, 'Fichier de sauvegarde introuvable sur le disque.'];
        }

        $running = trim($this->exec(
            'docker inspect -f "{{.State.Running}}" ' . escapeshellarg($dbContainer) . ' 2>/dev/null'
        ));
        if ($running !== 'true') {
            return [false, "Le container de base de données « {$dbContainer} » n'est pas démarré."];
        }

        $isGz = str_ends_with($abs, '.gz');

        // Intégrité de l'archive avant de toucher à la base : un gunzip qui
        // échoue en cours de pipe laisserait psql sortir en 0 sur une entrée
        // vide, masquant l'échec.
        if ($isGz) {
            $check = 0;
            exec('gzip -t ' . escapeshellarg($abs) . ' 2>&1', $o, $check);
            if ($check !== 0) {
                return [false, 'Archive corrompue (test gzip échoué) — restauration annulée.'];
            }
        }

        $reader = $isGz ? 'gunzip -c' : 'cat';
        $cmd = $reader . ' ' . escapeshellarg($abs)
             . ' | docker exec -i -e PGPASSWORD=' . escapeshellarg($dbPass) . ' '
             . escapeshellarg($dbContainer)
             . ' psql -U ' . escapeshellarg($dbUser) . ' -d ' . escapeshellarg($dbName)
             . ' -q -v ON_ERROR_STOP=1 2>&1';

        $out = [];
        $code = 0;
        exec($cmd, $out, $code);

        if ($code !== 0) {
            $tail = implode(' | ', array_slice(array_filter($out), -3));
            Log::error("[Backup] restore {$tenant->slug} FAILED ({$code}): {$tail}");
            return [false, 'Échec de la restauration (psql code ' . $code . ') : ' . ($tail ?: 'voir les logs.')];
        }

        Log::info("[Backup] restore {$tenant->slug} <- {$relPath} OK");

        return [true, null];
    }

    /**
     * Conserve les $keep backups complétés les plus récents d'un tenant,
     * supprime les plus anciens (fichier + enregistrement).
     */
    public function prune(Tenant $tenant, int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $old = TenantBackup::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->skip($keep)
            ->take(100)
            ->get();

        foreach ($old as $backup) {
            Storage::disk('local')->delete($backup->path);
            $backup->delete();
        }
    }

    public function delete(TenantBackup $backup): void
    {
        Storage::disk('local')->delete($backup->path);
        $backup->delete();
    }

    private function recordFailure(Tenant $tenant, string $filename, string $relPath, string $trigger, string $error): TenantBackup
    {
        Log::error("[Backup] {$tenant->slug} FAILED: {$error}");

        return TenantBackup::create([
            'tenant_id'  => $tenant->id,
            'filename'   => $filename,
            'path'       => $relPath,
            'size_bytes' => 0,
            'status'     => 'failed',
            'trigger'    => $trigger,
            'error'      => $error,
        ]);
    }

    private function exec(string $cmd): string
    {
        return (string) shell_exec($cmd);
    }
}
