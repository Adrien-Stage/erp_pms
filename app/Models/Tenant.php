<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'phone',
        'email',
        
        // Configuration Docker / Base de données
        'db_name',
        'db_username',
        'db_password',
        'docker_app_container',
        'docker_db_container',
        'docker_web_container',
        'docker_grc_container',
        'docker_status', // running, stopped, creating, error
        'docker_image_tag', // digest (sha256:...) de l'image ghcr.io figé pour ce tenant
        'web_image_tag', // digest de l'image wetchah_site figé pour ce tenant (module website)
        'grc_image_tag', // digest de l'image wetchah_GRC figé pour ce tenant (module grc)
        'app_port',
        'db_port',
        'web_port',
        'grc_port',

        // Modules & Features
        'api_enabled',
        'website_enabled',
        'grc_enabled',
        'modules',
        
        // Propriétaire et statut
        'is_active',
        'users_count',
        'owner_id',
        
        // Métadonnées
        'settings',
        'site_content', // contenu marketing du site vitrine (module website)
        'provisioned_at',
        'last_health_check',
    ];

    protected $casts = [
        'settings' => 'array',
        'site_content' => 'array',
        'modules' => 'array',
        'is_active' => 'boolean',
        'users_count' => 'integer',
        'api_enabled' => 'boolean',
        'website_enabled' => 'boolean',
        'grc_enabled' => 'boolean',
        'provisioned_at' => 'datetime',
        'last_health_check' => 'datetime',
    ];

    /**
     * Relation : L'établissement appartient à un propriétaire (owner/user).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Indique si le module GRC est activé pour l'établissement.
     */
    public function hasGrc(): bool
    {
        return (bool) ($this->grc_enabled || in_array('grc', $this->modules ?? [], true));
    }

    /**
     * Port résolu pour le module GRC (défaut : app_port + 2000, fallback 8085 pour dev autonome).
     */
    public function resolvedGrcPort(): int
    {
        return (int) ($this->grc_port ?: ($this->app_port ? $this->app_port + 2000 : 8085));
    }

    /**
     * URL d'accès direct au portail Wetchah_GRC.
     */
    public function grcUrl(): string
    {
        return 'http://localhost:' . $this->resolvedGrcPort();
    }

    /**
     * URL d'accès direct à l'application PMS.
     */
    public function appUrl(): ?string
    {
        return $this->app_port ? 'http://localhost:' . $this->app_port : null;
    }

    /**
     * URL d'accès au site web vitrine.
     */
    public function websiteUrl(): ?string
    {
        $port = $this->web_port ?: ($this->app_port ? $this->app_port + 1000 : null);
        return $port ? 'http://localhost:' . $port : null;
    }
}
