<?php
// app/Models/Traits/BelongsToTenant.php

namespace App\Models\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait BelongsToTenant
 * 
 * Implémente l'architecture multitenant pour tous les modèles métier.
 * 
 * PRINCIPE : 
 * - Toute entité métier appartient à un tenant (établissement)
 * - Les requêtes sont automatiquement filtrées par le tenant de l'utilisateur connecté
 * - Empêche les fuites de données entre établissements
 * 
 * UTILISATION : Ajouter `use BelongsToTenant;` dans chaque modèle métier
 */
trait BelongsToTenant
{
    /**
     * Boot du trait : applique le global scope automatiquement
     */
    public static function bootBelongsToTenant(): void
    {
        // Scope global : filtre automatiquement par tenant_id
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Vérifie si on a un utilisateur authentifié avec un tenant
            if (Auth::check() && Auth::user()->tenant_id) {
                $builder->where('tenant_id', Auth::user()->tenant_id);
            }
            // Note : Si pas d'auth (migrations, commandes), pas de filtre
        });

        // Auto-assignation du tenant_id à la création
        static::creating(function (Model $model) {
            if (Auth::check() && empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    /**
     * Relation : Ce modèle appartient à un tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope local : Permet de forcer un tenant spécifique (pour l'admin multi-sites)
     * Utilisé par les admins qui peuvent voir tous les tenants
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')
                     ->where('tenant_id', $tenantId);
    }
}