<?php

namespace Demo;

use Demo\Seeders\BookingSeeder;
use Demo\Seeders\OperationsSeeder;
use Demo\Seeders\RestaurantSeeder;
use Demo\Seeders\StaffSeeder;
use PDO;

/**
 * Retrait complet du jeu de démonstration d'un établissement.
 *
 * Ordre de purge :
 *  1. Le transactionnel marqué (ventes DEMO-V, commandes & banquets, DIT-DEMO,
 *     DM/DA/BC-DEMO, réservations DEMO- et groupe DEMO-GRP, ménage, charges,
 *     discussions du jeu).
 *  2. Le personnel @demo.wetchah.test (désactivé si cité par des données réelles).
 *  3. Le registre `demo_records` à rebours de l'installation (lignes enfants
 *     d'abord : lignes de mini-bar, articles mini-bar, recettes, garde-manger,
 *     dépôts, fiches coûts, budget, tarifs, stock, catalogue, clients, chambres…).
 */
class Purger
{
    /** @var array<int, string> Ce qui a été conservé, et pourquoi. */
    private array $conserves = [];

    /** @var array<string, bool> */
    private array $tablesConnues = [];

    /** @var array<string, bool> */
    private array $colonnesConnues = [];

    /** @var array<string, list<array{0: string, 1: string}>> */
    private array $citations = [];

    public function __construct(private PDO $pdo, private array $donnees = []) {}

    /**
     * @param  callable  $log  fn(string $etape, string $message, string $niveau)
     * @return array{total:int, kept:array<int, string>}
     */
    public function purge(callable $log): array
    {
        $supprimes = 0;

        // Une installation antérieure au registre y est inscrite d'abord.
        Registre::preparer($this->pdo, $this->donnees);

        $supprimes += $this->purgerVentes($log);
        $supprimes += $this->purgerCommandesEtBanquets($log);
        $supprimes += $this->purgerMaintenance($log);
        $supprimes += $this->purgerApprovisionnements($log);
        $supprimes += $this->purgerReservations($log);
        $supprimes += $this->purgerMenage($log);
        $supprimes += $this->purgerCharges($log);
        $supprimes += $this->purgerDiscussions($log);
        $supprimes += $this->purgerPersonnel($log);
        $supprimes += $this->purgerRegistre($log);

        $this->nettoyerParametresMinibar();

        return ['total' => $supprimes, 'kept' => $this->conserves];
    }

    // ── Transactionnel ───────────────────────────────────────────────────────

    private function purgerVentes(callable $log): int
    {
        if (!$this->table('shop_orders')) {
            return 0;
        }

        $ids = $this->ids("SELECT id FROM shop_orders WHERE order_number LIKE 'DEMO-V%'");
        if ($ids === []) {
            return 0;
        }

        $n = $this->supprimerPar('shop_order_items', 'shop_order_id', $ids)
            + $this->supprimerPar('shop_orders', 'id', $ids);

        $log('purge', "· Ventes boutique : {$n} ligne(s).", 'info');

        return $n;
    }

    private function purgerCommandesEtBanquets(callable $log): int
    {
        $n = 0;

        if ($this->table('restaurant_customer_orders')) {
            $ids = $this->ids(
                'SELECT id FROM restaurant_customer_orders WHERE notes = ?',
                [RestaurantSeeder::REPERE_COMMANDE]
            );

            if ($ids !== []) {
                $n += $this->supprimerPar('restaurant_customer_order_items', 'restaurant_customer_order_id', $ids);
                $n += $this->supprimerPar('restaurant_customer_orders', 'id', $ids);
            }
        }

        if ($this->table('restaurant_banquets')) {
            $bIds = $this->ids("SELECT id FROM restaurant_banquets WHERE reference LIKE 'DEMO-BQ-%'");

            if ($bIds !== []) {
                $n += $this->supprimerPar('restaurant_banquet_payments', 'restaurant_banquet_id', $bIds);
                $n += $this->supprimerPar('restaurant_banquets', 'id', $bIds);
            }
        }

        if ($this->table('restaurant_bar_bottles')) {
            $botIds = $this->ids(
                "SELECT r.id FROM restaurant_bar_bottles r
                 JOIN " . Registre::TABLE . " reg ON reg.table_name = 'restaurant_bar_bottles' AND reg.record_id = r.id"
            );
            if ($botIds !== []) {
                $n += $this->supprimerPar('restaurant_bar_bottles', 'id', $botIds);
            }
        }

        if ($this->table('service_store_movements')) {
            $mvtIds = $this->ids(
                "SELECT m.id FROM service_store_movements m
                 JOIN " . Registre::TABLE . " reg ON reg.table_name = 'service_store_movements' AND reg.record_id = m.id"
            );
            if ($mvtIds !== []) {
                $n += $this->supprimerPar('service_store_movements', 'id', $mvtIds);
            }
        }

        if ($n > 0) {
            $log('purge', "· Restaurant & banquets : {$n} ligne(s).", 'info');
        }

        return $n;
    }

    private function purgerMaintenance(callable $log): int
    {
        if (!$this->table('maintenance_requests')) {
            return 0;
        }

        $ids = $this->ids("SELECT id FROM maintenance_requests WHERE reference LIKE 'DIT-DEMO-%'");
        if ($ids === []) {
            return 0;
        }

        $n = $this->supprimerPar('maintenance_request_events', 'maintenance_request_id', $ids)
            + $this->supprimerPar('maintenance_requests', 'id', $ids);

        $log('purge', "· Interventions techniques : {$n} ligne(s).", 'info');

        return $n;
    }

    private function purgerApprovisionnements(callable $log): int
    {
        $n = 0;

        if ($this->table('purchase_orders')) {
            $ids = $this->ids("SELECT id FROM purchase_orders WHERE number LIKE 'BC-DEMO-%'");
            if ($ids !== []) {
                $n += $this->supprimerPar('purchase_order_lines', 'purchase_order_id', $ids);
                $n += $this->supprimerPar('purchase_orders', 'id', $ids);
            }
        }

        if ($this->table('purchase_requests')) {
            $ids = $this->ids("SELECT id FROM purchase_requests WHERE number LIKE 'DA-DEMO-%'");
            if ($ids !== []) {
                $n += $this->supprimerPar('purchase_request_lines', 'purchase_request_id', $ids);
                $n += $this->supprimerPar('purchase_requests', 'id', $ids);
            }
        }

        if ($this->table('stock_requisitions')) {
            $ids = $this->ids("SELECT id FROM stock_requisitions WHERE number LIKE 'DM-DEMO-%'");
            if ($ids !== []) {
                $n += $this->supprimerPar('stock_requisition_lines', 'stock_requisition_id', $ids);
                $n += $this->supprimerPar('stock_requisitions', 'id', $ids);
            }
        }

        if ($n > 0) {
            $log('purge', "· Demandes et bons d'achat : {$n} ligne(s).", 'info');
        }

        return $n;
    }

    private function purgerReservations(callable $log): int
    {
        $ids = $this->ids('SELECT id FROM bookings WHERE booking_number LIKE ?', [BookingSeeder::PREFIXE . '%']);
        $n   = 0;

        if ($ids !== []) {
            $chambresOccupees = $this->ids(
                "SELECT DISTINCT room_id FROM bookings WHERE status = 'checked_in' AND id IN (" . $this->trous($ids) . ')',
                $ids
            );

            // Factures : leurs lignes d'abord.
            if ($this->table('invoices')) {
                $factures = $this->ids(
                    'SELECT id FROM invoices WHERE booking_id IN (' . $this->trous($ids) . ')',
                    $ids
                );

                if ($factures !== []) {
                    $n += $this->supprimerPar('invoice_items', 'invoice_id', $factures);
                    $n += $this->supprimerPar('invoices', 'id', $factures);
                }
            }

            if ($this->table('minibar_checks')) {
                $checks = $this->ids(
                    'SELECT id FROM minibar_checks WHERE booking_id IN (' . $this->trous($ids) . ')',
                    $ids
                );
                if ($checks !== []) {
                    $n += $this->supprimerPar('minibar_check_lines', 'minibar_check_id', $checks);
                    $n += $this->supprimerPar('minibar_checks', 'id', $checks);
                }
            }

            foreach (['payments', 'folio_items', 'guests', 'loyalty_transactions', 'restaurant_notes', 'minibar_movements', 'minibar_restocks'] as $table) {
                $n += $this->supprimerPar($table, 'booking_id', $ids);
            }

            if ($this->table('rooms') && $this->colonne('rooms', 'minibar_due_booking_id')) {
                $this->detacher('rooms', 'minibar_due_booking_id', $ids);
            }

            // Ventes / commandes / banquets réels éventuellement imputés à une réservation démo.
            foreach (['shop_orders', 'restaurant_customer_orders', 'restaurant_banquets'] as $table) {
                $this->detacher($table, 'booking_id', $ids);
            }

            $n += $this->supprimerPar('bookings', 'id', $ids);

            // Libère les chambres réelles restées au statut « occupied » uniquement pour un séjour démo.
            if ($chambresOccupees !== [] && $this->table('rooms')) {
                $this->executer(
                    "UPDATE rooms SET status = 'available'
                     WHERE id IN (" . $this->trous($chambresOccupees) . ")
                       AND status = 'occupied'
                       AND NOT EXISTS (SELECT 1 FROM bookings WHERE bookings.room_id = rooms.id AND bookings.status = 'checked_in')",
                    $chambresOccupees
                );
            }
        }

        if ($this->table('group_bookings')) {
            $gIds = $this->ids('SELECT id FROM group_bookings WHERE group_code LIKE ?', [BookingSeeder::PREFIXE . 'GRP-%']);
            if ($gIds !== []) {
                $this->detacher('bookings', 'group_booking_id', $gIds);
                $n += $this->supprimerPar('group_bookings', 'id', $gIds);
            }
        }

        if ($n > 0) {
            $log('purge', "· Réservations, groupe, folios, paiements et factures : {$n} ligne(s).", 'info');
        }

        return $n;
    }

    private function purgerMenage(callable $log): int
    {
        if (!$this->table('housekeeping_assignments')) {
            return 0;
        }

        $ids = $this->ids('SELECT id FROM housekeeping_assignments WHERE notes = ?', [OperationsSeeder::REPERE_MENAGE]);
        if ($ids === []) {
            return 0;
        }

        $this->detacher('maintenance_requests', 'housekeeping_assignment_id', $ids);
        $this->detacher('minibar_checks', 'housekeeping_assignment_id', $ids);

        $n = $this->supprimerPar('housekeeping_assignments', 'id', $ids);
        if ($n > 0) {
            $log('purge', "· Affectations de ménage : {$n} ligne(s).", 'info');
        }

        return $n;
    }

    private function purgerCharges(callable $log): int
    {
        if (!$this->table('expenses')) {
            return 0;
        }

        $n = $this->executer('DELETE FROM expenses WHERE notes = ?', [OperationsSeeder::REPERE_CHARGE]);
        if ($n > 0) {
            $log('purge', "· Charges : {$n} ligne(s).", 'info');
        }

        return $n;
    }

    private function purgerDiscussions(callable $log): int
    {
        if (!Registre::existe($this->pdo) || !$this->table('discussion_conversations')) {
            return 0;
        }

        $convIds = $this->ids(
            "SELECT record_id FROM " . Registre::TABLE . " WHERE table_name = 'discussion_conversations'"
        );
        if ($convIds === []) {
            return 0;
        }

        $n = $this->supprimerPar('discussion_messages', 'conversation_id', $convIds)
            + $this->supprimerPar('discussion_conversation_user', 'discussion_conversation_id', $convIds)
            + $this->supprimerPar('discussion_conversations', 'id', $convIds);

        $this->executer(
            'DELETE FROM ' . Registre::TABLE . " WHERE table_name IN ('discussion_conversations', 'discussion_conversation_user', 'discussion_messages')"
        );

        if ($n > 0) {
            $log('purge', "· Discussions internes : {$n} ligne(s).", 'info');
        }

        return $n;
    }

    // ── Registre ─────────────────────────────────────────────────────────────

    private const LIBELLES = [
        'room_types'                  => 'catégories de chambres',
        'rooms'                       => 'chambres',
        'service_items'               => 'prestations annexes',
        'room_packages'               => 'formules tarifaires',
        'customers'                   => 'clients',
        'partner_organizations'       => 'partenaires',
        'shop_categories'             => 'rayons',
        'shop_products'               => 'articles boutique',
        'restaurant_menu_categories'  => 'catégories de la carte',
        'restaurant_menu_items'       => 'plats',
        'restaurant_bar_drinks'       => 'fiches boissons bar',
        'restaurant_bar_bottles'      => 'bouteilles ouvertes au bar',
        'restaurant_pantry_categories'=> 'catégories garde-manger',
        'restaurant_pantry_items'     => 'articles garde-manger',
        'restaurant_recipes'          => 'fiches recettes',
        'restaurant_recipe_lines'     => 'lignes recettes',
        'housekeeping_teams'          => 'équipes de ménage',
        'shift_weeks'                 => 'semaines de planning',
        'shift_assignments'           => 'quarts de travail',
        'suppliers'                   => 'fournisseurs',
        'stock_categories'            => 'familles de stock',
        'stock_units'                 => 'unités de stock',
        'stock_items'                 => 'articles de stock',
        'stock_item_supplier'         => 'tarifs fournisseurs',
        'storage_zones'               => 'zones de rangement',
        'service_stores'              => 'dépôts de service',
        'service_store_stocks'        => 'stocks des dépôts',
        'minibar_articles'            => 'articles mini-bar',
        'minibar_standards'           => 'dotations mini-bar',
        'room_minibar_lines'          => 'lignes mini-bar chambres',
        'room_cost_sheets'            => 'fiches coûts chambres',
        'room_cost_items'             => 'postes coûts chambres',
        'budget_lines'                => 'lignes budgétaires',
        'role_user'                   => 'rôles du personnel',
        'housekeeping_team_user'      => 'membres des équipes',
        'point_of_sale_user'          => 'rattachements au restaurant',
        'technician_specialties'      => 'spécialités techniques',
    ];

    private function purgerRegistre(callable $log): int
    {
        if (!Registre::existe($this->pdo)) {
            return 0;
        }

        $lignes  = $this->pdo->query('SELECT id, table_name, record_id FROM ' . Registre::TABLE . ' ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $retires = [];

        do {
            $restantes = [];
            $gardes    = [];
            $avance    = false;

            foreach ($lignes as $ligne) {
                $table = $ligne['table_name'];
                $id    = (int) $ligne['record_id'];

                if ($table === 'users') {
                    continue;
                }

                if (!$this->table($table) || $this->ids("SELECT id FROM \"{$table}\" WHERE id = ?", [$id]) === []) {
                    $this->desinscrire((int) $ligne['id']);
                    continue;
                }

                if ($this->estCitee($table, $id) || !$this->supprimerLigne($table, $id)) {
                    $gardes[$table] = ($gardes[$table] ?? 0) + 1;
                    $restantes[]    = $ligne;
                    continue;
                }

                $this->desinscrire((int) $ligne['id']);
                $retires[$table] = ($retires[$table] ?? 0) + 1;
                $avance          = true;
            }

            $lignes = $restantes;
        } while ($avance && $lignes !== []);

        if ($retires !== []) {
            $log('purge', '· Catalogue, clients et rattachements : ' . $this->detail($retires) . '.', 'info');
        }

        if ($gardes !== []) {
            $this->conserves[] = 'Conservé parce que des données réelles le citent encore : ' . $this->detail($gardes) . '.';
        }

        return array_sum($retires);
    }

    private function nettoyerParametresMinibar(): void
    {
        if (!$this->table('tenants') || !$this->table('service_stores')) {
            return;
        }

        $row = $this->pdo->query('SELECT id, settings FROM tenants ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['settings'])) {
            return;
        }

        $settings = json_decode((string) $row['settings'], true);
        $depotId  = $settings['minibar']['depot_id'] ?? null;
        if (!$depotId) {
            return;
        }

        if ($this->ids('SELECT id FROM service_stores WHERE id = ?', [(int) $depotId]) === []) {
            unset($settings['minibar']['depot_id']);
            if ($settings['minibar'] === []) {
                unset($settings['minibar']);
            }
            $this->executer(
                'UPDATE tenants SET settings = ? WHERE id = ?',
                [json_encode($settings, JSON_UNESCAPED_UNICODE), (int) $row['id']]
            );
        }
    }

    private function estCitee(string $table, int $id): bool
    {
        foreach ($this->citationsDe($table) as [$enfant, $colonne]) {
            if ($this->ids("SELECT 1 FROM \"{$enfant}\" WHERE \"{$colonne}\" = ? LIMIT 1", [$id]) !== []) {
                return true;
            }
        }

        if ($table === 'stock_units') {
            $nom = $this->pdo->prepare('SELECT name FROM stock_units WHERE id = ?');
            $nom->execute([$id]);
            $nom = $nom->fetchColumn();

            foreach ([['stock_items', 'unit'], ['stock_items', 'purchase_unit']] as [$t, $c]) {
                if ($this->colonne($t, $c) && $this->ids("SELECT 1 FROM \"{$t}\" WHERE \"{$c}\" = ? LIMIT 1", [$nom]) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<array{0: string, 1: string}> */
    private function citationsDe(string $table): array
    {
        return $this->citations[$table] ??= array_map(
            fn (array $r) => [$r[0], $r[1]],
            $this->pdo->query(
                "SELECT enfant.relname, att.attname
                 FROM pg_constraint c
                 JOIN pg_class enfant ON enfant.oid = c.conrelid
                 JOIN pg_class parent ON parent.oid = c.confrelid
                 JOIN pg_namespace ns ON ns.oid = parent.relnamespace AND ns.nspname = 'public'
                 JOIN pg_attribute att ON att.attrelid = c.conrelid AND att.attnum = c.conkey[1]
                 WHERE c.contype = 'f' AND array_length(c.conkey, 1) = 1
                   AND parent.relname = " . $this->pdo->quote($table)
            )->fetchAll(PDO::FETCH_NUM)
        );
    }

    private function supprimerLigne(string $table, int $id): bool
    {
        $this->pdo->exec('SAVEPOINT ligne_demo');

        try {
            $this->executer("DELETE FROM \"{$table}\" WHERE id = ?", [$id]);
            $this->pdo->exec('RELEASE SAVEPOINT ligne_demo');

            return true;
        } catch (\PDOException) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT ligne_demo');

            return false;
        }
    }

    private function desinscrire(int $entree): void
    {
        $this->executer('DELETE FROM ' . Registre::TABLE . ' WHERE id = ?', [$entree]);
    }

    private function detail(array $parTable): string
    {
        return implode(', ', array_map(
            fn (string $table, int $n) => $n . ' ' . (self::LIBELLES[$table] ?? $table),
            array_keys($parTable),
            $parTable
        ));
    }

    // ── Personnel ────────────────────────────────────────────────────────────

    private function purgerPersonnel(callable $log): int
    {
        if (!$this->table('users')) {
            return 0;
        }

        $ids       = $this->ids('SELECT id FROM users WHERE lower(email) LIKE ?', ['%@' . StaffSeeder::DOMAINE]);
        $supprimes = 0;
        $fermes    = 0;

        if ($ids !== [] && $this->table('sessions') && $this->colonne('sessions', 'user_id')) {
            $this->executer('DELETE FROM sessions WHERE user_id IN (' . $this->trous($ids) . ')', $ids);
        }

        foreach ($ids as $id) {
            if ($this->supprimerLigne('users', $id)) {
                $supprimes++;

                if (Registre::existe($this->pdo)) {
                    $this->executer('DELETE FROM ' . Registre::TABLE . " WHERE table_name = 'users' AND record_id = ?", [$id]);
                }

                continue;
            }

            $this->executer(
                'UPDATE users SET is_active = false, password = ?, remember_token = NULL WHERE id = ?',
                [password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT), $id]
            );
            $fermes++;
        }

        if ($supprimes > 0) {
            $log('purge', "· Personnel de démonstration : {$supprimes} compte(s).", 'info');
        }

        if ($fermes > 0) {
            $this->conserves[] = "{$fermes} compte(s) du personnel de démonstration désactivé(s), sans mot de passe utilisable : des données réelles les citent.";
        }

        return $supprimes;
    }

    // ── Aides ────────────────────────────────────────────────────────────────

    private function supprimerPar(string $table, string $colonne, array $ids): int
    {
        if ($ids === [] || !$this->table($table) || !$this->colonne($table, $colonne)) {
            return 0;
        }

        return $this->executer(
            "DELETE FROM \"{$table}\" WHERE \"{$colonne}\" IN (" . $this->trous($ids) . ')',
            $ids
        );
    }

    private function detacher(string $table, string $colonne, array $ids): void
    {
        if ($ids === [] || !$this->table($table) || !$this->colonne($table, $colonne)) {
            return;
        }

        $this->executer(
            "UPDATE \"{$table}\" SET \"{$colonne}\" = NULL WHERE \"{$colonne}\" IN (" . $this->trous($ids) . ')',
            $ids
        );
    }

    private function executer(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /** @return array<int, int> */
    private function ids(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function trous(array $valeurs): string
    {
        return implode(', ', array_fill(0, count($valeurs), '?'));
    }

    private function table(string $table): bool
    {
        if (!array_key_exists($table, $this->tablesConnues)) {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name = ?"
            );
            $stmt->execute([$table]);
            $this->tablesConnues[$table] = $stmt->fetchColumn() !== false;
        }

        return $this->tablesConnues[$table];
    }

    private function colonne(string $table, string $colonne): bool
    {
        $cle = $table . '.' . $colonne;

        if (!array_key_exists($cle, $this->colonnesConnues)) {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_schema='public' AND table_name = ? AND column_name = ?"
            );
            $stmt->execute([$table, $colonne]);
            $this->colonnesConnues[$cle] = $stmt->fetchColumn() !== false;
        }

        return $this->colonnesConnues[$cle];
    }
}
