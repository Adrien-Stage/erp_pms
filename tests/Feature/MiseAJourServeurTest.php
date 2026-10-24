<?php

/**
 * Mise à jour d'une console de production depuis l'onglet Système : les huit
 * étapes du guide (§9.1), déroulées par un conteneur à part. Docker, git et la
 * sauvegarde sont simulés par un exécuteur qui répond commande par commande ;
 * le dossier du code et le fichier de réglages sont de vrais fichiers.
 */

use App\Models\User;
use App\Services\MiseAJourServeur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Carbon::setTestNow(Carbon::parse('2026-10-22 13:35:00', 'UTC'));   // 14:35 à Douala

    $this->racine = sys_get_temp_dir() . '/maj-serveur-' . random_int(1, 999999);
    $this->code = $this->racine . '/wetchah_erp';
    mkdir($this->code . '/.git', 0777, true);
    mkdir($this->code . '/scripts');
    file_put_contents($this->code . '/docker-compose.prod.yml', "name: wetchah-erp\n");
    file_put_contents($this->code . '/scripts/sauvegarder-erp.sh', "#!/bin/bash\n");
    file_put_contents($this->code . '/.env.production.example', "# Clé de la console\nAPP_KEY=\nTZ=Africa/Douala\n");
    $this->reglages = $this->racine . '/erp.env';
    file_put_contents($this->reglages, "APP_KEY=base64:abc\nTZ=Africa/Douala\nERP_VERSION=2026-10-01-0930\n");
    chmod($this->reglages, 0600);

    $this->opts = [
        'conteneur' => 'wetchah_erp-app', 'service' => 'app', 'code' => $this->code,
        'compose' => $this->code . '/docker-compose.prod.yml', 'reglages' => $this->reglages,
        'volume' => 'wetchah-erp_erp_storage', 'tenants' => '/var/wetchah/tenants', 'sauvegardes' => '/srv/sauvegardes',
        'depot_image' => 'wetchah-erp', 'commit_image' => null,
    ];
});

afterEach(function () {
    Carbon::setTestNow();
    exec('rm -rf ' . escapeshellarg($this->racine));
});

/**
 * Exécuteur factice : la première réponse dont le motif figure dans la commande.
 * Les commandes git sont réduites à « git <sous-commande> » (sans setpriv ni -c).
 */
function serveurFactice(array $reponses, ?array &$vues = null): MiseAJourServeur
{
    $vues = [];
    $executeur = function (array $cmd, int $delai, array $env = [], ?callable $flux = null) use ($reponses, &$vues) {
        $texte = implode(' ', $cmd);
        if (in_array('git', $cmd, true) && ($i = array_search('-C', $cmd, true)) !== false) {
            $texte = 'git ' . implode(' ', array_slice($cmd, $i + 2));
        }
        $vues[] = ['cmd' => $texte, 'env' => $env, 'brut' => $cmd];
        foreach ($reponses as $motif => $reponse) {
            if (str_contains($texte, $motif)) {
                return is_callable($reponse) ? $reponse($cmd, $env) : $reponse;
            }
        }

        return [0, '', ''];
    };

    return new MiseAJourServeur($executeur, fn () => null);
}

/** Un serveur où tout se passe bien : un nouveau commit, une image saine. */
function serveurNominal(object $test, array $autres = []): array
{
    $tetes = ['aaaaaaa1111111', 'bbbbbbb2222222'];
    $reglages = $test->reglages;

    return $autres + [
        'sauvegarder-erp.sh' => [0, "[2026-10-22 14:30:00] Sauvegarde de wetchah_erp-app vers /srv/sauvegardes\n  base de la console : console-2026-10-22_1430.sqlite\n[2026-10-22 14:30:04] Terminé.\n", ''],
        'df -Pk' => [0, "Filesystem 1024-blocks Used Available Capacity Mounted on\noverlay 102400000 40000000 52000000 45% /\n", ''],
        'git rev-parse --abbrev-ref HEAD' => [0, "main\n", ''],
        'git rev-parse --abbrev-ref --symbolic-full-name' => [0, "origin/main\n", ''],
        'git status --porcelain' => [0, '', ''],
        'git rev-parse HEAD' => function () use (&$tetes) { return [0, (count($tetes) > 1 ? array_shift($tetes) : $tetes[0]) . "\n", '']; },
        'git pull' => [0, "Mise à jour aaaaaaa..bbbbbbb\nFast-forward\n", ''],
        'git diff --name-only' => [0, "app/X.php\nresources/views/y.blade.php\n", ''],
        'docker image inspect' => [0, '[{}]', ''],
        '{{.State.Running}}' => [0, "true\n", ''],
        'artisan migrate' => [0, "INFO  Running migrations.\n 2026_10_22_000000_a ..... DONE\n 2026_10_22_000001_b ..... DONE\n", ''],
        '{{.State.Status}}' => fn () => [0, 'running|healthy|wetchah-erp:' . (new MiseAJourServeur())->lireVersion($reglages) . "\n", ''],
    ];
}

function deroulerMaj(MiseAJourServeur $m, array $o, ?string $jeton = null): array
{
    $lignes = [];
    $resultat = $m->derouler($o, function ($n, $msg) use (&$lignes) { $lignes[] = "$n: $msg"; }, fn () => null, $jeton);

    return [$resultat, implode("\n", $lignes)];
}

test('les huit étapes, dans l\'ordre du guide : sauvegarde, code, réglages, numéro, image, démarrage, base, santé', function () {
    $m = serveurFactice(serveurNominal($this), $vues);

    [$r, $texte] = deroulerMaj($m, $this->opts);

    expect($r['ok'])->toBeTrue()
        ->and($r['etape'])->toBe(8)
        ->and($r['ancienne'])->toBe('2026-10-01-0930')
        ->and($r['nouvelle'])->toBe('2026-10-22-1435')
        ->and($r['migrations'])->toBe(2)
        ->and($r['sauvegarde'])->toBe('console-2026-10-22_1430.sqlite');

    foreach (range(1, 8) as $n) {
        expect($texte)->toContain("etape: {$n}");
    }

    // Une seule ligne ERP_VERSION, le reste du fichier intact, ses droits gardés.
    expect(file_get_contents($this->reglages))->toBe("APP_KEY=base64:abc\nTZ=Africa/Douala\nERP_VERSION=2026-10-22-1435\n")
        ->and(fileperms($this->reglages) & 0777)->toBe(0600);

    $commandes = array_column($vues, 'cmd');
    $position = fn (string $motif) => collect($commandes)->search(fn ($c) => str_contains($c, $motif));
    expect($position('sauvegarder-erp.sh'))->toBeLessThan($position('git pull'))
        ->and($position('git pull'))->toBeLessThan($position(' build'))
        ->and($position(' build'))->toBeLessThan($position('up -d'))
        ->and($position('up -d'))->toBeLessThan($position('artisan migrate'))
        ->and($position('artisan migrate'))->toBeLessThan($position('{{.State.Status}}'));

    // L'image porte le commit dont elle est faite ; compose lit le fichier de réglages.
    $build = collect($vues)->first(fn ($v) => str_ends_with($v['cmd'], ' build'));
    expect($build['env']['ERP_COMMIT'])->toBe('bbbbbbb2222222')
        ->and($build['cmd'])->toContain('--env-file ' . $this->reglages);

    // La sauvegarde vise la console, son volume et le dossier des hôtels.
    $sauvegarde = collect($vues)->first(fn ($v) => str_contains($v['cmd'], 'sauvegarder-erp.sh'));
    expect($sauvegarde['env'])->toMatchArray(['CONTENEUR' => 'wetchah_erp-app', 'VOLUME' => 'wetchah-erp_erp_storage', 'DESTINATION' => '/srv/sauvegardes', 'TZ' => 'Africa/Douala']);
});

test('déjà à jour : la mise à jour s\'arrête à l\'étape 2, sans rien fabriquer', function () {
    $m = serveurFactice(serveurNominal($this, ['git rev-parse HEAD' => [0, "aaaaaaa1111111\n", '']]), $vues);

    [$r, $texte] = deroulerMaj($m, ['commit_image' => 'aaaaaaa1111111'] + $this->opts);

    expect($r['ok'])->toBeTrue()->and($r['rien_a_faire'])->toBeTrue()->and($r['etape'])->toBe(2)
        ->and($texte)->toContain('Déjà à jour')
        ->and(implode("\n", array_column($vues, 'cmd')))->not->toContain(' build')
        ->and(file_get_contents($this->reglages))->toContain('ERP_VERSION=2026-10-01-0930');
});

test('un code téléchargé mais pas encore en service : la mise à jour reprend après l\'étape 2', function () {
    $m = serveurFactice(serveurNominal($this, ['git rev-parse HEAD' => [0, "bbbbbbb2222222\n", '']]), $vues);

    [$r, $texte] = deroulerMaj($m, ['commit_image' => 'aaaaaaa1111111'] + $this->opts);

    expect($r['ok'])->toBeTrue()->and($r['etape'])->toBe(8)
        ->and($texte)->toContain('pas encore en service')
        ->and(implode("\n", array_column($vues, 'cmd')))->toContain(' build');
});

test('sans étiquette sur l\'image, la reprise se fie au code noté lors du téléchargement', function () {
    Storage::disk('local')->put(MiseAJourServeur::DOSSIER . '/en_attente.json', json_encode(['commit' => 'bbbbbbb2222222']));
    $m = serveurFactice(serveurNominal($this, ['git rev-parse HEAD' => [0, "bbbbbbb2222222\n", '']]));

    [$r] = deroulerMaj($m, $this->opts);

    expect($r['ok'])->toBeTrue()->and($r['etape'])->toBe(8)
        ->and(Storage::disk('local')->exists(MiseAJourServeur::DOSSIER . '/en_attente.json'))->toBeFalse();
});

test('un réglage absent arrête à l\'étape 3, avant tout numéro et toute fabrication', function () {
    file_put_contents($this->code . '/.env.production.example', "APP_KEY=\n# Adresse du nouveau service\n# de messagerie\nNOUVEAU_REGLAGE=valeur\n");
    $m = serveurFactice(serveurNominal($this), $vues);

    [$r, $texte] = deroulerMaj($m, $this->opts);

    expect($r['ok'])->toBeFalse()->and($r['etape'])->toBe(3)
        ->and($texte)->toContain('Réglage absent : NOUVEAU_REGLAGE — Adresse du nouveau service de messagerie')
        ->and(file_get_contents($this->reglages))->toContain('ERP_VERSION=2026-10-01-0930')
        ->and(implode("\n", array_column($vues, 'cmd')))->not->toContain(' build');
});

test('une fabrication ratée remet l\'ancien numéro et ne touche pas à la console', function () {
    $m = serveurFactice(serveurNominal($this, [' build' => [1, '', "curl error 28 while downloading https://repo.packagist.org\n"]]), $vues);

    [$r, $texte] = deroulerMaj($m, $this->opts);

    expect($r['ok'])->toBeFalse()->and($r['etape'])->toBe(5)
        ->and($texte)->toContain('Téléchargement trop lent')
        ->and(file_get_contents($this->reglages))->toContain('ERP_VERSION=2026-10-01-0930')
        ->and(substr_count(file_get_contents($this->reglages), 'ERP_VERSION='))->toBe(1)
        ->and(implode("\n", array_column($vues, 'cmd')))->not->toContain('up -d');
});

test('sans numéro au départ, une fabrication ratée revient à « latest »', function () {
    file_put_contents($this->reglages, "APP_KEY=base64:abc\nTZ=Africa/Douala\n");
    $m = serveurFactice(serveurNominal($this, [' build' => [1, '', "no space left on device\n"]]));

    [$r, $texte] = deroulerMaj($m, $this->opts);

    expect($r['etape'])->toBe(5)->and($texte)->toContain('disque est plein')
        ->and(file_get_contents($this->reglages))->not->toContain('ERP_VERSION');
});

test('des fichiers modifiés sur le serveur arrêtent l\'étape 2 avant le pull', function () {
    $m = serveurFactice(serveurNominal($this, ['git status --porcelain' => [0, " M app/Models/User.php\n", '']]), $vues);

    [$r, $texte] = deroulerMaj($m, $this->opts);

    expect($r['etape'])->toBe(2)->and($texte)->toContain('app/Models/User.php')->and($texte)->toContain('git stash')
        ->and(implode("\n", array_column($vues, 'cmd')))->not->toContain('git pull');
});

test('un correctif appliqué sur le serveur est replacé par-dessus la nouvelle version (git pull --rebase)', function () {
    $avance = ['1', '1'];
    $m = serveurFactice(serveurNominal($this, [
        'git rev-list --count' => function () use (&$avance) { return [0, array_shift($avance) . "\n", '']; },
        'git pull' => [0, "Rebasage et mise à jour de refs/heads/main avec succès.\n", ''],
    ]), $vues);

    [$r, $texte] = deroulerMaj($m, $this->opts);

    $commandes = array_column($vues, 'cmd');
    expect($r['ok'])->toBeTrue()->and($r['etape'])->toBe(8)
        ->and($commandes)->toContain('git pull --rebase')->not->toContain('git pull --ff-only')
        ->and($texte)->toContain('en avance de 1 commit')
        ->and($texte)->toContain('1 correctif(s) local(aux) replacé(s)');
});

test("un correctif publié entre-temps par l'équipe est retiré par le rebasage", function () {
    $avance = ['1', '0'];
    $m = serveurFactice(serveurNominal($this, [
        'git rev-list --count' => function () use (&$avance) { return [0, array_shift($avance) . "\n", '']; },
    ]));

    [$r, $texte] = deroulerMaj($m, $this->opts);

    expect($r['ok'])->toBeTrue()->and($texte)->toContain('publié par l\'équipe entre-temps, il est retiré');
});

test('un conflit entre le correctif local et la nouvelle version annule le rebasage et arrête l\'étape 2', function () {
    $code = $this->code;
    $m = serveurFactice(serveurNominal($this, [
        'git rev-list --count' => [0, "1\n", ''],
        // git s'arrête au milieu du rebasage, comme sur un vrai conflit.
        'git pull' => function () use ($code) {
            mkdir($code . '/.git/rebase-merge');

            return [1, '', "CONFLIT (contenu) : Conflit de fusion dans app/Services/X.php\n"];
        },
        'git rebase --abort' => function () use ($code) {
            rmdir($code . '/.git/rebase-merge');

            return [0, '', ''];
        },
    ]), $vues);

    [$r, $texte] = deroulerMaj($m, $this->opts);

    $commandes = array_column($vues, 'cmd');
    expect($r['ok'])->toBeFalse()->and($r['etape'])->toBe(2)
        ->and($commandes)->toContain('git rebase --abort')
        ->and(implode("\n", $commandes))->not->toContain(' build')
        ->and($texte)->toContain('CONFLIT')->and($texte)->toContain("état d'avant")->and($texte)->toContain('§9.4')
        ->and(is_dir($this->code . '/.git/rebase-merge'))->toBeFalse()
        ->and(file_get_contents($this->reglages))->toContain('ERP_VERSION=2026-10-01-0930');
});

test('moins de 5 Go libres : arrêt à l\'étape 1', function () {
    $m = serveurFactice(serveurNominal($this, ['df -Pk' => [0, "Filesystem 1024-blocks Used Available Capacity Mounted on\noverlay 100 90 2000000 99% /\n", '']]), $vues);

    [$r, $texte] = deroulerMaj($m, $this->opts);

    expect($r['etape'])->toBe(1)->and($texte)->toContain('Place libre insuffisante')
        ->and(implode("\n", array_column($vues, 'cmd')))->not->toContain('git pull');
});

test('une sauvegarde ratée empêche la mise à jour de commencer', function () {
    $m = serveurFactice(serveurNominal($this, ['sauvegarder-erp.sh' => [1, '', "docker: Error response from daemon\n"]]), $vues);

    [$r] = deroulerMaj($m, $this->opts);

    expect($r['etape'])->toBe(1)->and(implode("\n", array_column($vues, 'cmd')))->not->toContain('git');
});

test('une migration en échec renvoie au retour en arrière avec la sauvegarde', function () {
    $m = serveurFactice(serveurNominal($this, ['artisan migrate' => [1, '', "SQLSTATE[HY000]: General error\n"]]));

    [$r, $texte] = deroulerMaj($m, $this->opts);

    expect($r['etape'])->toBe(7)->and($texte)->toContain('console-2026-10-22_1430.sqlite')->and($texte)->toContain('§9.5');
});

test('une console « unhealthy » arrête l\'étape 8 et garde son journal', function () {
    $m = serveurFactice(serveurNominal($this, [
        '{{.State.Status}}' => fn () => [0, 'running|unhealthy|wetchah-erp:2026-10-22-1435', ''],
        'docker logs' => [0, "PHP Fatal error: X\n", ''],
    ]), $vues);

    [$r, $texte] = deroulerMaj($m, $this->opts);

    expect($r['ok'])->toBeFalse()->and($r['etape'])->toBe(8)->and($texte)->toContain('unhealthy')
        ->and(implode("\n", array_column($vues, 'cmd')))->toContain('docker logs --tail 40');
});

test('le jeton GitHub passe par une variable, jamais dans la commande', function () {
    $m = serveurFactice(serveurNominal($this), $vues);

    deroulerMaj($m, $this->opts, 'ghp_jetonSecret1234567890');

    $pull = collect($vues)->first(fn ($v) => $v['cmd'] === 'git pull --rebase');
    expect($pull['env']['GIT_JETON'])->toBe('ghp_jetonSecret1234567890')
        ->and(implode(' ', $pull['brut']))->not->toContain('ghp_jetonSecret')
        ->and(implode(' ', $pull['brut']))->toContain('credential.helper');
});

test('la console se lit sur son conteneur ; installée par Portainer, elle renvoie au guide', function () {
    $inspect = fn (string $dossier, array $etiquettes = []) => [0, json_encode([[
        'Config' => [
            'Image' => 'wetchah-erp:2026-10-01-0930',
            'Labels' => $etiquettes + [
                'com.docker.compose.project.working_dir' => $dossier,
                'com.docker.compose.project.config_files' => $dossier . '/docker-compose.prod.yml',
                'com.docker.compose.service' => 'app',
            ],
            'Env' => ['TENANTS_BASE_PATH=/var/wetchah/tenants', 'APP_KEY=secret'],
        ],
        'Mounts' => [['Type' => 'volume', 'Name' => 'wetchah-erp_erp_storage', 'Destination' => '/var/www/html/storage']],
        'State' => ['StartedAt' => '2026-10-22T10:00:00Z'],
    ]]), ''];

    $ctx = serveurFactice(['docker inspect' => $inspect('/opt/wetchah/wetchah_erp', [
        'com.docker.compose.project.environment_file' => '/opt/wetchah/erp.env',
        MiseAJourServeur::ETIQUETTE_COMMIT => 'abc1234def',
    ])])->contexte();

    expect($ctx)->toMatchArray([
        'disponible' => true, 'version' => '2026-10-01-0930', 'depot_image' => 'wetchah-erp',
        'code' => '/opt/wetchah/wetchah_erp', 'compose' => '/opt/wetchah/wetchah_erp/docker-compose.prod.yml',
        'reglages' => '/opt/wetchah/erp.env', 'volume' => 'wetchah-erp_erp_storage', 'tenants' => '/var/wetchah/tenants',
        'commit_image' => 'abc1234def',
    ]);

    // Sans étiquette de fichier de réglages : celui du guide, à côté du dossier du code.
    expect(serveurFactice(['docker inspect' => $inspect('/opt/wetchah/wetchah_erp')])->contexte()['reglages'])->toBe('/opt/wetchah/erp.env');

    $portainer = serveurFactice(['docker inspect' => $inspect('/data/compose/12')])->contexte();
    expect($portainer['disponible'])->toBeFalse()->and($portainer['raison'])->toContain('Portainer');
});

test('l\'exécutant monte les chemins de l\'hôte à l\'identique et survit à la console', function () {
    $ctx = [
        'conteneur' => 'wetchah_erp-app', 'service' => 'app', 'image' => 'wetchah-erp:2026-10-01-0930', 'depot_image' => 'wetchah-erp',
        'commit_image' => null, 'code' => '/opt/wetchah/wetchah_erp', 'compose' => '/opt/wetchah/wetchah_erp/docker-compose.prod.yml',
        'reglages' => '/opt/wetchah/erp.env', 'volume' => 'wetchah-erp_erp_storage', 'tenants' => '/var/wetchah/tenants', 'sauvegardes' => '/srv/sauvegardes',
    ];
    $m = new MiseAJourServeur(fn () => [0, '', '']);

    $cmd = implode(' ', $m->commandeExecutant($ctx, false));
    expect($cmd)->toContain('-d --name wetchah_erp-maj')
        ->toContain('-v /var/run/docker.sock:/var/run/docker.sock')
        ->toContain('-v /opt/wetchah:/opt/wetchah')
        ->not->toContain('-v /opt/wetchah/wetchah_erp:')   // déjà dans /opt/wetchah
        ->toContain('-v wetchah-erp_erp_storage:/var/www/html/storage')
        ->toContain('-v /srv/sauvegardes:/srv/sauvegardes')
        ->toContain('-v /var/wetchah/tenants:/var/wetchah/tenants:ro')
        ->toContain('wetchah-erp:2026-10-01-0930 artisan erp:mettre-a-jour-serveur')
        ->toContain('--reglages=/opt/wetchah/erp.env')
        ->not->toContain('APP_KEY');

    $verif = implode(' ', $m->commandeExecutant($ctx, true));
    expect($verif)->toContain('--verifier')->not->toContain('--name')->not->toContain('/srv/sauvegardes:');
});

test('vérifier dit ce qui attend, y compris les réglages que la nouvelle version demandera', function () {
    $m = serveurFactice([
        'git rev-parse --abbrev-ref HEAD' => [0, "main\n", ''],
        'git rev-parse --abbrev-ref --symbolic-full-name' => [0, "origin/main\n", ''],
        'git log -1' => [0, "aaaaaaa\tdernier\til y a 3 jours\n", ''],
        'git rev-parse HEAD' => [0, "aaaaaaa1111111\n", ''],
        'git rev-list --left-right --count' => [0, "0\t2\n", ''],
        'git log --format' => [0, "bbbbbbb\tajoute X\til y a 1 h\nccccccc\tcorrige Y\til y a 2 h\n", ''],
        'git show @{upstream}:.env.production.example' => [0, "APP_KEY=\nTZ=\nNOUVEAU=\n", ''],
        'df -Pk' => [0, "F 1 2 3 4 /\noverlay 1 1 10000000 1% /\n", ''],
    ]);

    $v = $m->verifier($this->opts, null);

    expect($v['ok'])->toBeTrue()->and($v['derriere'])->toBe(2)->and($v['commits'][0][0])->toBe('bbbbbbb')
        ->and($v['version'])->toBe('2026-10-01-0930')
        ->and($v['reglages_absents'])->toBe(['NOUVEAU'])
        ->and($v['a_reprendre'])->toBeFalse();
});

test('l\'onglet n\'est servi qu\'à l\'administrateur TECH ; le lancement démarre l\'exécutant', function () {
    $this->get(route('tech.serveur.etat'))->assertRedirect();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))->post(route('tech.serveur.lancer'))->assertRedirect();

    $inspect = [0, json_encode([[
        'Config' => ['Image' => 'wetchah-erp:latest', 'Labels' => [
            'com.docker.compose.project.working_dir' => '/opt/wetchah/wetchah_erp',
            'com.docker.compose.project.config_files' => '/opt/wetchah/wetchah_erp/docker-compose.prod.yml',
        ], 'Env' => []],
        'Mounts' => [['Type' => 'volume', 'Name' => 'wetchah-erp_erp_storage', 'Destination' => '/var/www/html/storage']],
    ]]), ''];
    $this->app->instance(MiseAJourServeur::class, serveurFactice(['docker inspect' => $inspect, 'docker ps' => [0, '', '']], $vues));
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)->getJson(route('tech.serveur.etat'))->assertOk()
        ->assertJsonPath('contexte.disponible', true)->assertJsonPath('contexte.version', 'latest')->assertJsonPath('etapes.8', 'Vérifier');

    $this->postJson(route('tech.serveur.lancer'), ['jeton' => 'ghp_jeton1234567890'])->assertStatus(202);
    $run = collect($vues)->first(fn ($v) => str_starts_with($v['cmd'], 'docker run'));
    expect($run['cmd'])->toContain('--name wetchah_erp-maj')->not->toContain('ghp_jeton');
    expect(Storage::disk('local')->get(MiseAJourServeur::DOSSIER . '/jeton'))->toBe('ghp_jeton1234567890');

    // Une seconde demande pendant que la première tourne est refusée.
    $this->postJson(route('tech.serveur.lancer'))->assertStatus(409);
});

test('le statut relit le journal ; un exécutant disparu est dit interrompu', function () {
    $this->app->instance(MiseAJourServeur::class, serveurFactice(['docker ps' => [0, '', '']]));
    $disque = Storage::disk('local');
    $disque->put(MiseAJourServeur::DOSSIER . '/statut.json', json_encode(['etat' => 'en_cours', 'etape' => 5, 'debut' => now()->subMinutes(10)->toIso8601String()]));
    $disque->put(MiseAJourServeur::DOSSIER . '/journal.log', json_encode(['h' => '14:35:00', 'n' => 'etape', 'e' => 5, 'm' => 'Étape 5 — Fabriquer la nouvelle version']) . "\n");
    $disque->put(MiseAJourServeur::DOSSIER . '/detail.log', "#1 building\n#2 DONE\n");
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)->getJson(route('tech.serveur.statut'))->assertOk()
        ->assertJsonPath('lignes.0.e', 5)
        ->assertJsonPath('detail', "#1 building\n#2 DONE")
        ->assertJsonPath('statut.etat', 'termine')
        ->assertJsonPath('statut.interrompue', true);
});

test('la commande de l\'exécutant écrit statut et journal pour la page', function () {
    $this->app->instance(MiseAJourServeur::class, serveurFactice(serveurNominal($this)));

    $this->artisan('erp:mettre-a-jour-serveur', [
        '--conteneur' => 'wetchah_erp-app', '--code' => $this->code, '--compose' => $this->code . '/docker-compose.prod.yml',
        '--reglages' => $this->reglages, '--volume' => 'wetchah-erp_erp_storage',
    ])->assertSuccessful();

    $statut = json_decode(Storage::disk('local')->get(MiseAJourServeur::DOSSIER . '/statut.json'), true);
    expect($statut)->toMatchArray(['etat' => 'termine', 'ok' => true, 'etape' => 8, 'nouvelle' => '2026-10-22-1435'])
        ->and(Storage::disk('local')->get(MiseAJourServeur::DOSSIER . '/journal.log'))->toContain('Étape 8 — Vérifier');
});
