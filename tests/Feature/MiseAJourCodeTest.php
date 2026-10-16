<?php

/**
 * Bouton « Mettre à jour le code » (onglet Système) : git pull de la branche
 * courante. Git est simulé par un exécuteur qui répond commande par commande.
 */

use App\Models\User;
use App\Services\CodeUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Exécuteur factice. $reponses : sous-commande git (« rev-parse --abbrev-ref HEAD »…)
 * => [code, sortie, erreurs]. Les commandes passées par « docker exec » sont dépouillées.
 */
function mise(array $reponses, ?array &$journal = null, ?string &$vues = null): CodeUpdater
{
    $vues = '';
    $executeur = function (array $cmd, int $delai) use ($reponses, &$vues) {
        $i = array_search('-C', $cmd, true);
        $sous = $i === false ? implode(' ', $cmd) : implode(' ', array_slice($cmd, $i + 2));
        $vues .= $sous . "\n";
        foreach ($reponses as $motif => $rep) {
            if (str_starts_with($sous, $motif)) {
                return $rep;
            }
        }

        return [0, '', ''];
    };

    $depot = sys_get_temp_dir() . '/depot-test-' . random_int(1, 999999);
    mkdir($depot . '/.git', 0777, true);

    return new CodeUpdater($executeur, $depot);
}

function reponsesBase(array $en_plus = []): array
{
    return $en_plus + [
        'rev-parse --abbrev-ref HEAD' => [0, "develop\n", ''],
        'rev-parse --abbrev-ref --symbolic-full-name' => [0, "origin/develop\n", ''],
        'log -1' => [0, "abc1234\tdernier commit\til y a 2 jours\n", ''],
        'remote get-url' => [0, "https://jeton123@github.com/Adrien-Stage/erp_pms.git\n", ''],
        'status --porcelain' => [0, '', ''],
        'fetch' => [0, '', ''],
    ];
}

function lancerMaj(CodeUpdater $m): array
{
    $lignes = [];
    $ok = $m->mettreAJour(function ($n, $msg) use (&$lignes) { $lignes[] = "$n: $msg"; });

    return [$ok, implode("\n", $lignes)];
}

test("l'état donne la branche, la version et masque le jeton de l'adresse du dépôt", function () {
    $etat = mise(reponsesBase())->etat();

    expect($etat['depot'])->toBeTrue()
        ->and($etat['branche'])->toBe('develop')
        ->and($etat['amont'])->toBe('origin/develop')
        ->and($etat['commit']['hash'])->toBe('abc1234')
        ->and($etat['remote'])->toBe('https://github.com/Adrien-Stage/erp_pms.git')
        ->and($etat['modifies'])->toBe([]);
});

test("sans .git, la console se dit embarquée dans une image", function () {
    $m = new CodeUpdater(fn () => [0, '', ''], sys_get_temp_dir() . '/sans-git-' . random_int(1, 99999));

    expect($m->etat())->toBe(['depot' => false]);
    [$ok, $texte] = lancerMaj($m);
    expect($ok)->toBeFalse()->and($texte)->toContain("image Docker");
});

test("vérifier compte les commits en attente", function () {
    $m = mise(reponsesBase([
        'rev-list --left-right --count' => [0, "0\t2\n", ''],
        'log --format' => [0, "f00ba12\tcorrige X\til y a 1 h\n9c8d7e6\tajoute Y\til y a 3 h\n", ''],
    ]));

    $v = $m->verifier();

    expect($v['ok'])->toBeTrue()->and($v['derriere'])->toBe(2)->and($v['devant'])->toBe(0)
        ->and($v['commits'][0][0])->toBe('f00ba12');
});

test("un dépôt en SSH ou privé donne un message qui dit quoi faire", function () {
    $ssh = mise(reponsesBase(['fetch' => [128, '', 'git@github.com: Permission denied (publickey).']]))->verifier();
    $prive = mise(reponsesBase(['fetch' => [128, '', "fatal: could not read Username for 'https://github.com': terminal prompts disabled"]]))->verifier();

    expect($ssh['ok'])->toBeFalse()->and($ssh['message'])->toContain('HTTPS')
        ->and($prive['message'])->toContain('jeton');
});

test("la mise à jour refuse d'écraser des fichiers modifiés à la main", function () {
    $m = mise(reponsesBase(['status --porcelain' => [0, " M app/Models/User.php\n", '']]), $j, $vues);

    [$ok, $texte] = lancerMaj($m);

    expect($ok)->toBeFalse()->and($texte)->toContain('app/Models/User.php')
        ->and($vues)->not->toContain('pull');
});

test("la mise à jour refuse un HEAD détaché et une branche sans suivi", function () {
    [$ok1, $t1] = lancerMaj(mise(reponsesBase(['rev-parse --abbrev-ref HEAD' => [0, "HEAD\n", '']])));
    [$ok2, $t2] = lancerMaj(mise(reponsesBase(['rev-parse --abbrev-ref --symbolic-full-name' => [128, '', 'no upstream']])));

    expect($ok1)->toBeFalse()->and($t1)->toContain('HEAD détaché')
        ->and($ok2)->toBeFalse()->and($t2)->toContain('set-upstream-to');
});

test("déjà à jour : aucun pull", function () {
    $m = mise(reponsesBase(['rev-list --left-right --count' => [0, "0\t0\n", '']]), $j, $vues);

    [$ok, $texte] = lancerMaj($m);

    expect($ok)->toBeTrue()->and($texte)->toContain('Déjà à jour')->and($vues)->not->toContain('pull');
});

test("une branche divergente n'est pas tirée", function () {
    $m = mise(reponsesBase(['rev-list --left-right --count' => [0, "1\t3\n", '']]), $j, $vues);

    [$ok, $texte] = lancerMaj($m);

    expect($ok)->toBeFalse()->and($texte)->toContain('divergé')->and($vues)->not->toContain('pull');
});

test("des nouveautés : pull en avance rapide, puis migrations", function () {
    $m = mise(reponsesBase([
        'rev-list --left-right --count' => [0, "0\t1\n", ''],
        'rev-parse HEAD' => [0, "1111111aaaa\n", ''],
        'diff --name-only' => [0, "app/Services/X.php\ncomposer.lock\n", ''],
    ]), $j, $vues);

    [$ok, $texte] = lancerMaj($m);

    expect($ok)->toBeTrue()
        ->and($vues)->toContain('pull --ff-only')
        ->and($texte)->toContain('Code mis à jour')
        ->and($texte)->toContain('composer.lock a changé')   // dépendances : avertissement, pas de composer install
        ->and($texte)->toContain('Migrations de la base')
        ->and($vues)->not->toContain('npm');                 // aucun fichier d'écran n'a changé
});

test("un pull refusé par git est dit tel quel et arrête la mise à jour", function () {
    $m = mise(reponsesBase([
        'rev-list --left-right --count' => [0, "0\t1\n", ''],
        'rev-parse HEAD' => [0, "1111111aaaa\n", ''],
        'pull' => [1, '', 'fatal: Not possible to fast-forward, aborting.'],
    ]));

    [$ok, $texte] = lancerMaj($m);

    expect($ok)->toBeFalse()->and($texte)->toContain('divergé')->and($texte)->not->toContain('Migrations');
});

test("l'onglet Système n'est servi qu'à l'administrateur TECH", function () {
    $this->get(route('tech.code.etat'))->assertRedirect();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))->get(route('tech.code.etat'))->assertRedirect();
    $this->post(route('tech.code.lancer'))->assertRedirect();

    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);
    $this->actingAs($admin)->get(route('tech.dashboard', ['tab' => 'system']))->assertOk()->assertSee('Mise à jour du code');
});

test("lancer sans dépôt git renvoie une erreur claire", function () {
    $this->app->instance(CodeUpdater::class, new CodeUpdater(fn () => [0, '', ''], sys_get_temp_dir() . '/pas-de-depot-' . random_int(1, 99999)));
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)->postJson(route('tech.code.lancer'))->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'image Docker'));
    $this->getJson(route('tech.code.etat'))->assertOk()->assertJsonPath('depot', false);
});

test("le statut relit le journal de la mise à jour", function () {
    Storage::fake('local');
    Storage::disk('local')->put('mise-a-jour/statut.json', json_encode(['etat' => 'termine', 'ok' => true, 'vide_opcache' => true]));
    Storage::disk('local')->put('mise-a-jour/journal.log', json_encode(['h' => '10:00:00', 'n' => 'ok', 'm' => 'Déjà à jour']) . "\n");
    $admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);

    $this->actingAs($admin)->getJson(route('tech.code.statut'))
        ->assertOk()->assertJsonPath('statut.ok', true)->assertJsonPath('lignes.0.m', 'Déjà à jour');
});
