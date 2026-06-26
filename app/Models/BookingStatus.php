<?php
// app/Enums/BookingStatus.php

namespace App\Enums;

/**
 * Enum BookingStatus
 * 
 * Workflow complet d'une réservation (CDC section 4.4)
 * 
 * PENDING → CONFIRMED → CHECKED_IN → CHECKED_OUT → COMPLETED
 *    ↓          ↓           ↓            ↓
 * CANCELLED  NO_SHOW   EARLY_DEP    DISPUTED
 */
enum BookingStatus: string
{
    case PENDING = 'pending';           // En attente de confirmation/acompte
    case CONFIRMED = 'confirmed';       // Confirmée, acompte reçu
    case CHECKED_IN = 'checked_in';     // Client arrivé, en séjour
    case CHECKED_OUT = 'checked_out';   // Client parti, facture à finaliser
    case COMPLETED = 'completed';       // Séjour terminé, paiement finalisé
    
    // Statuts exceptionnels
    case CANCELLED = 'cancelled';       // Annulée par client ou staff
    case NO_SHOW = 'no_show';           // Client non présenté
    case EARLY_DEPARTURE = 'early_departure'; // Départ anticipé
    case DISPUTED = 'disputed';         // Litige en cours

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmée',
            self::CHECKED_IN => 'En séjour',
            self::CHECKED_OUT => 'Départ effectué',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
            self::NO_SHOW => 'No-show',
            self::EARLY_DEPARTURE => 'Départ anticipé',
            self::DISPUTED => 'Litige',
        };
    }

    /**
     * Couleur pour le UI (Tailwind classes)
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'bg-yellow-100 text-yellow-800',
            self::CONFIRMED => 'bg-blue-100 text-blue-800',
            self::CHECKED_IN => 'bg-green-100 text-green-800',
            self::CHECKED_OUT => 'bg-purple-100 text-purple-800',
            self::COMPLETED => 'bg-gray-100 text-gray-800',
            self::CANCELLED => 'bg-red-100 text-red-800',
            self::NO_SHOW => 'bg-orange-100 text-orange-800',
            self::EARLY_DEPARTURE => 'bg-pink-100 text-pink-800',
            self::DISPUTED => 'bg-red-200 text-red-900',
        };
    }

    /**
     * Vérifie si le statut permet la modification
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED]);
    }

    /**
     * Vérification de transition valide (sécurité métier)
     */
    public function canTransitionTo(self $newStatus): bool
    {
        $allowed = [
            self::PENDING->value => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED->value => [self::CHECKED_IN, self::CANCELLED, self::NO_SHOW],
            self::CHECKED_IN->value => [self::CHECKED_OUT, self::EARLY_DEPARTURE],
            self::CHECKED_OUT->value => [self::COMPLETED, self::DISPUTED],
            self::DISPUTED->value => [self::COMPLETED],
        ];

        return in_array($newStatus, $allowed[$this->value] ?? []);
    }
}