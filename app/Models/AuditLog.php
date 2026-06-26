<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'module',
        'description',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper static method to log audit events easily.
     */
    public static function record(?int $userId, string $eventType, string $description, ?string $module = null, ?array $payload = null): self
    {
        if (!$userId && Auth::check()) {
            $userId = Auth::id();
        }

        return self::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'module' => $module ?? 'system',
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'payload' => $payload,
        ]);
    }
}
