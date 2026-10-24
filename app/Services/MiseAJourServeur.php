<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Mise à jour d'une console de production (chemin B) depuis l'onglet Système :
 * les huit étapes du guide de déploiement (§9.1), dans le même ordre, sans
 * terminal.
 *
 * La console ne peut pas se reconstruire depuis son propre conteneur : l'étape 6
 * le remplace, et tout ce qui tourne dedans s'arrête avec lui. Elle lance donc un
 * conteneur « exécutant » à part (wetchah_erp-maj, même image qu'elle), qui monte
 * le dossier du code, le fichier de réglages, le dossier des sauvegardes et le
 * socket Docker aux mêmes chemins que sur l'hôte. Il déroule les étapes et écrit
 * son journal dans le volume storage de la console : la console redémarrée le
 * relit, et la page reprend où elle en était.
 *
 * Les chemins de l'hôte viennent des étiquettes que docker compose pose sur le
 * conteneur de la console (dossier du projet, fichier Compose, fichier de
 * réglages) : rien à configurer. Git s'exécute sous le propriétaire du dépôt,
 * comme si on tapait la commande à la main, pour ne pas lui laisser de fichiers
 * appartenant à root.
 */
class MiseAJourServeur
{
    public const DOSSIER = 'mise-a-jour-serveur';

    public const EXECUTANT = 'wetchah_erp-maj';

    /** Étiquette de l'image : le commit dont elle est faite (posée à l'étape 5). */
    public const ETIQUETTE_COMMIT = 'org.wetchah.erp.commit';

    /** La nouvelle version demande quelques gigaoctets (étape 1). */
    public const ESPACE_MINIMUM = 5 * 1024 * 1024 * 1024;

    public const ETAPES = [
        1 => 'Sauvegarder et noter la version actuelle',
        2 => 'Télécharger le nouveau code',
        3 => "Vérifier s'il y a de nouveaux réglages",
        4 => 'Donner un numéro à la nouvelle version',
        5 => 'Fabriquer la nouvelle version',
        6 => 'Démarrer la nouvelle version',
        7 => 'Appliquer les changements de base',
        8 => 'Vérifier',
    ];

    /** Attente de l'état « healthy » à l'étape 8, en secondes. */
    private const ATTENTE_SANTE = 360;

    /**
     * @param Closure(list<string>, int, array<string,string>, ?callable, ?string):array{0:int,1:string,2:string}|null $executeur
     *        remplaçable pour les tests : commande, délai, variables, flux de sortie, dossier
     * @param Closure(int):void|null $pause  remplaçable pour les tests
     */
    public function __construct(private readonly ?Closure $executeur = null, private readonly ?Closure $pause = null)
    {
    }

    // ── Côté console ─────────────────────────────────────────────────────────

    /**
     * Ce que la console sait d'elle-même, lu sur son propre conteneur.
     *
     * @return array<string,mixed> « disponible » et « raison » si elle ne peut pas se mettre à jour seule
     */
    public function contexte(): array
    {
        $conteneur = $this->conteneur();
        [$code, $sortie] = $this->lancer(['docker', 'inspect', $conteneur], 20);
        $info = $code === 0 ? (json_decode($sortie, true)[0] ?? null) : null;
        if (!is_array($info)) {
            return ['disponible' => false, 'raison' => "La console ne se trouve pas dans Docker (« docker inspect {$conteneur} » a échoué) : la mise à jour se fait à la main (guide, §9.1)."];
        }

        $etiquettes = $info['Config']['Labels'] ?? [];
        $dossier = $etiquettes['com.docker.compose.project.working_dir'] ?? null;
        $fichiers = $etiquettes['com.docker.compose.project.config_files'] ?? null;
        if (!$dossier || !$fichiers) {
            return ['disponible' => false, 'raison' => "La console n'a pas été démarrée par docker compose : la mise à jour se fait à la main (guide, §9.1)."];
        }
        if (str_starts_with($dossier, '/data/compose/')) {
            return ['disponible' => false, 'raison' => "Console installée depuis Portainer : la mise à jour se fait dans Portainer (Stacks → wetchah-erp → Pull and redeploy), puis les étapes 7 et 8 du guide (§9.1)."];
        }

        $volume = null;
        foreach ($info['Mounts'] ?? [] as $montage) {
            if (($montage['Destination'] ?? '') === '/var/www/html/storage' && ($montage['Type'] ?? '') === 'volume') {
                $volume = $montage['Name'] ?? null;
            }
        }
        if (!$volume) {
            return ['disponible' => false, 'raison' => "Le volume storage de la console est introuvable : la mise à jour se fait à la main (guide, §9.1)."];
        }

        $variables = [];
        foreach ($info['Config']['Env'] ?? [] as $ligne) {
            [$nom, $valeur] = array_pad(explode('=', $ligne, 2), 2, '');
            $variables[$nom] = $valeur;
        }

        $image = (string) ($info['Config']['Image'] ?? '');
        $deuxPoints = strrpos($image, ':');
        $depotImage = $deuxPoints === false || str_contains(substr($image, $deuxPoints), '/') ? $image : substr($image, 0, $deuxPoints);
        $version = $deuxPoints === false || $depotImage === $image ? 'latest' : substr($image, $deuxPoints + 1);

        // Le fichier de réglages passé par --env-file ; sinon, celui du guide (à côté du dossier du code).
        $reglages = $etiquettes['com.docker.compose.project.environment_file'] ?? null;

        return [
            'disponible' => true,
            'conteneur' => $conteneur,
            'service' => $etiquettes['com.docker.compose.service'] ?? 'app',
            'image' => $image,
            'depot_image' => $depotImage,
            'version' => $version,
            'commit_image' => ($etiquettes[self::ETIQUETTE_COMMIT] ?? '') ?: null,
            'code' => $dossier,
            'compose' => trim(explode(',', $fichiers)[0]),
            'reglages' => $reglages ? trim(explode(',', $reglages)[0]) : dirname($dossier) . '/erp.env',
            'volume' => $volume,
            'tenants' => ($variables['TENANTS_BASE_PATH'] ?? '') ?: null,
            'sauvegardes' => (string) config('provisioning.sauvegardes_console', '/srv/sauvegardes'),
            'demarre' => $info['State']['StartedAt'] ?? null,
        ];
    }

    /** Une mise à jour tourne-t-elle (le conteneur exécutant existe) ? */
    public function executantActif(): bool
    {
        [$code, $sortie] = $this->lancer(['docker', 'ps', '--filter', 'name=' . self::EXECUTANT, '--format', '{{.Names}}'], 20);

        return $code === 0 && in_array(self::EXECUTANT, array_map('trim', explode("\n", $sortie)), true);
    }

    /**
     * La commande « docker run » du conteneur exécutant : même image que la
     * console, chemins de l'hôte montés à l'identique.
     *
     * @param array<string,mixed> $contexte
     * @return list<string>
     */
    public function commandeExecutant(array $contexte, bool $verifier): array
    {
        $dossierReglages = dirname($contexte['reglages']);
        $montages = ['/var/run/docker.sock:/var/run/docker.sock', "{$dossierReglages}:{$dossierReglages}"];
        if (!str_starts_with($contexte['code'] . '/', rtrim($dossierReglages, '/') . '/')) {
            $montages[] = "{$contexte['code']}:{$contexte['code']}";
        }
        $montages[] = "{$contexte['volume']}:/var/www/html/storage";
        if (!$verifier) {
            $montages[] = "{$contexte['sauvegardes']}:{$contexte['sauvegardes']}";
            if ($contexte['tenants']) {
                $montages[] = "{$contexte['tenants']}:{$contexte['tenants']}:ro";
            }
        }

        $commande = ['docker', 'run', '--rm'];
        if (!$verifier) {
            $commande = [...$commande, '-d', '--name', self::EXECUTANT];
        }
        $commande = [...$commande, '--label', 'org.wetchah.erp.role=mise-a-jour'];
        foreach ($montages as $m) {
            $commande = [...$commande, '-v', $m];
        }

        // Peu de variables : celles de l'exécutant passeraient avant le fichier de
        // réglages dans « docker compose » (TZ, APP_KEY…). Le journal Laravel va
        // sur la sortie d'erreur, pour ne rien écrire en root dans storage/logs.
        $commande = [...$commande, '-e', 'LOG_CHANNEL=stderr', '-e', 'APP_ENV=production', '-w', '/var/www/html',
            '--entrypoint', 'php', $contexte['image'], 'artisan', 'erp:mettre-a-jour-serveur'];

        foreach (['conteneur', 'service', 'code', 'compose', 'reglages', 'volume', 'tenants', 'sauvegardes', 'depot_image', 'commit_image'] as $cle) {
            if (($contexte[$cle] ?? null) !== null && $contexte[$cle] !== '') {
                $commande[] = '--' . str_replace('_', '-', $cle) . '=' . $contexte[$cle];
            }
        }
        if ($verifier) {
            $commande[] = '--verifier';
        }

        return $commande;
    }

    /** Lance la mise à jour en arrière-plan ; le conteneur exécutant survit au redémarrage de la console. */
    public function demarrer(array $contexte, ?string $jeton): void
    {
        $disque = Storage::disk('local');
        $disque->put(self::DOSSIER . '/journal.log', '');
        $disque->put(self::DOSSIER . '/detail.log', '');
        $disque->put(self::DOSSIER . '/statut.json', json_encode(['etat' => 'en_cours', 'etape' => 0, 'debut' => now()->toIso8601String()]));
        $this->deposerJeton($jeton);

        [$code, , $err] = $this->lancer($this->commandeExecutant($contexte, false), 120);
        if ($code !== 0) {
            $disque->delete(self::DOSSIER . '/jeton');
            $disque->put(self::DOSSIER . '/statut.json', json_encode(['etat' => 'termine', 'ok' => false, 'etape' => 0, 'fin' => now()->toIso8601String()]));
            throw new \RuntimeException("Le conteneur de mise à jour n'a pas démarré : " . substr(trim($err), 0, 300));
        }
    }

    /** « Vérifier les nouveautés » : le même exécutant, en lecture, qui répond tout de suite. */
    public function verifierDepuisLaConsole(array $contexte, ?string $jeton): array
    {
        $this->deposerJeton($jeton);
        try {
            [$code, $sortie, $err] = $this->lancer($this->commandeExecutant($contexte, true), 180);
        } finally {
            Storage::disk('local')->delete(self::DOSSIER . '/jeton');
        }

        $lignes = array_values(array_filter(array_map('trim', explode("\n", $sortie))));
        $reponse = json_decode((string) end($lignes), true);
        if (!is_array($reponse)) {
            return ['ok' => false, 'message' => 'Vérification impossible : ' . (substr(trim($err ?: $sortie), -300) ?: 'aucune réponse.')];
        }

        return $reponse;
    }

    private function deposerJeton(?string $jeton): void
    {
        $disque = Storage::disk('local');
        $disque->delete(self::DOSSIER . '/jeton');
        if ($jeton !== null && trim($jeton) !== '') {
            $disque->put(self::DOSSIER . '/jeton', trim($jeton));
            @chmod($disque->path(self::DOSSIER . '/jeton'), 0600);
        }
    }

    // ── Côté exécutant ───────────────────────────────────────────────────────

    /**
     * L'état du dépôt et ce qui attend, sans rien changer (sauf « git fetch »).
     *
     * @param array<string,string|null> $o
     * @return array<string,mixed>
     */
    public function verifier(array $o, ?string $jeton): array
    {
        $git = $this->git($o['code'], $jeton);

        $branche = trim($git(['rev-parse', '--abbrev-ref', 'HEAD'])[1]);
        [$codeAmont, $amont] = $git(['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}']);
        [, $commit] = $git(['log', '-1', '--format=%h%x09%s%x09%cr']);
        [$h, $m, $d] = array_pad(explode("\t", trim($commit)), 3, '');
        $tete = trim($git(['rev-parse', 'HEAD'])[1]);
        $reponse = [
            'ok' => true,
            'branche' => $branche === 'HEAD' ? null : $branche,
            'amont' => $codeAmont === 0 ? trim($amont) : null,
            'commit' => ['hash' => $h, 'message' => $m, 'date' => $d],
            'modifies' => $this->lignes($git(['status', '--porcelain', '--untracked-files=no'])[1]),
            'version' => $this->lireVersion($o['reglages']),
            'espace_libre' => $this->espaceLibre(),
            'a_reprendre' => $tete !== '' && $this->codeEnAttente($o, $tete),
        ];
        if ($reponse['amont'] === null) {
            return ['ok' => false, 'message' => $reponse['branche'] === null
                ? "Aucune branche n'est active (HEAD détaché). Sur le serveur : git checkout main."
                : "La branche « {$reponse['branche']} » ne suit aucune branche distante. Sur le serveur : git branch --set-upstream-to=origin/{$reponse['branche']}."] + $reponse;
        }

        [$codeFetch, $sortieFetch, $errFetch] = $git(['fetch', '--prune'], 180);
        if ($codeFetch !== 0) {
            return ['ok' => false, 'message' => CodeUpdater::expliquerErreur($errFetch ?: $sortieFetch)] + $reponse;
        }

        [$devant, $derriere] = array_map('intval', preg_split('/\s+/', trim($git(['rev-list', '--left-right', '--count', 'HEAD...@{upstream}'])[1])) + [0, 0]);
        $reponse['devant'] = $devant;
        $reponse['derriere'] = $derriere;
        $reponse['commits'] = $derriere > 0
            ? array_map(fn ($l) => array_pad(explode("\t", $l), 3, ''), $this->lignes($git(['log', '--format=%h%x09%s%x09%cr', '-n', '20', 'HEAD..@{upstream}'])[1]))
            : [];

        // Les réglages que la nouvelle version attendra (étape 3), lus dans son modèle.
        [$codeModele, $modele] = $git(['show', '@{upstream}:.env.production.example']);
        $reponse['reglages_absents'] = $codeModele === 0 && is_file($o['reglages'])
            ? array_keys($this->reglagesAbsents($modele, (string) file_get_contents($o['reglages'])))
            : [];

        return $reponse;
    }

    /**
     * Les huit étapes. $log(niveau, message) : info | ok | warn | erreur, et
     * « etape » pour annoncer l'étape suivante (le message est alors son numéro).
     * $detail reçoit la sortie brute des commandes.
     *
     * @param array<string,string|null> $o
     * @return array{ok:bool, etape:int, rien_a_faire?:bool, ancienne?:?string, nouvelle?:string, migrations?:int, sauvegarde?:?string}
     */
    public function derouler(array $o, callable $log, callable $detail, ?string $jeton = null): array
    {
        $git = $this->git($o['code'], $jeton, $detail);
        $compose = fn (array $args, int $delai, array $env = []) => $this->lancer(
            ['docker', 'compose', '-f', $o['compose'], '--env-file', $o['reglages'], ...$args], $delai, $env, $detail, $o['code']);
        $resultat = ['ok' => false, 'etape' => 0];
        $echec = function (int $etape, string ...$messages) use ($log, &$resultat) {
            foreach ($messages as $m) {
                $log('erreur', $m);
            }

            return ['ok' => false, 'etape' => $etape] + $resultat;
        };

        foreach (['code' => 'dossier du code', 'compose' => 'fichier Compose', 'reglages' => 'fichier de réglages'] as $cle => $libelle) {
            if (!file_exists((string) $o[$cle])) {
                return $echec(0, "Le {$libelle} est introuvable sur le serveur : {$o[$cle]}.");
            }
        }

        // ── Étape 1 ──
        $log('etape', '1');
        $log('info', "Sauvegarde de la console vers {$o['sauvegardes']}…");
        [$code, $sortie, $err] = $this->lancer(['bash', $o['code'] . '/scripts/sauvegarder-erp.sh'], 900, [
            'CONTENEUR' => (string) $o['conteneur'],
            'VOLUME' => (string) $o['volume'],
            'TENANTS_BASE_PATH' => (string) ($o['tenants'] ?: '/var/wetchah/tenants'),
            'DESTINATION' => (string) $o['sauvegardes'],
            // Les fichiers datés à l'heure de l'hôtel, comme la sauvegarde nocturne (cron).
            'TZ' => $this->fuseau($o['reglages']),
        ], $detail);
        if ($code !== 0) {
            return $echec(1, 'La sauvegarde a échoué : la mise à jour ne commence pas sans elle.', $this->derniereLigne($err ?: $sortie));
        }
        $resultat['sauvegarde'] = preg_match('/(console-\d{4}-\d{2}-\d{2}_\d{4}\.sqlite)/', $sortie, $m) ? $m[1] : null;
        $log('ok', 'Sauvegarde faite' . ($resultat['sauvegarde'] ? " : {$resultat['sauvegarde']}" : '') . '.');

        $resultat['ancienne'] = $this->lireVersion($o['reglages']);
        $log('info', $resultat['ancienne'] ? "Version en service : ERP_VERSION={$resultat['ancienne']}." : 'Version en service : pas de numéro (version latest).');

        $libre = $this->espaceLibre();
        if ($libre !== null && $libre < self::ESPACE_MINIMUM) {
            return $echec(1, 'Place libre insuffisante : ' . $this->taille($libre) . ' (il faut plus de 5 Go). Supprimez les anciennes versions (guide, §9.6), puis relancez.');
        }
        if ($libre !== null) {
            $log('ok', 'Place libre : ' . $this->taille($libre) . '.');
        }

        // ── Étape 2 ──
        $log('etape', '2');
        $branche = trim($git(['rev-parse', '--abbrev-ref', 'HEAD'])[1]);
        if ($branche === 'HEAD' || $branche === '') {
            return $echec(2, "Aucune branche n'est active (HEAD détaché). Sur le serveur : git checkout main, puis relancez.");
        }
        [$codeAmont, $amont] = $git(['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}']);
        if ($codeAmont !== 0) {
            return $echec(2, "La branche « {$branche} » ne suit aucune branche distante. Sur le serveur : git branch --set-upstream-to=origin/{$branche}.");
        }
        $log('info', "Sur la branche {$branche} (suit " . trim($amont) . ').');
        if ($branche !== 'main') {
            $log('warn', "Le guide suit la branche main ; cette console suit « {$branche} ».");
        }
        $modifies = $this->lignes($git(['status', '--porcelain', '--untracked-files=no'])[1]);
        if ($modifies) {
            return $echec(2, "Des fichiers du code ont été modifiés sur le serveur ; la mise à jour les écraserait :",
                ...array_map(fn ($l) => '   ' . $l, array_slice($modifies, 0, 8)),
                ...["Annulez-les (git checkout -- FICHIER) ou mettez-les de côté (git stash), puis relancez (guide, §9.4)."]);
        }

        $avant = trim($git(['rev-parse', 'HEAD'])[1]);
        $log('info', 'Téléchargement du nouveau code (git pull --ff-only)…');
        [$code, $sortie, $err] = $git(['pull', '--ff-only'], 600);
        if ($code !== 0) {
            return $echec(2, CodeUpdater::expliquerErreur($err ?: $sortie));
        }
        $apres = trim($git(['rev-parse', 'HEAD'])[1]);

        if ($avant === $apres) {
            if (!$this->codeEnAttente($o, $apres)) {
                $log('ok', "Déjà à jour : rien de nouveau, la mise à jour s'arrête ici.");
                Storage::disk('local')->delete(self::DOSSIER . '/en_attente.json');

                return ['ok' => true, 'etape' => 2, 'rien_a_faire' => true] + $resultat;
            }
            $log('info', "Code déjà téléchargé ({$this->court($apres)}) mais pas encore en service : la mise à jour reprend.");
        } else {
            $fichiers = $this->lignes($git(['diff', '--name-only', $avant, $apres])[1]);
            $log('ok', "Code mis à jour : {$this->court($avant)} → {$this->court($apres)} (" . count($fichiers) . ' fichier(s)).');
        }
        // Une reprise saura que ce code n'est pas encore en service.
        $this->noter('en_attente.json', json_encode(['commit' => $apres]));

        // ── Étape 3 ──
        $log('etape', '3');
        $modele = $o['code'] . '/.env.production.example';
        if (is_file($modele)) {
            $absents = $this->reglagesAbsents((string) file_get_contents($modele), (string) file_get_contents($o['reglages']));
            if ($absents) {
                $messages = ['La nouvelle version attend des réglages absents de ' . $o['reglages'] . ' :'];
                foreach ($absents as $nom => $aide) {
                    $messages[] = "   Réglage absent : {$nom}" . ($aide ? " — {$aide}" : '');
                }
                $messages[] = "Ajoutez chaque ligne au fichier (nano {$o['reglages']}, annexe B du guide), puis relancez : la mise à jour reprendra ici. En cas de doute, demandez la valeur à l'équipe qui a livré la version.";

                return $echec(3, ...$messages);
            }
            $log('ok', 'Tous les réglages sont présents.');
        } else {
            $log('warn', 'Modèle .env.production.example absent : contrôle des réglages sauté.');
        }

        // ── Étape 4 ──
        $log('etape', '4');
        $nouvelle = Carbon::now($this->fuseau($o['reglages']))->format('Y-m-d-Hi');
        if ($nouvelle === $resultat['ancienne']) {
            $nouvelle = Carbon::now($this->fuseau($o['reglages']))->format('Y-m-d-His');
        }
        $this->ecrireVersion($o['reglages'], $nouvelle);
        if ($this->lireVersion($o['reglages']) !== $nouvelle) {
            return $echec(4, "Le numéro de version n'a pas pu être écrit dans {$o['reglages']}.");
        }
        $resultat['nouvelle'] = $nouvelle;
        $log('ok', "ERP_VERSION={$nouvelle}. La console tourne toujours sur l'ancienne version.");

        // ── Étape 5 ──
        $log('etape', '5');
        $log('info', 'Fabrication de l\'image (quelques minutes ; jusqu\'à une demi-heure si les bibliothèques ont changé). La console continue de fonctionner.');
        [$code, $sortie, $err] = $compose(['build'], 3600, ['ERP_COMMIT' => $apres]);
        $image = $o['depot_image'] . ':' . $nouvelle;
        if ($code === 0 && $this->lancer(['docker', 'image', 'inspect', $image], 30)[0] !== 0) {
            $code = 1;
            $err = "L'image {$image} est introuvable après la fabrication.";
        }
        if ($code !== 0) {
            // La console n'est pas touchée : on remet l'ancien numéro (guide, §9.5, étapes 1 et 2).
            $this->ecrireVersion($o['reglages'], $resultat['ancienne']);

            return $echec(5, 'La fabrication a échoué ; la console tourne toujours sur l\'ancienne version, dont le numéro est remis.',
                $this->expliquerFabrication($err . "\n" . $sortie), $this->derniereLigne($err ?: $sortie));
        }
        $log('ok', "Image {$image} fabriquée.");

        // ── Étape 6 ──
        $log('etape', '6');
        $log('info', 'Démarrage de la nouvelle version : la console est indisponible une à deux minutes. Les hôtels ne sont pas touchés.');
        [$code, $sortie, $err] = $compose(['up', '-d'], 900);
        if ($code !== 0) {
            return $echec(6, 'Le démarrage de la nouvelle version a échoué : ' . $this->derniereLigne($err ?: $sortie),
                "Lisez « docker logs --tail 40 {$o['conteneur']} » ; pour revenir en arrière : guide, §9.5.");
        }
        $log('ok', 'Nouvelle version démarrée.');

        // ── Étape 7 ──
        $log('etape', '7');
        $this->attendreQueLaConsoleTourne((string) $o['conteneur']);
        [$code, $sortie, $err] = $compose(['exec', '-T', '-u', 'www-data', (string) $o['service'], 'php', 'artisan', 'migrate', '--force'], 900);
        if ($code !== 0) {
            return $echec(7, 'La migration de la base s\'est arrêtée sur une erreur : ' . $this->derniereLigne($err ?: $sortie),
                'Ne la relancez pas en boucle : revenez à l\'ancienne version en restaurant la base' . (isset($resultat['sauvegarde']) && $resultat['sauvegarde'] ? " ({$resultat['sauvegarde']})" : '') . ' (guide, §9.5) et prévenez l\'équipe de développement.');
        }
        $resultat['migrations'] = substr_count($sortie, 'DONE');
        $log('ok', $resultat['migrations'] > 0
            ? "{$resultat['migrations']} migration(s) appliquée(s). En cas de retour en arrière, il faudra aussi restaurer la base (§9.5)."
            : 'Rien à migrer.');

        // ── Étape 8 ──
        $log('etape', '8');
        $log('info', "Attente de l'état « healthy » de la console…");
        [$etat, $imageEnService] = $this->attendreLaSante((string) $o['conteneur']);
        if ($imageEnService !== $image) {
            return $echec(8, "La console tourne sur {$imageEnService}, pas sur {$image} : l'étape 4 ou 6 n'a pas pris (guide, §9.4).");
        }
        if ($etat !== 'healthy') {
            $journal = $this->lancer(['docker', 'logs', '--tail', '40', (string) $o['conteneur']], 30);
            $detail("── docker logs --tail 40 {$o['conteneur']} ──\n" . $journal[1] . $journal[2]);

            return $echec(8, $etat === 'unhealthy'
                ? 'La console a démarré mais ne répond pas (unhealthy). Ses dernières lignes de journal sont dans le détail ; si rien n\'y fait, revenez à l\'ancienne version (§9.5).'
                : "La console n'est pas devenue « healthy » en " . intdiv(self::ATTENTE_SANTE, 60) . ' minutes (état : ' . ($etat ?: 'inconnu') . '). Voir le détail ; si rien n\'y fait, revenez à l\'ancienne version (§9.5).');
        }
        Storage::disk('local')->delete(self::DOSSIER . '/en_attente.json');
        $log('ok', "La console tourne sur {$image} (healthy).");
        $log('ok', 'Mise à jour terminée. Vérifiez que la Supervision liste bien vos hôtels. Gardez l\'ancienne version quelques jours, puis faites de la place (§9.6).');

        return ['ok' => true, 'etape' => 8] + $resultat;
    }

    // ── Fichier de réglages ──────────────────────────────────────────────────

    public function lireVersion(string $fichier): ?string
    {
        $contenu = is_file($fichier) ? (string) file_get_contents($fichier) : '';

        return preg_match('/^ERP_VERSION=(.*)$/m', $contenu, $m) && trim($m[1]) !== '' ? trim($m[1]) : null;
    }

    /**
     * Efface la ligne ERP_VERSION et écrit la nouvelle (ou aucune : version latest),
     * sans toucher au reste du fichier ni à son propriétaire et ses droits.
     */
    public function ecrireVersion(string $fichier, ?string $version): void
    {
        $lignes = preg_split('/\r?\n/', (string) file_get_contents($fichier));
        $lignes = array_values(array_filter($lignes, fn ($l) => !str_starts_with($l, 'ERP_VERSION=')));
        while ($lignes && end($lignes) === '') {
            array_pop($lignes);
        }
        if ($version !== null) {
            $lignes[] = 'ERP_VERSION=' . $version;
        }

        $proprietaire = fileowner($fichier);
        $groupe = filegroup($fichier);
        $droits = fileperms($fichier) & 0777;
        $temporaire = $fichier . '.maj-' . getmypid();
        file_put_contents($temporaire, implode("\n", $lignes) . "\n");
        @chmod($temporaire, $droits);
        @chown($temporaire, $proprietaire);
        @chgrp($temporaire, $groupe);
        rename($temporaire, $fichier);
    }

    /**
     * Les réglages du modèle absents du fichier, avec le commentaire qui les
     * précède dans le modèle (ce que dit « grep -B3 NOM »).
     *
     * @return array<string,string>
     */
    public function reglagesAbsents(string $modele, string $reglages): array
    {
        preg_match_all('/^([A-Z_][A-Z0-9_]*)=/m', $reglages, $presents);
        $presents = array_flip($presents[1]);

        $absents = [];
        $lignes = preg_split('/\r?\n/', $modele);
        foreach ($lignes as $i => $ligne) {
            if (!preg_match('/^([A-Z_][A-Z0-9_]*)=/', $ligne, $m) || isset($presents[$m[1]]) || isset($absents[$m[1]])) {
                continue;
            }
            $aide = [];
            for ($j = $i - 1; $j >= max(0, $i - 3) && str_starts_with(trim($lignes[$j]), '#'); $j--) {
                array_unshift($aide, trim(ltrim(trim($lignes[$j]), '#')));
            }
            $absents[$m[1]] = trim(implode(' ', array_filter($aide)));
        }

        return $absents;
    }

    private function fuseau(string $reglages): string
    {
        $contenu = is_file($reglages) ? (string) file_get_contents($reglages) : '';
        $fuseau = preg_match('/^TZ=(.+)$/m', $contenu, $m) ? trim($m[1], " \t\"'") : '';

        return in_array($fuseau, timezone_identifiers_list(), true) ? $fuseau : 'Africa/Douala';
    }

    // ── Utilitaires ──────────────────────────────────────────────────────────

    /** Un fichier du volume storage, rendu à www-data qui sert la page (l'exécutant tourne en root). */
    private function noter(string $fichier, string $contenu): void
    {
        $disque = Storage::disk('local');
        $disque->put(self::DOSSIER . '/' . $fichier, $contenu);
        if ($this->uidCourant() === 0) {
            @chown($disque->path(self::DOSSIER . '/' . $fichier), 33);
            @chgrp($disque->path(self::DOSSIER . '/' . $fichier), 33);
        }
    }

    /** Le code téléchargé n'est pas celui de l'image en service : une mise à jour précédente s'est arrêtée en route. */
    private function codeEnAttente(array $o, string $tete): bool
    {
        if (!empty($o['commit_image'])) {
            return !str_starts_with($tete, (string) $o['commit_image']);
        }
        $attente = json_decode((string) Storage::disk('local')->get(self::DOSSIER . '/en_attente.json'), true);

        return ($attente['commit'] ?? null) === $tete;
    }

    /**
     * Git sous le propriétaire du dépôt. Un jeton éventuel passe par une variable
     * d'environnement lue par l'assistant d'identification : il n'apparaît ni dans
     * la commande, ni dans l'adresse du dépôt, ni dans le journal.
     *
     * @return Closure(list<string>, int=):array{0:int,1:string,2:string}
     */
    private function git(string $dossier, ?string $jeton, ?callable $detail = null): Closure
    {
        $uid = @fileowner($dossier . '/.git');
        $gid = @filegroup($dossier . '/.git');

        return function (array $args, int $delai = 60) use ($dossier, $jeton, $detail, $uid, $gid) {
            $config = ['-c', 'safe.directory=' . $dossier];
            $env = ['HOME' => '/tmp', 'GIT_TERMINAL_PROMPT' => '0',
                'GIT_AUTHOR_NAME' => 'Console WeTchah', 'GIT_AUTHOR_EMAIL' => 'console@wetchah.local',
                'GIT_COMMITTER_NAME' => 'Console WeTchah', 'GIT_COMMITTER_EMAIL' => 'console@wetchah.local'];
            if ($jeton) {
                $config = [...$config, '-c', 'credential.helper=', '-c', 'credential.helper=!f() { echo username=x-access-token; echo "password=$GIT_JETON"; }; f'];
                $env['GIT_JETON'] = $jeton;
            }
            $cmd = ['git', ...$config, '-C', $dossier, ...$args];
            if ($uid && $uid !== $this->uidCourant()) {
                $cmd = ['setpriv', "--reuid={$uid}", "--regid={$gid}", '--clear-groups', ...$cmd];
            }

            return $this->lancer($cmd, $delai, $env, in_array($args[0] ?? '', ['pull', 'fetch'], true) ? $detail : null);
        };
    }

    private function attendreQueLaConsoleTourne(string $conteneur): void
    {
        for ($i = 0; $i < 30; $i++) {
            if (trim($this->lancer(['docker', 'inspect', '-f', '{{.State.Running}}', $conteneur], 20)[1]) === 'true') {
                return;
            }
            $this->dormir(2);
        }
    }

    /** @return array{0:string,1:string} état de santé, image en service */
    private function attendreLaSante(string $conteneur): array
    {
        $etat = '';
        $image = '';
        for ($ecoule = 0; $ecoule <= self::ATTENTE_SANTE; $ecoule += 5) {
            [$code, $sortie] = $this->lancer(['docker', 'inspect', '-f', '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{end}}|{{.Config.Image}}', $conteneur], 20);
            if ($code === 0) {
                [$statut, $sante, $image] = array_pad(explode('|', trim($sortie)), 3, '');
                $etat = $sante !== '' ? $sante : $statut;
                if ($etat === 'healthy' || $etat === 'unhealthy' || ($sante === '' && $statut === 'running' && $ecoule >= 30) || in_array($statut, ['exited', 'dead'], true)) {
                    return [$sante === '' && $statut === 'running' ? 'healthy' : $etat, $image];
                }
            }
            $this->dormir(5);
        }

        return [$etat, $image];
    }

    private function expliquerFabrication(string $sortie): string
    {
        return match (true) {
            str_contains($sortie, 'no space left on device') =>
                'Le disque est plein : supprimez les anciennes versions (guide, §9.6), puis relancez.',
            str_contains($sortie, 'curl error 28'), str_contains($sortie, 'Operation timed out'),
            str_contains($sortie, 'ETIMEDOUT'), str_contains($sortie, 'ECONNRESET'), str_contains($sortie, 'network') =>
                'Téléchargement trop lent depuis GitHub ou npm : relancez la mise à jour, les fichiers déjà reçus sont gardés.',
            default => 'Problème dans le code reçu : transmettez les dernières lignes du détail à l\'équipe de développement.',
        };
    }

    private function espaceLibre(): ?int
    {
        // Dans l'exécutant, « / » est sur le disque où Docker range ses images.
        [$code, $sortie] = $this->lancer(['df', '-Pk', '/'], 20);
        $lignes = $this->lignes($sortie);
        if ($code !== 0 || count($lignes) < 2) {
            return null;
        }
        $colonnes = preg_split('/\s+/', end($lignes));

        return isset($colonnes[3]) && ctype_digit($colonnes[3]) ? (int) $colonnes[3] * 1024 : null;
    }

    private function taille(int $octets): string
    {
        return number_format($octets / 1024 ** 3, 1, ',', ' ') . ' Go';
    }

    private function court(string $commit): string
    {
        return substr($commit, 0, 7);
    }

    /** @return list<string> */
    private function lignes(string $texte): array
    {
        return array_values(array_filter(array_map('rtrim', explode("\n", $texte)), fn ($l) => trim($l) !== ''));
    }

    private function derniereLigne(string $texte): string
    {
        $lignes = $this->lignes($texte);

        return substr((string) end($lignes), 0, 300) ?: 'aucun message.';
    }

    private function dormir(int $secondes): void
    {
        $this->pause ? ($this->pause)($secondes) : sleep($secondes);
    }

    private function conteneur(): string
    {
        return (string) config('provisioning.cms_container', 'wetchah_erp-app');
    }

    private function uidCourant(): int
    {
        return function_exists('posix_geteuid') ? posix_geteuid() : 0;
    }

    /**
     * @param list<string> $cmd
     * @param array<string,string> $env
     * @return array{0:int,1:string,2:string} code, sortie, erreurs
     */
    private function lancer(array $cmd, int $delai, array $env = [], ?callable $flux = null, ?string $dossier = null): array
    {
        if ($this->executeur) {
            return ($this->executeur)($cmd, $delai, $env, $flux, $dossier);
        }

        $p = new Process($cmd, $dossier, $env, null, $delai);
        try {
            $p->run($flux ? function ($type, $morceau) use ($flux) { $flux($morceau); } : null);
        } catch (\Throwable $e) {
            return [1, $p->getOutput(), $p->getErrorOutput() . "\n" . $e->getMessage()];
        }

        return [$p->getExitCode() ?? 1, $p->getOutput(), $p->getErrorOutput()];
    }
}
