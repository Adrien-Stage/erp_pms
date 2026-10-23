<?php

namespace App\Console\Commands;

use App\Services\MiseAJourServeur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Tourne dans le conteneur exécutant (wetchah_erp-maj) que lance le bouton
 * « Mettre à jour la console » de l'onglet Système : les huit étapes du guide
 * (§9.1). Le journal et le statut vont dans storage/app/private/mise-a-jour-serveur/,
 * sur le volume de la console, que la page relit toutes les deux secondes, y
 * compris après le redémarrage de l'étape 6.
 *
 * Avec --verifier : l'état du dépôt et ce qui attend, en JSON, sans rien changer.
 */
class MettreAJourServeur extends Command
{
    protected $signature = 'erp:mettre-a-jour-serveur
        {--conteneur=} {--service=app} {--code=} {--compose=} {--reglages=} {--volume=}
        {--tenants=} {--sauvegardes=/srv/sauvegardes} {--depot-image=wetchah-erp} {--commit-image=}
        {--verifier : Dire ce qui attend, sans rien changer}';

    protected $description = 'Met à jour la console de production (guide, §9.1) — lancée par l\'onglet Système dans un conteneur à part';

    protected $hidden = true;

    public function handle(MiseAJourServeur $mise): int
    {
        $o = [
            'conteneur' => $this->option('conteneur') ?: config('provisioning.cms_container', 'wetchah_erp-app'),
            'service' => $this->option('service') ?: 'app',
            'code' => (string) $this->option('code'),
            'compose' => (string) $this->option('compose'),
            'reglages' => (string) $this->option('reglages'),
            'volume' => (string) $this->option('volume'),
            'tenants' => $this->option('tenants') ?: null,
            'sauvegardes' => $this->option('sauvegardes') ?: '/srv/sauvegardes',
            'depot_image' => $this->option('depot-image') ?: 'wetchah-erp',
            'commit_image' => $this->option('commit-image') ?: null,
        ];

        // Le jeton GitHub éventuel, déposé par la console : lu puis effacé aussitôt.
        $disque = Storage::disk('local');
        $jeton = $disque->exists(MiseAJourServeur::DOSSIER . '/jeton') ? trim((string) $disque->get(MiseAJourServeur::DOSSIER . '/jeton')) : null;

        if ($this->option('verifier')) {
            $this->line(json_encode($mise->verifier($o, $jeton ?: null), JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }
        $disque->delete(MiseAJourServeur::DOSSIER . '/jeton');

        $statut = ['etat' => 'en_cours', 'etape' => 0, 'debut' => now()->toIso8601String()];
        $this->ecrire('statut.json', json_encode($statut));
        $this->ecrire('journal.log', '');
        $this->ecrire('detail.log', '');

        $log = function (string $niveau, string $message) use (&$statut) {
            if ($niveau === 'etape') {
                $statut['etape'] = (int) $message;
                $this->ecrire('statut.json', json_encode($statut));
                $message = 'Étape ' . $message . ' — ' . MiseAJourServeur::ETAPES[(int) $message];
            }
            $this->ajouter('journal.log', json_encode(['h' => now()->format('H:i:s'), 'n' => $niveau, 'e' => $statut['etape'], 'm' => $message], JSON_UNESCAPED_UNICODE) . "\n");
            $this->line("[{$niveau}] {$message}");
        };
        $detail = fn (string $morceau) => $this->ajouter('detail.log', $morceau);

        try {
            $resultat = $mise->derouler($o, $log, $detail, $jeton ?: null);
        } catch (\Throwable $e) {
            $log('erreur', 'Arrêt inattendu : ' . $e->getMessage());
            $resultat = ['ok' => false, 'etape' => $statut['etape']];
        }

        $this->ecrire('statut.json', json_encode(['etat' => 'termine', 'fin' => now()->toIso8601String()] + $resultat + $statut));

        return $resultat['ok'] ? self::SUCCESS : self::FAILURE;
    }

    /** L'exécutant tourne en root : ses fichiers sont rendus à www-data, qui sert la page. */
    private function ecrire(string $fichier, string $contenu): void
    {
        $disque = Storage::disk('local');
        $disque->put(MiseAJourServeur::DOSSIER . '/' . $fichier, $contenu);
        $this->rendre($disque->path(MiseAJourServeur::DOSSIER . '/' . $fichier));
    }

    private function ajouter(string $fichier, string $contenu): void
    {
        $chemin = Storage::disk('local')->path(MiseAJourServeur::DOSSIER . '/' . $fichier);
        file_put_contents($chemin, $contenu, FILE_APPEND);
        $this->rendre($chemin);
    }

    private function rendre(string $chemin): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            foreach ([$chemin, dirname($chemin)] as $c) {
                @chown($c, 33);
                @chgrp($c, 33);
            }
        }
    }
}
