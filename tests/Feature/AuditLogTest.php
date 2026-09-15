<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;

uses(RefreshDatabase::class);

test('logging in records an audit log and updates last_login_at', function () {
    
    $tenant = etablissementValide([
        'name' => 'Villa Boutanga',
        'slug' => 'villa-boutanga',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
        'is_active' => true,
    ]);

    expect($user->last_login_at)->toBeNull();

    // Trigger Login Event
    event(new Login('web', $user, false));

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();

    $log = AuditLog::latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->event_type)->toBe('login');
    expect($log->user_id)->toBe($user->id);
    expect($log->module)->toBe('auth');
});

test('failed login attempts record an audit log', function () {
    event(new Failed('web', null, ['email' => 'hacker@example.com', 'password' => 'secret']));

    $log = AuditLog::latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->event_type)->toBe('failed_login');
    expect($log->description)->toContain('hacker@example.com');
});

test('access denied is recorded in audit logs', function () {
    
    $tenant = etablissementValide([
        'name' => 'Villa Boutanga',
        'slug' => 'villa-boutanga',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $manager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
        'is_active' => true,
    ]);
    
    $this->actingAs($manager);

    // Access admin route which calls AdminOnly middleware
    $response = $this->get('/tech/establishments', ['X-Requested-With' => 'XMLHttpRequest']);
    $response->assertStatus(403);

    $log = AuditLog::where('event_type', 'access_denied')->first();
    expect($log)->not->toBeNull();
    expect($log->module)->toBe('security');
    expect($log->user_id)->toBe($manager->id);
});

test('admin can toggle user status and reset password', function () {
    
    $tenant = etablissementValide([
        'name' => 'Villa Boutanga',
        'slug' => 'villa-boutanga',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_OWNER,
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    // Toggle active status
    $response = $this->post(route('tech.users.toggle-active', $staff));
    $response->assertRedirect();
    
    $staff->refresh();
    expect($staff->is_active)->toBeFalse();

    $log = AuditLog::where('event_type', 'user_management')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->description)->toContain('désactivé');

    // Force password reset
    $response = $this->post(route('tech.users.reset-password', $staff));
    $response->assertRedirect();
    // Le mot de passe temporaire est renvoyé dans le message de succès,
    // pas dans une clé dédiée.
    $response->assertSessionHas('success');

    $log = AuditLog::where('event_type', 'user_management')->latest('id')->first();
    expect($log->description)->toContain('Mot de passe réinitialisé');
});

test('admin can filter audit logs by event type', function () {
    $admin = User::factory()->create([
        'role' => User::ROLE_TECH_ADMIN,
        'is_active' => true,
    ]);

    // Le journal de l'ERP ne porte pas de colonne tenant_id : le filtrage par
    // établissement qu'attendait ce test relevait de l'application, dont il a
    // été recopié. Seul le filtre par type d'événement existe ici.
    AuditLog::create([
        'user_id'     => $admin->id,
        'event_type'  => 'export_supervision',
        'description' => 'Action de supervision',
        'module'      => 'tech_admin',
    ]);

    AuditLog::create([
        'user_id'     => $admin->id,
        'event_type'  => 'login',
        'description' => 'Connexion enregistree',
        'module'      => 'auth',
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('tech.dashboard', ['tab' => 'audit', 'event_type' => 'login']));
    $response->assertStatus(200);

    $logs = collect($response->viewData('logs')->items());
    expect($logs->pluck('event_type')->unique()->all())->toBe(['login']);
    expect($logs->pluck('description'))->toContain('Connexion enregistree');
});
