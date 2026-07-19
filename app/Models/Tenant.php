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
        'docker_status', // running, stopped, creating, error
        'docker_image_tag', // digest (sha256:...) de l'image ghcr.io figé pour ce tenant
        'web_image_tag', // digest de l'image wetchah_site figé pour ce tenant (module website)
        'app_port',
        'db_port',
        'web_port',

        // Modules & Features
        'api_enabled',
        'website_enabled',
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
}
