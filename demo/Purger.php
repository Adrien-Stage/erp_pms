<?php

namespace Demo;

use Demo\Seeders\BookingSeeder;
use Demo\Seeders\CustomerSeeder;
use PDO;

/**
 * Retrait du jeu de démonstration d'un établissement.
 *
 * Deux principes gouvernent cette classe, parce qu'elle supprime dans une base
 * en service :
 *
 * 1. **Ne toucher qu'au marqué.** Chaque enregistrement fictif porte un
 *    marqueur posé à l'installation (`[demo] …` en note, préfixe `DEMO-` sur
 *    un numéro). Rien d'autre n'est éligible.
 *
 * 2. **Ne jamais casser une donnée réelle.** Le catalogue — chambres, carte,
 *    articles, fournisseurs — n'est retiré que s'il n'est plus référencé. Une
 *    chambre créée par la démonstration mais qui porte depuis une vraie
 *    réservation reste en place : l'établissement s'en sert.
 *
 * Le second point est celui qui compte. Les tables transactionnelles sont
 * faciles à reconnaître ; le catalogue, lui, se confond avec l'exploitation dès
 * qu'on s'en sert. Supprimer une chambre occupée pour « faire propre » coûterait
 * bien plus cher que de laisser trois chambres de démonstration.
 */
class Purger
{
    /** @var array<int, string> Ce qui a été conservé, et pourquoi. */
    private array $conserves = [];

    public function __construct(private PDO $pdo) {}

    /**
     * @param  callable  $log  fn(string $etape, string $message, string $niveau)
     * @return array{total:int, kept:array<int, string>}
     */
    public function purge(callable $log): array
    {
        $supprimes = 0;

        $supprimes += $this->purgerVentes($log);
        $supprimes += $this->purgerCommandesRestaurant($log);
        $supprimes += $this->purgerReservations($log);
        $supprimes += $this->purgerMenage($log);
        $supprimes += $this->purgerCharges($log);
        $supprimes += $this->purgerClients($log);
        $supprimes += $this->purgerCatalogue($log);

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

    private function purgerCommandesRestaurant(callable $log): int
    {
        if (!$this->table('restaurant_customer_orders')) {
            return 0;
        }

        $ids = $this->ids(
            'SELECT id FROM restaurant_customer_orders WHERE notes = ?',
            ['[demo] Commande de démonstration']
        );

        if ($ids === []) {
            return 0;
        }

        $n = $this->supprimerPar('restaurant_customer_order_items', 'restaurant_customer_order_id', $ids)
            + $this->supprimerPar('restaurant_customer_orders', 'id', $ids);

        $log('purge', "· Commandes restaurant : {$n} ligne(s).", 'info');

        return $n;
    }

    /**
     * Réservations et tout ce qui leur pend au nez.
     *
     * L'ordre importe : un check-out a pu produire une facture et des points de
     * fidélité, qui référencent la réservation. Les supprimer après elle est
     * impossible — la contrainte de clé étrangère refuse.
     */
    private function purgerReservations(callable $log): int
    {
        $ids = $this->ids("SELECT id FROM bookings WHERE booking_number LIKE ?", [BookingSeeder::PREFIXE . '%']);

        if ($ids === []) {
            return 0;
        }

        $n = 0;

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

        foreach (['payments', 'folio_items', 'guests', 'loyalty_transactions', 'restaurant_notes'] as $table) {
            $n += $this->supprimerPar($table, 'booking_id', $ids);
        }

        // Une vente ou une commande réelle a pu être imputée à une réservation
        // de démonstration : on la détache plutôt que de la supprimer, elle
        // appartient à l'exploitation.
        foreach (['shop_orders', 'restaurant_customer_orders'] as $table) {
            $this->detacher($table, 'booking_id', $ids);
        }

        $n += $this->supprimerPar('bookings', 'id', $ids);

        $log('purge', "· Réservations, folios, paiements et factures : {$n} ligne(s).", 'info');

        return $n;
    }

    private function purgerMenage(callable $log): int
    {
        if (!$this->table('housekeeping_assignments')) {
            return 0;
        }

        $n = $this->executer(
            'DELETE FROM housekeeping_assignments WHERE notes = ?',
            ['[demo] Affectation de démonstration']
        );

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

        $n = $this->executer('DELETE FROM expenses WHERE notes = ?', ['[demo] Charge de démonstration']);

        if ($n > 0) {
            $log('purge', "· Charges : {$n} ligne(s).", 'info');
        }

        return $n;
    }

    /**
     * Clients fictifs — seulement ceux dont plus rien ne dépend.
     *
     * Un client de démonstration à qui la réception a fini par attribuer une
     * vraie réservation n'est plus fictif : le supprimer emporterait la
     * réservation avec lui.
     */
    private function purgerClients(callable $log): int
    {
        $ids = $this->ids('SELECT id FROM customers WHERE notes = ?', [CustomerSeeder::MARQUEUR]);

        if ($ids === []) {
            return 0;
        }

        $dependances = [
            'bookings'       => ['customer_id', 'booker_id'],
            'booking_drafts' => ['customer_id', 'booker_id'],
            'folio_items'    => ['customer_id'],
            'invoices'       => ['customer_id'],
            'payments'       => ['customer_id'],
            'guests'         => ['customer_id'],
            'shop_orders'    => ['customer_id'],
            'group_bookings' => ['contact_customer_id', 'booker_id'],
            'loyalty_transactions' => ['customer_id'],
            'restaurant_notes'     => ['customer_id'],
        ];

        $libres = array_values(array_filter(
            $ids,
            fn(int $id) => !$this->estReference($id, $dependances)
        ));

        $retenus = count($ids) - count($libres);

        if ($retenus > 0) {
            $this->conserves[] = "{$retenus} client(s) de démonstration conservé(s) : des données réelles y sont rattachées.";
        }

        if ($libres === []) {
            return 0;
        }

        $n = $this->supprimerPar('customers', 'id', $libres);
        $log('purge', "· Clients : {$n} ligne(s).", 'info');

        return $n;
    }

    // ── Catalogue ────────────────────────────────────────────────────────────

    /**
     * Le catalogue posé par la démonstration, uniquement s'il ne sert plus.
     *
     * Chaque entrée est testée contre ce qui la référence. On ne supprime que
     * ce qui est devenu inerte.
     */
    private function purgerCatalogue(callable $log): int
    {
        $regles = [
            // table, condition d'identification, paramètres, dépendances
            ['shop_products', 'sku LIKE ?', ['DEMO-%'], ['shop_order_items' => ['shop_product_id']]],
            ['stock_items', 'reference LIKE ?', ['DEMO-S%'], [
                'purchase_order_lines'    => ['stock_item_id'],
                'stock_movements'         => ['stock_item_id'],
                'stock_requisition_lines' => ['stock_item_id'],
                'room_cost_items'         => ['stock_item_id'],
            ]],
            ['partner_organizations', 'code LIKE ?', ['DEMO-P%'], [
                'bookings'  => ['partner_organization_id'],
                'customers' => ['partner_organization_id'],
            ]],
        ];

        $n = 0;

        foreach ($regles as [$table, $condition, $params, $dependances]) {
            if (!$this->table($table)) {
                continue;
            }

            $ids = $this->ids("SELECT id FROM {$table} WHERE {$condition}", $params);

            if ($ids === []) {
                continue;
            }

            $libres = array_values(array_filter(
                $ids,
                fn(int $id) => !$this->estReference($id, $dependances)
            ));

            $retenus = count($ids) - count($libres);

            if ($retenus > 0) {
                $this->conserves[] = "{$retenus} entrée(s) de « {$table} » conservée(s) : encore utilisée(s).";
            }

            if ($libres !== []) {
                $n += $this->supprimerPar($table, 'id', $libres);
            }
        }

        if ($n > 0) {
            $log('purge', "· Catalogue devenu inutilisé : {$n} ligne(s).", 'info');
        }

        // Chambres, catégories, carte, fournisseurs et équipes ne portent aucun
        // marqueur : rien ne les distingue de ceux que l'établissement aurait
        // créés lui-même. Ils restent — c'est le socle sur lequel il travaille.
        $this->conserves[] = 'Chambres, carte, rayons, fournisseurs et équipes conservés : indiscernables de ceux créés par l\'établissement.';

        return $n;
    }

    // ── Aides ────────────────────────────────────────────────────────────────

    /** Une valeur est-elle référencée par l'une des dépendances listées ? */
    private function estReference(int $id, array $dependances): bool
    {
        foreach ($dependances as $table => $colonnes) {
            if (!$this->table($table)) {
                continue;
            }

            foreach ($colonnes as $colonne) {
                if (!$this->colonne($table, $colonne)) {
                    continue;
                }

                $stmt = $this->pdo->prepare("SELECT 1 FROM \"{$table}\" WHERE \"{$colonne}\" = ? LIMIT 1");
                $stmt->execute([$id]);

                if ($stmt->fetchColumn() !== false) {
                    return true;
                }
            }
        }

        return false;
    }

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

    /** Détache sans supprimer : la ligne appartient à l'exploitation. */
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
        static $connues = [];

        if (!array_key_exists($table, $connues)) {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name = ?"
            );
            $stmt->execute([$table]);
            $connues[$table] = $stmt->fetchColumn() !== false;
        }

        return $connues[$table];
    }

    private function colonne(string $table, string $colonne): bool
    {
        static $connues = [];
        $cle = $table . '.' . $colonne;

        if (!array_key_exists($cle, $connues)) {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_schema='public' AND table_name = ? AND column_name = ?"
            );
            $stmt->execute([$table, $colonne]);
            $connues[$cle] = $stmt->fetchColumn() !== false;
        }

        return $connues[$cle];
    }
}
