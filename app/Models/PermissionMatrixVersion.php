<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une version de la couche de droits que la console pose sur un
 * établissement : l'état complet de ses écarts, qui l'a posé, quand, pourquoi.
 */
class PermissionMatrixVersion extends Model
{
    public const ETAT_INITIAL = 'etat_initial';

    public const MODIFICATION = 'modification';

    public const RETOUR = 'retour';

    protected $fillable = [
        'tenant_id', 'numero', 'nature', 'ecarts', 'motif', 'derogation', 'cumuls',
        'user_id', 'auteur', 'restauree_depuis',
    ];

    protected $casts = [
        'ecarts'     => 'array',
        'cumuls'     => 'array',
        'derogation' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Enregistre la version suivante de la couche de cet établissement. */
    public static function consigner(Tenant $tenant, array $attributs): self
    {
        $numero = (int) static::where('tenant_id', $tenant->id)->max('numero') + 1;

        return static::create($attributs + ['tenant_id' => $tenant->id, 'numero' => $numero]);
    }

    /**
     * Écarts d'une couche, rangés pour comparer deux versions sans dépendre
     * de l'ordre d'envoi.
     *
     * @return array<string, array<string, mixed>>
     */
    public function parCase(): array
    {
        $cases = [];
        foreach ($this->ecarts ?? [] as $ecart) {
            $cases[($ecart['role'] ?? '?') . '|' . ($ecart['permission'] ?? '?')] = $ecart;
        }
        ksort($cases);

        return $cases;
    }
}
