<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Support › Logs applicatifs : la liste est rendue côté navigateur à partir du
 * JSON de tech.support.app-logs, la pagination l'est donc aussi. Ce test garde
 * le câblage : si le tableau reboucle sur la liste complète au lieu de la
 * tranche courante, la pagination redevient décorative sans que rien ne casse.
 */

test('la liste des logs applicatifs est paginée par tranches de 20', function () {
    $admin = User::factory()->create([
        'role'      => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('tech.dashboard', ['tab' => 'support']));

    $response->assertOk()
        ->assertSee('appLogsPerPage: 20', false)
        // Le tableau parcourt la tranche de la page courante, pas tout le lot.
        ->assertSee('x-for="log in appLogsVisible"', false)
        ->assertDontSee('x-for="log in appLogs"', false)
        // Commandes de navigation entre les pages.
        ->assertSee('x-for="p in appLogsPages"', false)
        ->assertSee('appLogsGoTo(appLogsPage + 1)', false)
        ->assertSee('appLogsGoTo(appLogsPage - 1)', false);
});
