<?php

namespace App\Console\Commands;

use App\Services\CodeUpdater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Lancée en arrière-plan par le bouton « Mettre à jour le code » de l'onglet
 * Système : une mise à jour peut durer plus longtemps qu'une requête web.
 * Elle écrit son journal et son statut dans storage/app/private/mise-a-jour/,
 * que la page relit toutes les deux secondes.
 */
class MettreAJourCode extends Command
{
    public const DOSSIER = 'mise-a-jour';

    protected $signature = 'code:mettre-a-jour';

    protected $description = 'Tire la branche courante (git pull --ff-only), migre la base et vide les caches';

    public function handle(CodeUpdater $mise): int
    {
        $disque = Storage::disk('local');
        $disque->put(self::DOSSIER . '/journal.log', '');
        $this->statut(['etat' => 'en_cours', 'debut' => now()->toIso8601String()]);

        $ok = false;
        try {
            $ok = $mise->mettreAJour(function (string $niveau, string $message) use ($disque) {
                $disque->append(self::DOSSIER . '/journal.log', json_encode(['h' => now()->format('H:i:s'), 'n' => $niveau, 'm' => $message], JSON_UNESCAPED_UNICODE));
                $this->line("[$niveau] $message");
            });
        } catch (\Throwable $e) {
            $disque->append(self::DOSSIER . '/journal.log', json_encode(['h' => now()->format('H:i:s'), 'n' => 'erreur', 'm' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }

        $this->statut(['etat' => 'termine', 'ok' => $ok, 'fin' => now()->toIso8601String(), 'vide_opcache' => false]);

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function statut(array $donnees): void
    {
        Storage::disk('local')->put(self::DOSSIER . '/statut.json', json_encode($donnees));
    }
}
