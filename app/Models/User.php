<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Rôles de l'administration globale
    public const ROLE_TECH_ADMIN = 'tech_admin';
    public const ROLE_OWNER = 'owner';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
        'company_name',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Relation : Un propriétaire (owner) possède plusieurs établissements (tenants).
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    /**
     * Helper : Vérifie si l'utilisateur est administrateur technique
     */
    public function isTechAdmin(): bool
    {
        return $this->role === self::ROLE_TECH_ADMIN;
    }

    /**
     * Helper : Vérifie si l'utilisateur est propriétaire
     */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Helper : Vérifie si l'utilisateur est en ligne
     */
    public function isOnline(): bool
    {
        return \Illuminate\Support\Facades\Cache::has('user-is-online-' . $this->id);
    }
}
