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
        'nationality',
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

    /**
     * Helper : Vérifie si l'utilisateur possède l'un des rôles spécifiés
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Helper : Vérifie si l'utilisateur possède un rôle spécifique
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Helper : Alias pour isTechAdmin() pour la rétrocompatibilité des middlewares
     */
    public function isAdmin(): bool
    {
        return $this->isTechAdmin();
    }

    /**
     * Helper : Vérifie si l'utilisateur (propriétaire) possède ou a le droit de voir l'établissement spécifié
     */
    public function canViewTenant($tenantId): bool
    {
        if ($this->isTechAdmin()) {
            return true;
        }
        return $this->tenants()->where('id', $tenantId)->exists();
    }
}
