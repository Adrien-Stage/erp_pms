<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest or non-admin user cannot access export routes', function () {

    $tenant = etablissementValide([
        'name' => 'Villa Boutanga Test',
        'slug' => 'villa-boutanga-test',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $manager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
        'is_active' => true,
    ]);

    // Visiteur non connecté
    $this->get(route('tech.export.supervision'))->assertRedirect('/login');

    // Connecté mais sans le rôle : le middleware redirige une navigation
    // ordinaire et ne répond 403 qu'à une requête XHR.
    $this->actingAs($manager);
    $this->get(route('tech.export.supervision'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertStatus(403);
});

test('an admin can download supervision csv', function () {
    \Carbon\Carbon::setTestNow(now());

    $admin = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    $tenant = etablissementValide([
        'name' => 'Villa Boutanga Test',
        'slug' => 'villa-boutanga-test',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('tech.export.supervision'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename=supervision_etablissements_' . now()->format('Ymd_His') . '.csv');

    $content = $response->streamedContent();

    // Check BOM UTF-8
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();

    // Verify Headers and data
    expect($content)->toContain('"Villa Boutanga Test";villa-boutanga-test');

    // Verify AuditLog entry was recorded for the supervision export
    $exportLog = AuditLog::where('event_type', 'export_supervision')->first();
    expect($exportLog->description)->toContain('Export CSV du rapport de supervision');
    
    expect($exportLog)->not->toBeNull();
    expect($exportLog->user_id)->toBe($admin->id);
});
