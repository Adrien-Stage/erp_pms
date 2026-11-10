<?php

namespace Demo\Seeders;

use Demo\Registre;

/**
 * Réservations individuelles et de groupe, avec folios, fiches voyageurs et paiements.
 *
 * Tous les montants en base sont en centimes FCFA (* 100 sur les extras), avec
 * TVA à 0 par défaut (sauf activation explicite dans l'établissement) et modes
 * de règlement alignés sur Payment ('cash', 'orange_money', 'mtn_momo', 'stripe',
 * 'bank_transfer').
 *
 * Les 20 séjours sont répartis de façon déterministe autour d'aujourd'hui (J0) :
 *   - 6 séjours terminés (passés) pour nourrir l'historique et la comptabilité ;
 *   - 2 séjours en cours avec départ AUJOURD'HUI (J0) pour tester le check-out
 *     et le relevé mini-bar de départ ;
 *   - 2 séjours en cours avec départ à J+1 / J+2 (dont 1 avec Formule
 *     Demi-Pension) pour l'imputation chambre (restaurant, boutique, mini-bar) ;
 *   - 3 arrivées confirmées AUJOURD'HUI (J0) avec code OTP déterministe
 *     (410201 à 410203), dont 1 sur chambre sale (304) et 1 avec remise partenaire ;
 *   - 1 demande site web en attente à J0 (à confirmer par la réception) ;
 *   - 3 arrivées de groupe à J+1 (DEMO-GRP-0001) ;
 *   - 2 réservations futures (J+5, J+7) et 1 annulée récente.
 */
class BookingSeeder extends AbstractModuleSeeder
{
    /** Préfixe reconnaissable : sert de garde d'idempotence et à la purge. */
    public const PREFIXE = 'DEMO-';

    public const CODE_GROUPE = 'DEMO-GRP-0001';

    public function module(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return 'Réservations, groupe, folios et paiements';
    }

    public function seed(): int
    {
        $clients = $this->ids('customers', 'notes = ?', [CustomerSeeder::MARQUEUR]);
        $chambresRows = $this->pdo->query(
            'SELECT id, number, room_type_id FROM rooms WHERE is_active = true ORDER BY id'
        )->fetchAll(\PDO::FETCH_ASSOC);

        if ($clients === [] || $chambresRows === []) {
            return 0;
        }

        $chambresParNum = [];
        foreach ($chambresRows as $r) {
            $chambresParNum[(string) $r['number']] = [
                'id'           => (int) $r['id'],
                'number'       => (string) $r['number'],
                'room_type_id' => (int) $r['room_type_id'],
            ];
        }

        $operateur = $this->userIdByEmail('aurelie.nkoulou@' . StaffSeeder::DOMAINE) ?? $this->anyUserId();
        $chefRec   = $this->userIdByEmail('patrick.tchinda@' . StaffSeeder::DOMAINE) ?? $operateur;
        $sources   = $this->data['people']['sources'] ?? ['direct', 'phone', 'walk_in', 'email', 'ota_bookingcom', 'website'];
        $methodes  = ['cash', 'orange_money', 'mtn_momo', 'stripe', 'bank_transfer'];
        $formules  = $this->formulesParCode();
        $partenaires = $this->assurerPartenaires();
        $partenaireLittoralId = $partenaires['Groupe Hôtelier Littoral'] ?? null;

        // S'assure que le dossier groupe DEMO-GRP-0001 existe pour les 3 arrivées J+1.
        $groupeId = $this->assurerGroupe($clients[2] ?? $clients[0]);
        $crees = 0;

        $plan = $this->planSejours();
        $vise = min($this->volume['bookings'] ?? 20, count($plan));

        for ($i = 0; $i < $vise; $i++) {
            $numero = self::PREFIXE . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            if ($this->findId('bookings', ['booking_number' => $numero]) !== null) {
                continue;
            }

            $spec   = $plan[$i];
            $client = $this->cycle($clients, $spec['client_idx']);
            $chambre = $this->choisirChambreLibre(
                $chambresParNum,
                $spec['room'],
                $spec['arrivee'],
                $spec['depart'],
                in_array($spec['statut'], ['confirmed', 'checked_in', 'pending'], true)
            );

            if ($chambre === null) {
                continue;
            }

            $prixNuit = $this->prixNuit($chambre['id']);
            $nuits    = $spec['nuits'];
            $adultes  = $spec['adults'];
            $enfants  = $spec['children'];
            $montantHeberg = $prixNuit * $nuits;

            // Formule optionnelle (Demi-Pension sur DEMO-0009)
            $roomPackageId = null;
            $packageAmount = 0;
            $packageLabel  = null;
            $remiseMontant = 0;
            $remiseLibelles = [];

            if (!empty($spec['package_code']) && isset($formules[$spec['package_code']])) {
                $pkg = $formules[$spec['package_code']];
                $roomPackageId = (int) $pkg['id'];
                $occupants = $adultes + $enfants;
                $packageAmount = $pkg['pricing_mode'] === 'per_stay'
                    ? (int) $pkg['price']
                    : (int) $pkg['price'] * $nuits * max(1, $occupants);
                $packageLabel = $pkg['name'];

                if ($pkg['room_discount_type'] === 'percent' && (int) $pkg['room_discount_value'] > 0) {
                    $remisePkg = (int) round($montantHeberg * ((int) $pkg['room_discount_value']) / 100);
                    $remiseMontant += $remisePkg;
                    $remiseLibelles[] = ["Formule — {$pkg['name']}", $remisePkg];
                }
            }

            // Convention partenaire optionnelle (Groupe Hôtelier Littoral sur DEMO-0013)
            $partnerId = null;
            if (!empty($spec['with_partner']) && $partenaireLittoralId !== null) {
                $partnerId = $partenaireLittoralId;
                $remisePart = (int) round($montantHeberg * 0.15);
                $remiseMontant += $remisePart;
                $remiseLibelles[] = ['Remise partenaire — Groupe Hôtelier Littoral', $remisePart];
            }

            $remiseMontant = min($remiseMontant, $montantHeberg);

            // Extras initiaux du folio (en centimes FCFA)
            $extraSpec = $this->extraPourSejour($spec, $i);
            $extrasAmount = $extraSpec !== null ? $extraSpec['amount'] : 0;

            $taxe  = 0;
            $total = max(0, $montantHeberg + $packageAmount + $extrasAmount - $remiseMontant);
            $acompteAttendu = (int) round($total * 0.30);

            $paye = match ($spec['statut']) {
                'completed', 'checked_out' => $total,
                'checked_in'               => (int) round($total * 0.50),
                'confirmed'                => (int) round($total * ($spec['paid_ratio'] ?? 0.30)),
                default                    => 0,
            };
            $reste = max(0, $total - $paye);

            $creeQuand = $this->jours(-$spec['recul'], '10:00:00');
            $actualCheckIn = in_array($spec['statut'], ['checked_in', 'checked_out', 'completed'], true)
                ? $spec['arrivee'] . ' 14:15:00'
                : null;
            $actualCheckOut = in_array($spec['statut'], ['checked_out', 'completed'], true)
                ? $spec['depart'] . ' 11:30:00'
                : null;

            $bookingId = $this->insert('bookings', array_merge([
                'room_id'                 => $chambre['id'],
                'customer_id'             => $client,
                'group_booking_id'        => !empty($spec['in_group']) ? $groupeId : null,
                'partner_organization_id' => $partnerId,
                'room_package_id'         => $roomPackageId,
                'booking_number'          => $numero,
                'status'                  => $spec['statut'],
                'check_in'                => $spec['arrivee'],
                'check_in_time'           => '14:00',
                'check_out'               => $spec['depart'],
                'actual_check_in'         => $actualCheckIn,
                'actual_check_out'        => $actualCheckOut,
                'adults_count'            => $adultes,
                'children_count'          => $enfants,
                'total_nights'            => $nuits,
                'price_per_night'         => $prixNuit,
                'total_room_amount'       => $montantHeberg,
                'extras_amount'           => $extrasAmount,
                'package_amount'          => $packageAmount,
                'tax_amount'              => $taxe,
                'discount_amount'         => $remiseMontant,
                'total_amount'            => $total,
                'deposit_amount'          => $acompteAttendu,
                'paid_amount'             => $paye,
                'balance_due'             => $reste,
                'source'                  => $spec['source'] ?? $this->cycle($sources, $i),
                'checkin_code'            => $spec['otp'],
                'checkin_attempts'        => 0,
                'code_recipient'          => 'customer',
                'created_by'              => $operateur,
                'checked_in_by'           => $actualCheckIn !== null ? $operateur : null,
                'checked_out_by'          => $actualCheckOut !== null ? $chefRec : null,
                'notes'                   => $spec['notes'],
            ], $this->timestamps($creeQuand)));

            $crees++;

            // --- Folio : hébergement ---
            $this->insert('folio_items', array_merge([
                'booking_id'       => $bookingId,
                'customer_id'      => $client,
                'type'             => 'room',
                'description'      => "Hébergement {$nuits} nuit(s) — Chambre {$chambre['number']}",
                'quantity'         => $nuits,
                'unit_price'       => $prixNuit,
                'total_price'      => $montantHeberg,
                'is_complimentary' => false,
                'earns_points'     => true,
                'recorded_by'      => $operateur,
                'occurred_at'      => $spec['arrivee'] . ' 14:00:00',
            ], $this->timestamps($creeQuand)));
            $crees++;

            // --- Folio : formule éventuelle ---
            if ($packageAmount > 0 && $packageLabel !== null) {
                $this->insert('folio_items', array_merge([
                    'booking_id'       => $bookingId,
                    'customer_id'      => $client,
                    'type'             => 'other',
                    'description'      => "Formule — {$packageLabel}",
                    'quantity'         => 1,
                    'unit_price'       => $packageAmount,
                    'total_price'      => $packageAmount,
                    'is_complimentary' => false,
                    'earns_points'     => true,
                    'recorded_by'      => $operateur,
                    'occurred_at'      => $spec['arrivee'] . ' 14:00:00',
                ], $this->timestamps($creeQuand)));
                $crees++;
            }

            // --- Folio : remises éventuelles (en négatif) ---
            foreach ($remiseLibelles as [$labelRemise, $montantRem]) {
                $this->insert('folio_items', array_merge([
                    'booking_id'       => $bookingId,
                    'customer_id'      => $client,
                    'type'             => 'discount',
                    'description'      => $labelRemise,
                    'quantity'         => 1,
                    'unit_price'       => -$montantRem,
                    'total_price'      => -$montantRem,
                    'is_complimentary' => false,
                    'earns_points'     => false,
                    'recorded_by'      => $operateur,
                    'occurred_at'      => $spec['arrivee'] . ' 14:00:00',
                ], $this->timestamps($creeQuand)));
                $crees++;
            }

            // --- Folio : extra en cours / passé ---
            if ($extraSpec !== null) {
                $this->insert('folio_items', array_merge([
                    'booking_id'       => $bookingId,
                    'customer_id'      => $client,
                    'type'             => $extraSpec['type'],
                    'description'      => $extraSpec['label'],
                    'quantity'         => 1,
                    'unit_price'       => $extraSpec['amount'],
                    'total_price'      => $extraSpec['amount'],
                    'is_complimentary' => false,
                    'earns_points'     => true,
                    'recorded_by'      => $operateur,
                    'occurred_at'      => $spec['arrivee'] . ' 20:00:00',
                ], $this->timestamps($creeQuand)));
                $crees++;
            }

            // --- Fiche voyageur (guests) sur les séjours déjà arrivés ---
            if ($actualCheckIn !== null && $this->tableExists('guests')) {
                $crees += $this->creerGuestPrincipal($bookingId, $client, $operateur, $actualCheckIn);
            }

            // --- Paiement ---
            if ($paye > 0) {
                $methode = $this->cycle($methodes, $i);
                $origine = in_array($spec['statut'], ['completed', 'checked_out'], true)
                    ? 'check_out'
                    : (!empty($spec['in_group']) ? 'groupe' : 'acompte_reservation');

                $this->insert('payments', array_merge([
                    'booking_id'              => $bookingId,
                    'customer_id'             => $client,
                    'partner_organization_id' => $partnerId,
                    'amount'                  => $paye,
                    'currency'                => 'XAF',
                    'method'                  => $methode,
                    'status'                  => 'completed',
                    'purpose'                 => 'settlement',
                    'payment_origin'          => $origine,
                    'reference'               => 'PAY-' . $numero,
                    'external_reference'      => $methode === 'cash' ? 'Espèces' : strtoupper($methode) . '-' . substr($numero, -4),
                    'paid_at'                 => $spec['arrivee'] . ' 14:30:00',
                    'processed_by'            => $operateur,
                    'notes'                   => '[demo] Règlement de démonstration',
                ], $this->timestamps($creeQuand)));

                $crees++;
            }
        }

        // Met à jour le bilan du dossier groupe et les statistiques clients.
        $this->synchroniserGroupe($groupeId);
        $this->synchroniserClientsDemo();

        return $crees;
    }

    /**
     * Scénario déterministe des 20 séjours DEMO-0001 à DEMO-0020.
     *
     * @return list<array<string, mixed>>
     */
    private function planSejours(): array
    {
        return [
            // 0..5 : 6 séjours terminés (historique récent)
            ['room' => '101', 'client_idx' => 0,  'arrivee' => $this->date(-28), 'depart' => $this->date(-25), 'nuits' => 3, 'statut' => 'completed',  'adults' => 1, 'children' => 0, 'otp' => '410101', 'recul' => 32, 'notes' => 'Séjour terminé (archive comptable).'],
            ['room' => '102', 'client_idx' => 1,  'arrivee' => $this->date(-22), 'depart' => $this->date(-20), 'nuits' => 2, 'statut' => 'completed',  'adults' => 2, 'children' => 0, 'otp' => '410102', 'recul' => 26, 'notes' => 'Séjour terminé (archive comptable).'],
            ['room' => '103', 'client_idx' => 2,  'arrivee' => $this->date(-17), 'depart' => $this->date(-13), 'nuits' => 4, 'statut' => 'completed',  'adults' => 2, 'children' => 1, 'otp' => '410103', 'recul' => 21, 'notes' => 'Séjour familial terminé.'],
            ['room' => '105', 'client_idx' => 3,  'arrivee' => $this->date(-14), 'depart' => $this->date(-11), 'nuits' => 3, 'statut' => 'completed',  'adults' => 1, 'children' => 0, 'otp' => '410104', 'recul' => 18, 'notes' => 'Voyageur d\'affaires (archive).'],
            ['room' => '203', 'client_idx' => 4,  'arrivee' => $this->date(-10), 'depart' => $this->date(-8),  'nuits' => 2, 'statut' => 'completed',  'adults' => 2, 'children' => 0, 'otp' => '410105', 'recul' => 13, 'notes' => 'Séjour week-end terminé.'],
            ['room' => '106', 'client_idx' => 5,  'arrivee' => $this->date(-7),  'depart' => $this->date(-5),  'nuits' => 2, 'statut' => 'completed',  'adults' => 1, 'children' => 0, 'otp' => '410106', 'recul' => 10, 'notes' => 'Séjour récent soldé.'],

            // 6..7 : 2 séjours en cours avec départ AUJOURD'HUI (J0)
            ['room' => '201', 'client_idx' => 6,  'arrivee' => $this->date(-2),  'depart' => $this->date(0),   'nuits' => 2, 'statut' => 'checked_in', 'adults' => 1, 'children' => 0, 'otp' => '410107', 'recul' => 5,  'notes' => 'Départ prévu aujourd\'hui (check-out simple + solde caisse).'],
            ['room' => '305', 'client_idx' => 7,  'arrivee' => $this->date(-2),  'depart' => $this->date(0),   'nuits' => 2, 'statut' => 'checked_in', 'adults' => 2, 'children' => 0, 'otp' => '410108', 'recul' => 5,  'notes' => 'Départ prévu aujourd\'hui (relevé mini-bar requis avant solde).'],

            // 8..9 : 2 séjours en cours (J-1 -> J+1 / J+2)
            ['room' => '204', 'client_idx' => 8,  'arrivee' => $this->date(-1),  'depart' => $this->date(1),   'nuits' => 2, 'statut' => 'checked_in', 'adults' => 2, 'children' => 0, 'otp' => '410109', 'recul' => 4,  'package_code' => 'DP', 'notes' => 'Séjour en cours avec Formule Demi-Pension (départ J+1).'],
            ['room' => '403', 'client_idx' => 9,  'arrivee' => $this->date(-1),  'depart' => $this->date(2),   'nuits' => 3, 'statut' => 'checked_in', 'adults' => 2, 'children' => 0, 'otp' => '410110', 'recul' => 4,  'notes' => 'Suite Présidentielle occupée (imputations POS & mini-bar).'],

            // 10..12 : 3 arrivées confirmées AUJOURD'HUI (J0)
            ['room' => '304', 'client_idx' => 10, 'arrivee' => $this->date(0),   'depart' => $this->date(2),   'nuits' => 2, 'statut' => 'confirmed',  'adults' => 2, 'children' => 0, 'otp' => '410201', 'recul' => 3,  'paid_ratio' => 0.30, 'notes' => 'Arrivée du jour (chambre 304 à nettoyer / inspecter avant check-in).'],
            ['room' => '102', 'client_idx' => 11, 'arrivee' => $this->date(0),   'depart' => $this->date(3),   'nuits' => 3, 'statut' => 'confirmed',  'adults' => 1, 'children' => 0, 'otp' => '410202', 'recul' => 3,  'paid_ratio' => 0.50, 'notes' => 'Arrivée du jour — chambre prête pour check-in immédiat (OTP 410202).'],
            ['room' => '402', 'client_idx' => 12, 'arrivee' => $this->date(0),   'depart' => $this->date(2),   'nuits' => 2, 'statut' => 'confirmed',  'adults' => 2, 'children' => 0, 'otp' => '410203', 'recul' => 4,  'paid_ratio' => 0.30, 'with_partner' => true, 'notes' => 'Client VIP avec convention Groupe Hôtelier Littoral (-15%).'],

            // 13..15 : 3 arrivées de groupe DEMAIN (J+1 -> J+4) — DEMO-GRP-0001
            ['room' => '106', 'client_idx' => 13, 'arrivee' => $this->date(1),   'depart' => $this->date(4),   'nuits' => 3, 'statut' => 'confirmed',  'adults' => 1, 'children' => 0, 'otp' => '410204', 'recul' => 6,  'paid_ratio' => 0.30, 'in_group' => true, 'source' => 'email', 'notes' => 'Membre 1 du groupe Séminaire Banque Atlantique Douala.'],
            ['room' => '203', 'client_idx' => 14, 'arrivee' => $this->date(1),   'depart' => $this->date(4),   'nuits' => 3, 'statut' => 'confirmed',  'adults' => 1, 'children' => 0, 'otp' => '410205', 'recul' => 6,  'paid_ratio' => 0.30, 'in_group' => true, 'source' => 'email', 'notes' => 'Membre 2 du groupe Séminaire Banque Atlantique Douala.'],
            ['room' => '206', 'client_idx' => 15, 'arrivee' => $this->date(1),   'depart' => $this->date(4),   'nuits' => 3, 'statut' => 'confirmed',  'adults' => 2, 'children' => 0, 'otp' => '410206', 'recul' => 6,  'paid_ratio' => 0.30, 'in_group' => true, 'source' => 'email', 'notes' => 'Membre 3 du groupe Séminaire Banque Atlantique Douala.'],

            // 16 : Demande site web en attente à J0
            ['room' => '401', 'client_idx' => 16, 'arrivee' => $this->date(0),   'depart' => $this->date(2),   'nuits' => 2, 'statut' => 'pending',    'adults' => 2, 'children' => 0, 'otp' => '410207', 'recul' => 1,  'source' => 'website', 'notes' => 'Demande reçue via le site web, en attente d\'encaissement d\'acompte et confirmation.'],

            // 17..18 : 2 réservations à venir
            ['room' => '301', 'client_idx' => 17, 'arrivee' => $this->date(5),   'depart' => $this->date(8),   'nuits' => 3, 'statut' => 'confirmed',  'adults' => 2, 'children' => 1, 'otp' => '410208', 'recul' => 2,  'paid_ratio' => 0.30, 'notes' => 'Réservation confirmée à J+5.'],
            ['room' => '302', 'client_idx' => 18, 'arrivee' => $this->date(7),   'depart' => $this->date(10),  'nuits' => 3, 'statut' => 'confirmed',  'adults' => 1, 'children' => 0, 'otp' => '410209', 'recul' => 2,  'paid_ratio' => 0.30, 'notes' => 'Réservation confirmée à J+7.'],

            // 19 : 1 annulation récente
            ['room' => '105', 'client_idx' => 19, 'arrivee' => $this->date(-4),  'depart' => $this->date(-2),  'nuits' => 2, 'statut' => 'cancelled',  'adults' => 1, 'children' => 0, 'otp' => '410210', 'recul' => 8,  'notes' => 'Réservation annulée par le client avant arrivée.'],
        ];
    }

    /** Extra de démonstration (montant converti en centimes FCFA). */
    private function extraPourSejour(array $spec, int $i): ?array
    {
        if (!in_array($spec['statut'], ['checked_in', 'checked_out', 'completed'], true)) {
            return null;
        }

        $catalogue = [
            ['type' => 'restaurant', 'label' => 'Dîner au restaurant',  'amount' => 12000 * 100],
            ['type' => 'laundry',    'label' => 'Service blanchisserie', 'amount' => 5000 * 100],
            ['type' => 'minibar',    'label' => 'Consommation mini-bar', 'amount' => 3500 * 100],
        ];

        return $this->cycle($catalogue, $i);
    }

    /**
     * Sélectionne la chambre demandée si elle existe et n'a pas de conflit actif ;
     * sinon choisit une autre chambre active non bloquée (hors 205 en maintenance).
     */
    private function choisirChambreLibre(
        array $chambresParNum,
        string $numeroSouhaite,
        string $arrivee,
        string $depart,
        bool $verifierDisponibilite
    ): ?array {
        $candidat = $chambresParNum[$numeroSouhaite] ?? null;

        if ($candidat !== null && (!$verifierDisponibilite || $this->chambreDisponible($candidat['id'], $arrivee, $depart))) {
            return $candidat;
        }

        foreach ($chambresParNum as $num => $chambre) {
            if ($num === '205') {
                continue;
            }
            if (!$verifierDisponibilite || $this->chambreDisponible($chambre['id'], $arrivee, $depart)) {
                return $chambre;
            }
        }

        return reset($chambresParNum) ?: null;
    }

    private function chambreDisponible(int $roomId, string $arrivee, string $depart): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM bookings
             WHERE room_id = ?
               AND status IN ('pending', 'confirmed', 'checked_in')
               AND check_in < ?
               AND check_out > ?
             LIMIT 1"
        );
        $stmt->execute([$roomId, $depart, $arrivee]);

        return $stmt->fetchColumn() === false;
    }

    /** @return array<string, array<string, mixed>> */
    private function formulesParCode(): array
    {
        if (!$this->tableExists('room_packages')) {
            return [];
        }

        $parCode = [];
        foreach ($this->pdo->query('SELECT id, code, name, pricing_mode, price, room_discount_type, room_discount_value FROM room_packages WHERE code IS NOT NULL')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $parCode[(string) $row['code']] = $row;
        }

        return $parCode;
    }

    /**
     * S'assure que les organisations partenaires existent avant de poser DEMO-0013
     * (pour lui rattacher sa convention Groupe Hôtelier Littoral).
     *
     * @return array<string, int> nom => id
     */
    private function assurerPartenaires(): array
    {
        if (!$this->tableExists('partner_organizations')) {
            return [];
        }

        $parNom = [];
        foreach ($this->data['operations']['partner_organizations'] ?? [] as $rang => $p) {
            $parNom[$p['name']] = $this->insertOnce(
                'partner_organizations',
                ['name' => $p['name']],
                array_merge([
                    'code'                        => 'DEMO-P' . str_pad((string) ($rang + 1), 3, '0', STR_PAD_LEFT),
                    'type'                        => $p['type'],
                    'contact_name'                => $p['contact'],
                    'contact_phone'               => $p['phone'],
                    'valid_from'                  => $this->date(-180),
                    'valid_until'                 => $this->date(185),
                    'is_active'                   => true,
                    'has_convention'              => true,
                    'room_discount_type'          => 'percent',
                    'room_discount_value'         => $p['discount'],
                    'restaurant_discount_percent' => max(0, $p['discount'] - 5),
                    'shop_discount_percent'       => max(0, $p['discount'] - 5),
                    'notes'                       => '[demo] Convention de démonstration',
                ], $this->timestamps())
            );
        }

        return $parNom;
    }

    private function assurerGroupe(int $contactCustomerId): ?int
    {
        if (!$this->tableExists('group_bookings')) {
            return null;
        }

        return $this->insertOnce(
            'group_bookings',
            ['group_code' => self::CODE_GROUPE],
            array_merge([
                'contact_customer_id'    => $contactCustomerId,
                'booker_id'              => null,
                'group_name'             => 'Séminaire Banque Atlantique Douala',
                'event_type'             => 'corporate',
                'start_date'             => $this->date(1),
                'end_date'               => $this->date(4),
                'total_deposit_required' => 0,
                'total_deposit_paid'     => 0,
                'status'                 => 'confirmed',
                'rooming_list_sent'      => false,
                'notes'                  => '[demo] Séjour de groupe de démonstration',
            ], $this->timestamps($this->jours(-6)))
        );
    }

    private function synchroniserGroupe(?int $groupeId): void
    {
        if ($groupeId === null || !$this->tableExists('group_bookings')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(deposit_amount), 0) AS req, COALESCE(SUM(paid_amount), 0) AS paid
             FROM bookings WHERE group_booking_id = ?'
        );
        $stmt->execute([$groupeId]);
        $totaux = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($totaux) {
            $this->pdo->prepare(
                'UPDATE group_bookings SET total_deposit_required = ?, total_deposit_paid = ? WHERE id = ?'
            )->execute([(int) $totaux['req'], (int) $totaux['paid'], $groupeId]);
        }
    }

    private function creerGuestPrincipal(int $bookingId, int $customerId, ?int $operateur, string $checkedInAt): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT first_name, last_name, nationality, id_document_type, id_document_number, phone, email
             FROM customers WHERE id = ?'
        );
        $stmt->execute([$customerId]);
        $c = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$c) {
            return 0;
        }

        $this->insert('guests', array_merge([
            'booking_id'         => $bookingId,
            'customer_id'        => $customerId,
            'first_name'         => $c['first_name'],
            'last_name'          => $c['last_name'],
            'nationality'        => $c['nationality'],
            'id_document_type'   => $c['id_document_type'],
            'id_document_number' => $c['id_document_number'],
            'phone'              => $c['phone'],
            'email'              => $c['email'],
            'checked_in_at'      => $checkedInAt,
            'checked_in_by'      => $operateur,
            'is_primary_guest'   => true,
        ], $this->timestamps($checkedInAt)));

        return 1;
    }

    private function synchroniserClientsDemo(): void
    {
        $this->pdo->prepare(
            "UPDATE customers c
             SET total_nights_stayed = COALESCE((
                    SELECT SUM(b.total_nights) FROM bookings b
                    WHERE b.customer_id = c.id AND b.status IN ('checked_in', 'checked_out', 'completed')
                 ), 0),
                 total_spent = COALESCE((
                    SELECT SUM(b.paid_amount) FROM bookings b
                    WHERE b.customer_id = c.id AND b.status IN ('checked_in', 'checked_out', 'completed')
                 ), 0)
             WHERE c.notes = ?"
        )->execute([CustomerSeeder::MARQUEUR]);
    }

    /** Tarif de la catégorie de la chambre, en centimes FCFA. */
    private function prixNuit(int $chambreId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT rt.base_price FROM rooms r
             JOIN room_types rt ON rt.id = r.room_type_id
             WHERE r.id = ?'
        );
        $stmt->execute([$chambreId]);
        $prix = $stmt->fetchColumn();

        return $prix === false ? 2500000 : (int) $prix;
    }
}
