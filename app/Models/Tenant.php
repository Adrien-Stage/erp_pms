<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant représente un établissement de l'ONG.
 * 
 * Architecture : Un tenant = un établissement physique (Villa Boutanga, 
 * futurs établissements). Toutes les données sont isolées par tenant_id.
 * 
 * @property string $name Nom de l'établissement
 * @property string $slug Identifiant unique URL-friendly
 * @property array $settings Configuration JSON par établissement
 * @property string $currency Devise par défaut (XAF/FCFA pour le Cameroun)
 */

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'phone',
        'email',
        'settings',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array', // PostgreSQL JSON column
        'is_active' => 'boolean',
    ];

    /**
     * Les utilisateurs de cet établissement
     * Relation : Un tenant a plusieurs users
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Les chambres de cet établissement
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Les réservations de cet établissement
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Les clients de cet établissement
     * Note : Un client peut être partagé entre tenants en V2, 
     * mais pour l'MVP, isolation stricte.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
