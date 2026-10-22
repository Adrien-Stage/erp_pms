<?php

/**
 * L'identité d'un établissement — nom, coordonnées, devise, pays, couleurs —
 * se règle dans la console et part vers son application à chaque
 * enregistrement de la fiche : l'application la montre sans pouvoir la
 * modifier.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['provisioning.reporting_secret' => 'jeton-de-service']);
    $this->admin = User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);
});

function fichePostee(array $autres = []): array
{
    return $autres + [
        'name' => 'Hôtel Zingana',
        'slug' => 'ignore',
        'address' => 'Quartier Bastos, Yaoundé',
        'phone' => '+237 6 99 00 11 22',
        'email' => 'contact@zingana.cm',
        'country' => 'Cameroun',
        'currency' => 'XAF',
        'theme' => ['primary' => '#0F766E'],
    ];
}

test("enregistrer la fiche envoie l'identité à l'application de l'établissement", function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    $tenant = etablissementValide(['docker_app_container' => 'meka-erp-zingana-app', 'provisioned_at' => now()]);

    $this->actingAs($this->admin)->post(route('tech.establishments.update', $tenant), fichePostee())
        ->assertSessionHas('success', fn ($m) => str_contains($m, "dans l'application"))
        ->assertSessionMissing('warning');

    Http::assertSent(fn ($r) => $r->method() === 'PUT'
        && $r->url() === 'http://meka-erp-zingana-app/api/etablissement'
        && $r->hasHeader('Authorization', 'Bearer jeton-de-service')
        && $r['name'] === 'Hôtel Zingana'
        && $r['address'] === 'Quartier Bastos, Yaoundé'
        && $r['currency'] === 'XAF'
        && $r['country'] === 'Cameroun'
        && $r['theme']['primary'] === '#0F766E');
});

test("si l'application ne reçoit pas, la console le dit ; la fiche reste enregistrée", function () {
    Http::fake(['*' => Http::response([], 404)]);
    $tenant = etablissementValide(['docker_app_container' => 'meka-erp-zingana-app', 'provisioned_at' => now()]);

    $this->actingAs($this->admin)->post(route('tech.establishments.update', $tenant), fichePostee())
        ->assertSessionHas('warning', fn ($m) => str_contains($m, "n'a pas encore cette fonction"));

    expect($tenant->fresh()->name)->toBe('Hôtel Zingana');
});

test("un établissement pas encore provisionné ne reçoit rien", function () {
    Http::fake();
    $tenant = etablissementValide();

    $this->actingAs($this->admin)->post(route('tech.establishments.update', $tenant), fichePostee())
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'une fois provisionnée'));

    Http::assertNothingSent();
});
