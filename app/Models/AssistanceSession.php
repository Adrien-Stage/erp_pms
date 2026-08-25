<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistanceSession extends Model
{
    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'user_id',
        'reason',
        'token',
        'status',
        'expires_at',
        'closed_at',
    ];

    protected $casts = [
        'ticket_id' => 'integer',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Une session est réellement exploitable tant qu'elle est active ET non
     * expirée. La colonne 'status' est mise à 'expired' paresseusement à la
     * lecture (voir AdminAuditController::assistanceList).
     */
    public function isLive(): bool
    {
        return $this->status === 'active' && $this->expires_at && $this->expires_at->isFuture();
    }

    /**
     * Jeton signé HMAC + URL d'accès direct en mode assistance.
     */
    public function entryUrl(): ?string
    {
        if (!$this->isLive() || empty(config('assistance.secret'))) {
            return null;
        }

        $tenant = $this->tenant;
        if (!$tenant || !$tenant->app_port) {
            return null;
        }

        $payload = [
            'slug'    => $tenant->slug,
            'session' => $this->token,
            'admin'   => $this->user?->name ?? 'Support',
            'exp'     => $this->expires_at->timestamp,
        ];

        $encoded   = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, config('assistance.secret'));

        $base = 'http://localhost:' . $tenant->app_port;

        return $base . '/assistance/enter?token=' . $encoded . '.' . $signature;
    }

    /**
     * Ouvre — ou réutilise — la session d'assistance rattachée à un ticket.
     *
     * L'admin demandeur est exigé, jamais deviné : entrer en assistance
     * connecte le support dans l'application de l'établissement sous
     * l'identité de son administrateur, et c'est ce demandeur-là que le
     * journal d'audit engage.
     */
    public static function openForTicket(
        Tenant $tenant,
        int $ticketId,
        string $subject,
        string $authorName,
        User $admin
    ): ?self {
        if (empty(config('assistance.secret')) || !$tenant->provisioned_at) {
            return null;
        }

        // Une session encore vivante sur ce ticket est réutilisée telle
        // quelle : en rouvrir une prolongerait l'accès sans nouvelle
        // justification.
        $active = self::where('tenant_id', $tenant->id)
            ->where('ticket_id', $ticketId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if ($active) {
            return $active;
        }

        $reason  = "Ticket #{$ticketId} — {$subject} (signalé par {$authorName}, {$tenant->name})";
        $ttl     = (int) config('assistance.ttl_minutes', 30);
        $expires = now()->addMinutes($ttl);

        $session = self::create([
            'tenant_id'  => $tenant->id,
            'ticket_id'  => $ticketId,
            'user_id'    => $admin->id,
            'reason'     => $reason,
            'token'      => \Illuminate\Support\Str::random(48),
            'status'     => 'active',
            'expires_at' => $expires,
        ]);

        AuditLog::record(
            $admin->id,
            'assistance_open',
            "Ouverture d'une session d'assistance sur {$tenant->name} — motif : {$reason}",
            'support',
            ['slug' => $tenant->slug, 'session' => $session->token, 'ticket_id' => $ticketId, 'expires_at' => $expires->toIso8601String()]
        );

        return $session;
    }
}
