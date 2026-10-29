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
        'currency',
        
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
        'compose_base_path', // dossier des docker-compose de cet établissement (vide : TENANTS_BASE_PATH)

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

    /** Jamais dans une sérialisation : il ne s'affiche qu'à l'écran de démonstration. */
    protected $hidden = ['demo_password'];

    protected $casts = [
        // Mot de passe des comptes de démonstration : chiffré au repos.
        'demo_password' => 'encrypted',
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
     * Fichier docker-compose de l'établissement : la source de vérité de ce
     * que ses conteneurs ont reçu — images, clés et secrets de service.
     */
    public function composePath(): string
    {
        return $this->baseDir() . '/.compose/' . $this->slug . '.yml';
    }

    /** Dossier choisi à la création, sinon TENANTS_BASE_PATH. */
    public function baseDir(): string
    {
        return rtrim($this->compose_base_path ?: config('provisioning.tenants_base_path'), '/\\');
    }

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
        return \App\Support\PublicUrl::forPort($this->resolvedGrcPort());
    }

    /**
     * URL d'accès direct à l'application PMS.
     */
    public function appUrl(): ?string
    {
        return \App\Support\PublicUrl::forPort($this->app_port);
    }

    /**
     * URL d'accès au site web vitrine.
     */
    public function websiteUrl(): ?string
    {
        $port = $this->web_port ?: ($this->app_port ? $this->app_port + 1000 : null);
        return \App\Support\PublicUrl::forPort($port);
    }
}
