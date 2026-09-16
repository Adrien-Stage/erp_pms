<?php

/**
 * Lisibilité de la sortie d'un « docker pull » surveillé.
 *
 * Le pull est lancé sur un pseudo-terminal : sans lui, Docker n'écrit qu'une
 * ligne par changement d'état de couche et reste muet pendant le transfert —
 * 942 octets pour 26 couches, puis plus rien. La surveillance interprétait ce
 * silence comme un blocage et tuait le téléchargement, à chaque tentative,
 * pour toute image dont une seule couche dépassait le seuil.
 *
 * Le terminal rend la progression visible, au prix des séquences
 * d'échappement que ces deux méthodes retirent.
 */

use App\Services\TenantProvisioningService;

function invoquer(string $methode, ...$arguments)
{
    return (new ReflectionMethod(TenantProvisioningService::class, $methode))
        ->invoke(app(TenantProvisioningService::class), ...$arguments);
}

test('la ligne affichée est débarrassée des déplacements de curseur', function () {
    $brut = "\e[1A\e[2Kf6387304975a: Downloading [====>    ]  45.2MB/120MB\r\n";

    expect(invoquer('derniereLigneLisible', $brut))
        ->toBe('f6387304975a: Downloading [====>    ]  45.2MB/120MB');
});

test('seule la dernière ligne utile est retenue', function () {
    $brut = "ad4875b55dd4: Pulling fs layer\n\e[2K642bd5ea257f: Download complete\n\n";

    expect(invoquer('derniereLigneLisible', $brut))->toBe('642bd5ea257f: Download complete');
});

test('un fragment sans texte ne remplace pas la ligne courante', function () {
    expect(invoquer('derniereLigneLisible', "\e[1A\e[2K\r\n"))->toBe('');
});

test('la ligne est bornée pour ne pas inonder le journal', function () {
    expect(mb_strlen(invoquer('derniereLigneLisible', str_repeat('x', 500))))->toBe(120);
});

test('la sortie remontée est dédoublonnée des redessins successifs', function () {
    // Le même état réécrit cent fois par le terminal ne doit apparaître qu'une fois.
    $brut = str_repeat("\e[1A\e[2Kf6387304975a: Downloading\r\n", 100)
          . "f6387304975a: Pull complete\n";

    expect(invoquer('sortieLisible', $brut))
        ->toBe("f6387304975a: Downloading\nf6387304975a: Pull complete");
});

test('la sortie remontée ne garde que les dernières lignes', function () {
    $brut = implode("\n", array_map(fn ($i) => "couche-{$i}: Pull complete", range(1, 80)));

    $lignes = explode("\n", invoquer('sortieLisible', $brut));

    expect($lignes)->toHaveCount(30)
        ->and($lignes[29])->toBe('couche-80: Pull complete');
});
