<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intervention de l'administrateur d'un établissement dans son exploitation,
 * transmise par l'établissement lui-même.
 */
class TenantIntervention extends Model
{
    protected $fillable = [
        'tenant_id', 'reference', 'administrateur', 'email', 'motif', 'perimetres',
        'debut', 'fin_prevue', 'fin_reelle', 'cloture', 'actions', 'tardive',
    ];

    protected $casts = [
        'perimetres' => 'array',
        'debut'      => 'datetime',
        'fin_prevue' => 'datetime',
        'fin_reelle' => 'datetime',
        'tardive'    => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function enCours(): bool
    {
        return $this->fin_reelle === null && $this->fin_prevue->isFuture();
    }
}
