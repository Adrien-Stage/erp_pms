<?php

namespace Demo\Seeders;

/**
 * Réservations, avec leur folio et leurs paiements.
 *
 * Le cœur du jeu : c'est là que les relations comptent. Chaque réservation
 * pointe vers un client et une chambre réellement créés, porte au moins une
 * ligne de folio pour l'hébergement, et un paiement quand elle est encaissée.
 *
 * Les séjours sont étalés autour d'aujourd'hui — passés, en cours, à venir —
 * pour que le tableau de bord, le planning et les arrivées du jour aient tous
 * quelque chose à montrer. Un jeu entièrement passé laisserait l'écran d'accueil
 * vide, ce qui est précisément l'inverse du but recherché.
 */
class BookingSeeder extends AbstractModuleSeeder
{
    /** Préfixe reconnaissable : sert de garde d'idempotence. */
    public const PREFIXE = 'DEMO-';

    public function module(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return 'Réservations, folios et paiements';
    }

    public function seed(): int
    {
        $clients  = $this->ids('customers', 'notes = ?', [CustomerSeeder::MARQUEUR]);
        $chambres = $this->ids('rooms');

        if ($clients === [] || $chambres === []) {
            return 0;
        }

        $vise      = $this->volume['bookings'] ?? 20;
        $operateur = $this->anyUserId();
        $sources   = $this->data['people']['sources'];
        $crees     = 0;

        for ($i = 0; $i < $vise; $i++) {
            $numero = self::PREFIXE . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            if ($this->findId('bookings', ['booking_number' => $numero]) !== null) {
                continue;
            }

            $sejour = $this->sejour($i);
            $client = $this->cycle($clients, $i);
            $chambre = $this->cycle($chambres, $i);

            $prixNuit = $this->prixNuit($chambre);
            $montant  = $prixNuit * $sejour['nuits'];
            $taxe     = (int) round($montant * 0.1925);
            $total    = $montant + $taxe;

            $paye = in_array($sejour['statut'], ['completed', 'checked_out'], true)
                ? $total
                : (in_array($sejour['statut'], ['checked_in', 'confirmed'], true) ? (int) round($total * 0.5) : 0);

            $bookingId = $this->insert('bookings', array_merge([
                'room_id'           => $chambre,
                'customer_id'       => $client,
                'booking_number'    => $numero,
                'status'            => $sejour['statut'],
                'check_in'          => $sejour['arrivee'],
                'check_out'         => $sejour['depart'],
                'adults_count'      => 1 + ($i % 3),
                'children_count'    => $i % 4 === 0 ? 1 : 0,
                'total_nights'      => $sejour['nuits'],
                'price_per_night'   => $prixNuit,
                'total_room_amount' => $montant,
                'tax_amount'        => $taxe,
                'total_amount'      => $total,
                'paid_amount'       => $paye,
                'balance_due'       => $total - $paye,
                'deposit_amount'    => (int) round($total * 0.3),
                'source'            => $this->cycle($sources, $i),
                'checkin_code'      => str_pad((string) (($i * 51787) % 1000000), 6, '0', STR_PAD_LEFT),
                'code_recipient'    => 'customer',
                'created_by'        => $operateur,
                'notes'             => 'Réservation de démonstration.',
            ], $this->timestamps($this->jours(-$sejour['recul']))));

            $crees++;

            // --- Folio : l'hébergement, puis quelques extras ---
            $this->insert('folio_items', array_merge([
                'booking_id'       => $bookingId,
                'customer_id'      => $client,
                'type'             => 'room',
                'description'      => "Hébergement — {$sejour['nuits']} nuit(s)",
                'quantity'         => $sejour['nuits'],
                'unit_price'       => $prixNuit,
                'total_price'      => $montant,
                'is_complimentary' => false,
                'earns_points'     => true,
                'recorded_by'      => $operateur,
                'occurred_at'      => $sejour['arrivee'] . ' 14:00:00',
            ], $this->timestamps()));

            $crees += $this->extras($bookingId, $client, $operateur, $sejour, $i);

            // --- Paiement, uniquement si de l'argent est réellement entré ---
            if ($paye > 0) {
                $this->insert('payments', array_merge([
                    'booking_id'   => $bookingId,
                    'customer_id'  => $client,
                    'amount'       => $paye,
                    'currency'     => 'XAF',
                    'method'       => $this->cycle(['cash', 'mobile_money', 'card', 'transfer'], $i),
                    'status'       => 'completed',
                    'reference'    => 'PAY-' . $numero,
                    'paid_at'      => $sejour['arrivee'] . ' 14:30:00',
                    'processed_by' => $operateur,
                ], $this->timestamps()));

                $crees++;
            }
        }

        return $crees;
    }

    /**
     * Quelques consommations en cours de séjour, sur les dossiers déjà entamés.
     * Une réservation à venir n'a rien consommé : lui inventer un dîner
     * fausserait le folio et le chiffre d'affaires du jour.
     */
    private function extras(int $bookingId, int $client, ?int $operateur, array $sejour, int $i): int
    {
        if (!in_array($sejour['statut'], ['checked_in', 'checked_out', 'completed'], true)) {
            return 0;
        }

        $catalogue = [
            ['restaurant', 'Dîner au restaurant', 12000],
            ['minibar',    'Consommation minibar', 3500],
            ['laundry',    'Service blanchisserie', 5000],
        ];

        $extra = $this->cycle($catalogue, $i);

        $this->insert('folio_items', array_merge([
            'booking_id'       => $bookingId,
            'customer_id'      => $client,
            'type'             => $extra[0],
            'description'      => $extra[1],
            'quantity'         => 1,
            'unit_price'       => $extra[2],
            'total_price'      => $extra[2],
            'is_complimentary' => false,
            'earns_points'     => true,
            'recorded_by'      => $operateur,
            'occurred_at'      => $sejour['arrivee'] . ' 20:00:00',
        ], $this->timestamps()));

        return 1;
    }

    /** Répartit les séjours autour d'aujourd'hui, avec le statut cohérent. */
    private function sejour(int $i): array
    {
        $nuits = 1 + ($i % 5);

        // Un tiers passé, un tiers en cours ou imminent, un tiers à venir.
        $decalage = match (true) {
            $i % 3 === 0 => -(20 + $i * 2),   // terminé
            $i % 3 === 1 => -($i % 3),        // en cours ou arrivant
            default      => 3 + $i,           // à venir
        };

        $statut = match (true) {
            $decalage <= -10 => 'completed',
            $decalage < 0    => 'checked_in',
            $decalage === 0  => 'checked_in',
            default          => $i % 7 === 0 ? 'pending' : 'confirmed',
        };

        return [
            'arrivee' => $this->date($decalage),
            'depart'  => $this->date($decalage + $nuits),
            'nuits'   => $nuits,
            'statut'  => $statut,
            'recul'   => max(1, abs($decalage) + 5),
        ];
    }

    /** Le tarif de la catégorie de la chambre, pour rester cohérent. */
    private function prixNuit(int $chambreId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT rt.base_price FROM rooms r
             JOIN room_types rt ON rt.id = r.room_type_id
             WHERE r.id = ?'
        );
        $stmt->execute([$chambreId]);
        $prix = $stmt->fetchColumn();

        return $prix === false ? 25000 : (int) $prix;
    }
}
