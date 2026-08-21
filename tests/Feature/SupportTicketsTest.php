<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tickets remontés par le personnel des établissements (bouton « Suggestion »
 * de wetchah_app) et traités dans le kanban de Support › Justification.
 *
 * Ces tickets vivent dans la base de leur établissement, jointe par PDO : les
 * tests remplacent le service d'accès par un double. Ce qui se joue ici n'est
 * pas le SQL mais l'agrégation, la tolérance aux établissements qui ne peuvent
 * pas répondre, et la trace laissée par un traitement.
 */

function ticketAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_TECH_ADMIN, 'is_active' => true]);
}

function ticketTenant(string $nom, string $slug): Tenant
{
    return Tenant::create([
        'name'           => $nom,
        'slug'           => $slug,
        'db_name'        => 'db_' . random_int(1000, 99999),
        'owner_id'       => User::factory()->create(['role' => User::ROLE_OWNER])->id,
        'is_active'      => true,
        'provisioned_at' => now(),
    ]);
}

function ligneTicket(int $id, string $sujet, string $statut = 'nouveau', string $quand = '-1 hour'): array
{
    return [
        'id'          => $id,
        'author_name' => 'Serge Mbarga',
        'author_role' => 'reception',
        'type'        => 'probleme',
        'subject'     => $sujet,
        'message'     => 'La page reste blanche après validation.',
        'context_url' => '/bookings/create',
        'status'      => $statut,
        'reply'       => null,
        'handled_by'  => null,
        'handled_at'  => null,
        'created_at'  => now()->modify($quand)->toDateTimeString(),
    ];
}

/** Double du service : sert des tickets en mémoire et mémorise les écritures. */
class TicketDatabaseDouble extends TenantDatabase
{
    /** @var array<string, array|string> slug => lecture, ou 'injoignable' */
    public array $parTenant = [];

    public array $ecritures = [];

    public bool $applique = true;

    public function supportTickets(Tenant $tenant, int $limite = 120): array
    {
        $etat = $this->parTenant[$tenant->slug] ?? ['tickets' => [], 'disponible' => true];

        if ($etat === 'injoignable') {
            throw new PDOException('base injoignable');
        }

        return $etat;
    }

    public function updateSupportTicket(
        Tenant $tenant,
        int $ticketId,
        string $statut,
        ?string $reponse,
        string $traitePar
    ): bool {
        $this->ecritures[] = [
            'slug' => $tenant->slug, 'ticket' => $ticketId, 'statut' => $statut,
            'reponse' => $reponse, 'par' => $traitePar,
        ];

        return $this->applique;
    }
}

function doubleTickets(array $parTenant = []): TicketDatabaseDouble
{
    $double = new TicketDatabaseDouble();
    $double->parTenant = $parTenant;
    app()->instance(TenantDatabase::class, $double);

    return $double;
}

// ── Lecture agrégée ──────────────────────────────────────────────────────────

test('les tickets de tous les établissements sont fusionnés du plus récent au plus ancien', function () {
    ticketTenant('Villa Boutanga', 'villa');
    ticketTenant('Weloobe', 'weloobe');

    doubleTickets([
        'villa'   => ['tickets' => [ligneTicket(1, 'Caisse bloquée', 'nouveau', '-3 hours')], 'disponible' => true],
        'weloobe' => ['tickets' => [ligneTicket(4, 'Ajouter un filtre', 'en_cours', '-10 minutes')], 'disponible' => true],
    ]);

    $reponse = $this->actingAs(ticketAdmin())->getJson(route('tech.support.tickets'));

    $reponse->assertOk();
    $tickets = $reponse->json('tickets');

    expect($tickets)->toHaveCount(2)
        ->and($tickets[0]['subject'])->toBe('Ajouter un filtre')
        ->and($tickets[0]['tenant'])->toBe('Weloobe')
        ->and($tickets[1]['subject'])->toBe('Caisse bloquée');

    expect($reponse->json('counts'))
        ->toMatchArray(['nouveau' => 1, 'en_cours' => 1, 'resolu' => 0, 'rejete' => 0, 'total' => 2]);
});

test('un établissement dont l\'application est antérieure au bouton est signalé à part', function () {
    ticketTenant('Villa Boutanga', 'villa');
    ticketTenant('Ancienne Résidence', 'ancienne');

    doubleTickets([
        'villa'    => ['tickets' => [ligneTicket(1, 'Caisse bloquée')], 'disponible' => true],
        // Table support_tickets absente : image applicative non mise à jour.
        'ancienne' => ['tickets' => [], 'disponible' => false],
    ]);

    $reponse = $this->actingAs(ticketAdmin())->getJson(route('tech.support.tickets'));

    // Ce n'est pas une panne : à distinguer d'une base injoignable, qui elle
    // demande une intervention.
    expect($reponse->json('outdated'))->toBe(['Ancienne Résidence'])
        ->and($reponse->json('unreachable'))->toBeEmpty()
        ->and($reponse->json('tickets'))->toHaveCount(1);
});

test('une base injoignable n\'empêche pas de lire les autres', function () {
    ticketTenant('Villa Boutanga', 'villa');
    ticketTenant('Weloobe', 'weloobe');

    doubleTickets([
        'villa'   => ['tickets' => [ligneTicket(1, 'Caisse bloquée')], 'disponible' => true],
        'weloobe' => 'injoignable',
    ]);

    $reponse = $this->actingAs(ticketAdmin())->getJson(route('tech.support.tickets'));

    $reponse->assertOk();
    expect($reponse->json('unreachable'))->toBe(['Weloobe'])
        ->and($reponse->json('tickets'))->toHaveCount(1);
});

test('un établissement non provisionné n\'est pas interrogé', function () {
    $tenant = ticketTenant('Villa Boutanga', 'villa');
    $tenant->update(['provisioned_at' => null]);

    doubleTickets(['villa' => ['tickets' => [ligneTicket(1, 'Caisse bloquée')], 'disponible' => true]]);

    $reponse = $this->actingAs(ticketAdmin())->getJson(route('tech.support.tickets'));

    expect($reponse->json('tickets'))->toBeEmpty();
});

// ── Traitement d'un ticket ───────────────────────────────────────────────────

test('déplacer un ticket écrit le statut et la réponse dans la base de l\'établissement', function () {
    $tenant = ticketTenant('Villa Boutanga', 'villa');
    $double = doubleTickets();
    $admin  = ticketAdmin();

    $this->actingAs($admin)->postJson(route('tech.support.tickets.update'), [
        'tenant_id' => $tenant->id,
        'ticket_id' => 12,
        'status'    => 'resolu',
        'reply'     => 'Corrigé dans la version déployée ce matin.',
    ])->assertOk()->assertJson(['ok' => true]);

    expect($double->ecritures)->toHaveCount(1)
        ->and($double->ecritures[0])->toMatchArray([
            'slug' => 'villa', 'ticket' => 12, 'statut' => 'resolu',
            'reponse' => 'Corrigé dans la version déployée ce matin.', 'par' => $admin->name,
        ]);
});

test('le traitement d\'un ticket est audité', function () {
    $tenant = ticketTenant('Villa Boutanga', 'villa');
    doubleTickets();

    $this->actingAs(ticketAdmin())->postJson(route('tech.support.tickets.update'), [
        'tenant_id' => $tenant->id,
        'ticket_id' => 12,
        'status'    => 'en_cours',
    ])->assertOk();

    $trace = AuditLog::where('event_type', 'support_ticket_update')->first();

    expect($trace)->not->toBeNull()
        ->and($trace->module)->toBe('support')
        ->and($trace->payload['ticket_id'])->toBe(12)
        ->and($trace->payload['status'])->toBe('en_cours')
        ->and($trace->payload['replied'])->toBeFalse();
});

test('un ticket absent de la base remonte une erreur plutôt qu\'un succès silencieux', function () {
    $tenant = ticketTenant('Villa Boutanga', 'villa');
    $double = doubleTickets();
    $double->applique = false;

    $this->actingAs(ticketAdmin())->postJson(route('tech.support.tickets.update'), [
        'tenant_id' => $tenant->id,
        'ticket_id' => 999,
        'status'    => 'resolu',
    ])->assertNotFound();

    expect(AuditLog::where('event_type', 'support_ticket_update')->count())->toBe(0);
});

test('un statut inventé est refusé', function () {
    $tenant = ticketTenant('Villa Boutanga', 'villa');
    $double = doubleTickets();

    $this->actingAs(ticketAdmin())->postJson(route('tech.support.tickets.update'), [
        'tenant_id' => $tenant->id,
        'ticket_id' => 12,
        'status'    => 'archive_definitivement',
    ])->assertStatus(422);

    expect($double->ecritures)->toBeEmpty();
});

// ── Accès ────────────────────────────────────────────────────────────────────

test('les tickets sont réservés à l\'administrateur technique', function () {
    $tenant = ticketTenant('Villa Boutanga', 'villa');
    $double = doubleTickets();

    $proprietaire = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);

    $this->actingAs($proprietaire)->get(route('tech.support.tickets'))->assertRedirect();

    $this->actingAs($proprietaire)->post(route('tech.support.tickets.update'), [
        'tenant_id' => $tenant->id, 'ticket_id' => 12, 'status' => 'resolu',
    ]);

    expect($double->ecritures)->toBeEmpty();
});
