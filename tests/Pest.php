<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->beforeEach(function () {
        // Les vues appellent @vite. Hors serveur de développement, Laravel va
        // chercher le manifeste produit par « npm run build » — absent d'un
        // dépôt fraîchement cloné, puisque public/build et public/hot sont
        // tous deux ignorés par git. Chaque test rendant une vue repart alors
        // en 500, pour une raison étrangère à ce qu'il vérifie.
        //
        // La compilation reste contrôlée là où elle compte : l'image de
        // production exécute « npm run build ».
        $this->withoutVite();
    })
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Crée un établissement valide.
 *
 * La table tenants a gagné des colonnes obligatoires au fil du provisioning —
 * db_name, owner_id — que les tests écrits avant ne fournissaient pas. Les
 * rassembler ici évite que chaque nouvelle colonne ne casse à nouveau la
 * moitié de la suite.
 *
 * Les attributs passés priment : un test qui vérifie un slug le fixe lui-même.
 */
function etablissementValide(array $attributs = []): \App\Models\Tenant
{
    return \App\Models\Tenant::create(array_merge([
        'name'      => 'Établissement de test',
        'slug'      => 'etab-' . random_int(1, 999999),
        'db_name'   => 'db_' . random_int(1000, 999999),
        'owner_id'  => \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_OWNER])->id,
        'is_active' => true,
    ], $attributs));
}
