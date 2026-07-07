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
        'docker_status', // running, stopped, creating, error
        'docker_image_tag', // digest (sha256:...) de l'image ghcr.io figé pour ce tenant
        'app_port',
        'db_port',

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
        'provisioned_at',
        'last_health_check',
    ];

    protected $casts = [
        'settings' => 'array',
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
