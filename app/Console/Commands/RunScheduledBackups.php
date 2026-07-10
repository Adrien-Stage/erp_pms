<?php

namespace App\Console\Commands;

use App\Models\BackupSchedule;
use App\Services\TenantBackupService;
use Illuminate\Console\Command;

/**
 * Exécute les sauvegardes planifiées dont l'échéance est atteinte. Appelée
 * chaque minute par le scheduler Laravel (voir bootstrap/app.php). Peut aussi
 * être lancée à la main : `php artisan backups:run` ou `--force` pour lancer
 * immédiatement toutes les planifications actives.
 */
class RunScheduledBackups extends Command
{
    protected $signature = 'backups:run {--force : Exécute toutes les planifications actives sans tenir compte de l\'échéance}';

    protected $description = 'Lance les sauvegardes planifiées des établissements dont l\'échéance est atteinte';

    public function handle(TenantBackupService $service): int
    {
        $query = BackupSchedule::with('tenant')->where('enabled', true);

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            });
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->info('Aucune sauvegarde planifiée à exécuter.');
            return self::SUCCESS;
        }

        foreach ($schedules as $schedule) {
            $tenant = $schedule->tenant;
            if (!$tenant) {
                continue;
            }

            $this->line("Sauvegarde planifiée : {$tenant->name}…");
            $backup = $service->backup($tenant, 'scheduled');

            if ($backup->status === 'completed') {
                $service->prune($tenant, $schedule->retention);
                $this->info("  ✔ {$backup->filename} ({$backup->humanSize()})");
            } else {
                $this->error("  x Échec : {$backup->error}");
            }

            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => $schedule->computeNextRun(),
            ]);
        }

        return self::SUCCESS;
    }
}
