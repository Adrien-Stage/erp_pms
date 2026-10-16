<?php

/**
 * Réglages par défaut du provisioning, tels qu'un serveur neuf les reçoit.
 */

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test("l'image GRC par défaut est celle qui existe réellement sur GHCR", function () {
    // ghcr.io/wetchah/wetchah_grc répondait « denied » : le module GRC ne démarrait jamais.
    expect(config('provisioning.registry_image_grc'))->toBe('ghcr.io/clyde237/wetchah_grc');
});

test("un dossier des établissements non inscriptible donne un message qui dit quoi faire", function () {
    config(['provisioning.tenants_base_path' => '/proc/inexistant/tenants']);
    $tenant = etablissementValide(['slug' => 'hotel-droits']);

    $appeler = fn () => (new ReflectionMethod(TenantProvisioningService::class, 'generateDockerCompose'))
        ->invoke(app(TenantProvisioningService::class), $tenant, 'ghcr.io/x/app@sha256:aaa', null, null, fn () => null);

    expect($appeler)->toThrow(RuntimeException::class, 'sudo chown 33:33');
});

test("le dossier des établissements par défaut est celui que monte le docker-compose", function () {
    // Sans cela, la console écrivait dans /var/meka-erp/tenants — absent du conteneur :
    // « mkdir(): Permission denied » à la première création.
    $compose = file_get_contents(base_path('docker-compose.yml'));
    $defaut = '${TENANTS_BASE_PATH:-/var/wetchah/tenants}';

    expect($compose)->toContain("- {$defaut}:{$defaut}")
        ->and($compose)->toContain("TENANTS_BASE_PATH: {$defaut}");
    // phpunit.xml impose un dossier de test : on lit la valeur par défaut dans le fichier de config.
    expect(file_get_contents(base_path('config/provisioning.php')))->toContain("env('TENANTS_BASE_PATH', '/var/wetchah/tenants')");
});
