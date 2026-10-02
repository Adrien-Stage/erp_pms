<?php

/**
 * Traces des interventions des administrateurs d'établissement.
 *
 * L'établissement transmet chaque intervention de son administrateur dans
 * l'exploitation ; il s'authentifie avec son propre secret d'orchestration.
 * Le support et le propriétaire les lisent dans « Droits & rôles ».
 */

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantIntervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->dossierComposes = sys_get_temp_dir() . '/interventions-test-' . random_int(1, 999999);
    config(['provisioning.tenants_base_path' => $this->dossierComposes, 'provisioning.reporting_secret' => '']);
});

afterEach(function () {
    exec('rm -rf ' . escapeshellarg($this->dossierComposes));
});

function etablissementAvecSecret(string $slug, string $secret): Tenant
{
    $tenant = etablissementValide(['slug' => $slug, 'provisioned_at' => now(), 'docker_app_container' => "meka-erp-{$slug}-app"]);

    @mkdir(dirname($tenant->composePath()), 0777, true);
    file_put_contents($tenant->composePath(), "services:\n  meka-erp-{$slug}-app:\n    environment:\n      ORCHESTRATION_SECRET: \"{$secret}\"\n");

    return $tenant;
}

function trace(array $champs = []): array
{
    return $champs + [
        'reference' => 7,
        'administrateur' => 'Serge Informatique',
        'email' => 'it@zingana.test',
        'motif' => "Correction d'un article mal saisi",
        'perimetres' => ['Économat'],
        'debut' => now()->subMinutes(5)->toIso8601String(),
        'fin_prevue' => now()->addMinutes(25)->toIso8601String(),
        'fin_reelle' => null,
        'cloture' => null,
        'actions' => 2,
        'tardive' => false,
    ];
}

test("l'établissement transmet son intervention avec son propre secret", function () {
    $tenant = etablissementAvecSecret('zingana', 'secret-zingana');

    $this->postJson('/api/etablissements/zingana/interventions', trace(), ['Authorization' => 'Bearer secret-zingana'])
        ->assertCreated();

    $intervention = TenantIntervention::firstOrFail();
    expect($intervention->tenant_id)->toBe($tenant->id)
        ->and($intervention->reference)->toBe(7)
        ->and($intervention->perimetres)->toBe(['Économat'])
        ->and(AuditLog::where('event_type', 'tenant_intervention')->exists())->toBeTrue();
});

test("sans le bon secret, rien n'est enregistré — pas même avec celui d'un autre établissement", function () {
    etablissementAvecSecret('zingana', 'secret-zingana');
    etablissementAvecSecret('kore-teck', 'secret-kore');

    $this->postJson('/api/etablissements/zingana/interventions', trace())->assertUnauthorized();
    $this->postJson('/api/etablissements/zingana/interventions', trace(), ['Authorization' => 'Bearer faux'])->assertUnauthorized();
    $this->postJson('/api/etablissements/zingana/interventions', trace(), ['Authorization' => 'Bearer secret-kore'])->assertUnauthorized();

    expect(TenantIntervention::count())->toBe(0);
});

test("un établissement sans secret d'orchestration ne transmet rien", function () {
    etablissementValide(['slug' => 'ancien', 'provisioned_at' => now()]);

    $this->postJson('/api/etablissements/ancien/interventions', trace(), ['Authorization' => 'Bearer quoi-que-ce-soit'])
        ->assertUnauthorized();
});

test('la clôture met à jour la même intervention, et une trace tardive le reste', function () {
    etablissementAvecSecret('zingana', 'secret-zingana');
    $entete = ['Authorization' => 'Bearer secret-zingana'];

    $this->postJson('/api/etablissements/zingana/interventions', trace(['tardive' => true]), $entete)->assertCreated();
    $this->postJson('/api/etablissements/zingana/interventions', trace([
        'fin_reelle' => now()->toIso8601String(), 'cloture' => 'terminee', 'actions' => 5, 'tardive' => false,
    ]), $entete)->assertOk();

    $intervention = TenantIntervention::sole();
    expect($intervention->cloture)->toBe('terminee')
        ->and($intervention->actions)->toBe(5)
        ->and($intervention->tardive)->toBeTrue();
});

test("l'onglet Interventions montre les traces au propriétaire", function () {
    config(['provisioning.reporting_secret' => 'jeton-de-service']);
    $tenant = etablissementValide(['slug' => 'zingana', 'provisioned_at' => now(), 'docker_app_container' => 'meka-erp-zingana-app']);
    TenantIntervention::create([
        'tenant_id' => $tenant->id, 'reference' => 3, 'administrateur' => 'Serge Informatique',
        'motif' => 'Urgence en caisse', 'perimetres' => ['Comptabilité et caisses'],
        'debut' => now()->subHour(), 'fin_prevue' => now()->subMinutes(30), 'fin_reelle' => now()->subMinutes(40),
        'cloture' => 'terminee', 'actions' => 4, 'tardive' => true,
    ]);
    Http::fake(['*/api/permissions/matrice' => Http::response(matriceV2(), 200)]);

    $this->actingAs(User::find($tenant->owner_id))
        ->get(route('business.establishments.permissions', ['tenant' => $tenant, 'onglet' => 'interventions']))
        ->assertOk()
        ->assertSee('Urgence en caisse')
        ->assertSee('Serge Informatique')
        ->assertSee('Tardive');
});
