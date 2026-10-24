<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/**
 * Met à jour le code de la console depuis son dépôt git : « git pull » sur la
 * branche courante, puis migrations et vidage des caches.
 *
 * Ne s'applique qu'à une console dont le dossier est un dépôt git — c'est le cas
 * de la pile d'essai (docker-compose.yml, code monté depuis l'hôte). L'image de
 * production n'embarque pas .git : son code se met à jour en reconstruisant l'image.
 *
 * Qui possède les fichiers ? Le code monté appartient à l'utilisateur de l'hôte,
 * pas à www-data, qui n'y écrit donc pas. Git s'exécute alors « en tant que »
 * le propriétaire du dossier .git, par `docker exec -u` sur le conteneur de la
 * console : mêmes fichiers, mêmes droits que si l'on tapait la commande à la main.
 */
class CodeUpdater
{
    public const FICHIERS_ASSETS = ['package.json', 'package-lock.json', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js'];

    /** @param Closure(list<string>, int):array{0:int,1:string,2:string}|null $executeur remplaçable pour les tests */
    public function __construct(private readonly ?Closure $executeur = null, private readonly ?string $dossier = null)
    {
    }

    public function depot(): string
    {
        return $this->dossier ?? base_path();
    }

    public function estUnDepot(): bool
    {
        return file_exists($this->depot() . '/.git');
    }

    /** « docker » si git doit passer par le conteneur, « direct » sinon. */
    public function mode(): string
    {
        return $this->uidProprietaire() !== $this->uidCourant() && $this->dockerDisponible() ? 'docker' : 'direct';
    }

    /** @return array<string,mixed> */
    public function etat(): array
    {
        if (!$this->estUnDepot()) {
            return ['depot' => false];
        }

        $branche = $this->sortie(['rev-parse', '--abbrev-ref', 'HEAD']);
        [$codeCommit, $commit] = $this->git(['log', '-1', '--format=%h%x09%s%x09%cr']);
        [$h, $m, $d] = array_pad(explode("\t", trim($commit)), 3, '');
        [$codeAmont, $amont] = $this->git(['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}']);
        $modifies = array_values(array_filter(explode("\n", $this->sortie(['status', '--porcelain', '--untracked-files=no']))));

        return [
            'depot' => true,
            'mode' => $this->mode(),
            'branche' => $branche === 'HEAD' ? null : $branche,
            'detache' => $branche === 'HEAD',
            'commit' => $codeCommit === 0 ? ['hash' => $h, 'message' => $m, 'date' => $d] : null,
            'remote' => $this->nettoyerUrl($this->sortie(['remote', 'get-url', 'origin'])),
            'amont' => $codeAmont === 0 ? trim($amont) : null,
            'modifies' => $modifies,
        ];
    }

    /** Récupère l'état distant puis dit ce qui attend. @return array<string,mixed> */
    public function verifier(): array
    {
        [$code, , $err] = $this->git(['fetch', '--prune'], 90);
        if ($code !== 0) {
            return ['ok' => false, 'message' => self::expliquerErreur($err)];
        }

        [$c, $compte] = $this->git(['rev-list', '--left-right', '--count', 'HEAD...@{upstream}']);
        if ($c !== 0) {
            return ['ok' => false, 'message' => "Cette branche ne suit aucune branche distante. Sur le serveur : git branch --set-upstream-to=origin/<branche>"];
        }
        [$devant, $derriere] = array_map('intval', preg_split('/\s+/', trim($compte)));

        $commits = [];
        if ($derriere > 0) {
            $commits = array_map(
                fn ($l) => array_pad(explode("\t", $l), 3, ''),
                array_slice(array_filter(explode("\n", $this->sortie(['log', '--format=%h%x09%s%x09%cr', '-n', '20', 'HEAD..@{upstream}']))), 0, 20)
            );
        }

        return ['ok' => true, 'devant' => $devant, 'derriere' => $derriere, 'commits' => $commits];
    }

    /**
     * Déroule la mise à jour ; chaque étape est dite à $log. Renvoie true si le
     * code est à jour à la fin (même si une étape annexe a donné un avertissement).
     *
     * @param callable(string,string):void $log  niveau (info|ok|warn|erreur), message
     */
    public function mettreAJour(callable $log): bool
    {
        $etat = $this->etat();
        if (!$etat['depot']) {
            $log('erreur', "Le dossier de la console n'est pas un dépôt git : son code est dans l'image Docker. Mettez-la à jour en la reconstruisant.");
            return false;
        }
        if ($etat['detache'] || !$etat['branche']) {
            $log('erreur', "Aucune branche n'est active (HEAD détaché) : rien à tirer.");
            return false;
        }
        if (!$etat['amont']) {
            $log('erreur', "La branche « {$etat['branche']} » ne suit aucune branche distante (git branch --set-upstream-to=origin/{$etat['branche']}).");
            return false;
        }
        if ($etat['modifies']) {
            $log('erreur', "Des fichiers suivis ont été modifiés à la main ; la mise à jour les écraserait :");
            foreach (array_slice($etat['modifies'], 0, 8) as $ligne) {
                $log('erreur', '   ' . $ligne);
            }
            $log('erreur', "Annulez ces changements (git checkout -- <fichier>) ou enregistrez-les (git commit), puis relancez.");
            return false;
        }

        $log('info', "Branche « {$etat['branche']} » (suivi : {$etat['amont']}), mode {$etat['mode']}.");
        $log('info', 'Récupération des nouveautés (git fetch)…');
        $verif = $this->verifier();
        if (!$verif['ok']) {
            $log('erreur', $verif['message']);
            return false;
        }
        if ($verif['devant'] > 0 && $verif['derriere'] > 0) {
            $log('erreur', "La branche a divergé de {$etat['amont']} ({$verif['devant']} commit(s) locaux, {$verif['derriere']} distants) : une mise à jour sans conflit est impossible. Résolvez-le à la main.");
            return false;
        }
        if ($verif['derriere'] === 0) {
            $log('ok', 'Déjà à jour : aucun nouveau commit.');
            return true;
        }

        $avant = $this->sortie(['rev-parse', 'HEAD']);
        $log('info', "{$verif['derriere']} nouveau(x) commit(s) à appliquer (git pull --ff-only)…");
        [$code, $sortie, $err] = $this->git(['pull', '--ff-only'], 180);
        if ($code !== 0) {
            $log('erreur', self::expliquerErreur($err ?: $sortie));
            return false;
        }
        $apres = $this->sortie(['rev-parse', 'HEAD']);
        $fichiers = array_values(array_filter(explode("\n", $this->sortie(['diff', '--name-only', $avant, $apres]))));
        $log('ok', 'Code mis à jour : ' . substr($avant, 0, 7) . ' → ' . substr($apres, 0, 7) . ' (' . count($fichiers) . ' fichier(s)).');

        if (in_array('composer.lock', $fichiers, true)) {
            $log('warn', "composer.lock a changé : les dépendances PHP ne se mettent pas à jour ici. Reconstruisez l'image (docker compose build app, puis up -d).");
        }

        if ($this->assetsChanges($fichiers)) {
            $this->recompilerAssets($log);
        }

        $this->artisan('migrate', ['--force' => true], 'Migrations de la base', $log);
        $this->artisan('optimize:clear', [], 'Caches vidés', $log);
        $this->redemarrerPlanificateur($log);

        $log('ok', 'Mise à jour terminée. Rechargez la page pour voir les changements.');
        return true;
    }

    // ── Utilitaires ──────────────────────────────────────────────────────────

    private function assetsChanges(array $fichiers): bool
    {
        foreach ($fichiers as $f) {
            if (str_starts_with($f, 'resources/') || in_array($f, self::FICHIERS_ASSETS, true)) {
                return true;
            }
        }

        return false;
    }

    private function recompilerAssets(callable $log): void
    {
        $log('info', 'Les écrans ont changé : compilation (npm run build), une à deux minutes…');
        $cmd = ['npm', 'run', 'build'];
        if ($this->mode() === 'docker') {
            // vendor et node_modules appartiennent à root dans le conteneur : on compile en root.
            $cmd = ['docker', 'exec', '-u', '0', '-w', $this->depot(), $this->conteneur(), ...$cmd];
        }
        [$code, , $err] = $this->lancer($cmd, 420, $this->depot());
        if ($code !== 0) {
            $log('warn', "La compilation a échoué : les anciens écrans restent servis. Lancez à la main : docker compose exec app npm run build.");
            if (trim($err) !== '') {
                $log('warn', substr(trim($err), -300));
            }
            return;
        }
        if ($this->mode() === 'docker') {
            $this->lancer(['docker', 'exec', '-u', '0', $this->conteneur(), 'chmod', '-R', 'a+rwX', $this->depot() . '/public/build'], 30);
        }
        $log('ok', 'Écrans compilés.');
    }

    private function artisan(string $commande, array $options, string $libelle, callable $log): void
    {
        try {
            $code = Artisan::call($commande, $options);
            $log($code === 0 ? 'ok' : 'warn', $libelle . ($code === 0 ? '.' : " : code de sortie {$code}. " . trim(Artisan::output())));
        } catch (\Throwable $e) {
            $log('warn', "{$libelle} : {$e->getMessage()}");
        }
    }

    /** Le planificateur garde l'ancien code en mémoire : on l'arrête, supervisord le relance. */
    private function redemarrerPlanificateur(callable $log): void
    {
        if (!function_exists('posix_kill') || !is_dir('/proc')) {
            return;
        }
        $n = 0;
        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $f) {
            $cmd = str_replace("\0", ' ', (string) @file_get_contents($f));
            if (str_contains($cmd, 'artisan schedule:work')) {
                $pid = (int) basename(dirname($f));
                if ($pid > 1 && @posix_kill($pid, 15)) {
                    $n++;
                }
            }
        }
        if ($n > 0) {
            $log('ok', 'Planificateur relancé avec le nouveau code.');
        }
    }

    /** @return array{0:int,1:string,2:string} code, sortie, erreurs */
    private function git(array $args, int $delai = 60): array
    {
        $cmd = ['git', '-c', 'safe.directory=' . $this->depot(), '-C', $this->depot(), ...$args];
        if ($this->mode() === 'docker') {
            $cmd = ['docker', 'exec', '-u', (string) $this->uidProprietaire(), '-e', 'HOME=/tmp', '-e', 'GIT_TERMINAL_PROMPT=0', '-w', $this->depot(), $this->conteneur(), ...$cmd];
        }

        return $this->lancer($cmd, $delai);
    }

    private function sortie(array $args): string
    {
        return trim($this->git($args)[1]);
    }

    /** @return array{0:int,1:string,2:string} */
    private function lancer(array $cmd, int $delai, ?string $dossier = null): array
    {
        if ($this->executeur) {
            return ($this->executeur)($cmd, $delai);
        }
        $p = new Process($cmd, $dossier, ['GIT_TERMINAL_PROMPT' => '0', 'HOME' => sys_get_temp_dir()], null, $delai);
        try {
            $p->run();
        } catch (\Throwable $e) {
            return [1, '', $e->getMessage()];
        }

        return [$p->getExitCode() ?? 1, $p->getOutput(), $p->getErrorOutput()];
    }

    /** Ce que dit git, traduit en ce qu'il faut faire. Sert aussi à la mise à jour de production. */
    public static function expliquerErreur(string $erreur): string
    {
        $erreur = trim($erreur);
        return match (true) {
            str_contains($erreur, 'Permission denied (publickey)'), str_contains($erreur, 'Host key verification') =>
                "Le dépôt est configuré en SSH, dont la console n'a pas les clés. Passez-le en HTTPS : git remote set-url origin https://github.com/<organisation>/<dépôt>.git",
            str_contains($erreur, 'could not read Username'), str_contains($erreur, 'Authentication failed'), str_contains($erreur, 'terminal prompts disabled') =>
                "Le dépôt demande un identifiant (dépôt privé). Enregistrez un jeton dans l'adresse du dépôt : git remote set-url origin https://<jeton>@github.com/<organisation>/<dépôt>.git",
            str_contains($erreur, 'Could not resolve host'), str_contains($erreur, 'unable to access') =>
                "Le serveur n'atteint pas le dépôt distant (réseau ou DNS) : " . substr($erreur, 0, 200),
            str_contains($erreur, 'Not possible to fast-forward'), str_contains($erreur, 'diverging') =>
                "La branche locale a divergé du dépôt distant : une mise à jour sans conflit est impossible.",
            str_contains($erreur, 'Permission denied'), str_contains($erreur, 'cannot open') =>
                "Droits insuffisants pour écrire dans le dépôt : " . substr($erreur, 0, 200),
            default => $erreur !== '' ? substr($erreur, 0, 400) : 'Échec sans message de git.',
        };
    }

    /** Retire identifiant et jeton d'une adresse https://jeton@hôte/… avant de l'afficher. */
    private function nettoyerUrl(string $url): string
    {
        return preg_replace('#(https?://)[^/@]+@#', '$1', trim($url)) ?? '';
    }

    private function conteneur(): string
    {
        return (string) config('provisioning.cms_container', 'wetchah_erp-app');
    }

    private function uidProprietaire(): int
    {
        return (int) (@fileowner($this->depot() . '/.git') ?: 0);
    }

    private function uidCourant(): int
    {
        return function_exists('posix_geteuid') ? posix_geteuid() : 0;
    }

    private function dockerDisponible(): bool
    {
        return file_exists('/var/run/docker.sock') && file_exists('/.dockerenv');
    }
}
