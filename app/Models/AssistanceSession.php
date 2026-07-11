<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistanceSession extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'reason',
        'token',
        'status',
        'expires_at',
        'closed_at',
    ];

    protected $casts = [
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
        return $this->status === 'active' && $this->expires_at->isFuture();
    }
}
