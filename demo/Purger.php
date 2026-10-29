<?php

namespace Demo;

use Demo\Seeders\BookingSeeder;
use Demo\Seeders\StaffSeeder;
use PDO;

/**
 * Retrait du jeu de démonstration d'un établissement.
 *
 * La purge retire tout ce que le jeu a créé, et rien d'autre :
 *
 * 1. **Le transactionnel marqué**, et ce qui en dérive. Réservations
 *    (`DEMO-`), ventes (`DEMO-V`), commandes, affectations et charges
 *    (`[demo] …`) partent avec leurs folios, paiements, factures et lignes,
 *    même si l'application les a produits après coup.
 *
 * 2. **Les comptes du personnel**, reconnus à leur domaine, avec leurs rôles,
 *    équipes et spécialités.
 *
 * 3. **Tout le reste, d'après le registre** (voir Demo\Registre) : chambres,
 *    catégories, carte, rayons, articles, fournisseurs, familles, unités,
 *    équipes, partenaires, clients et rattachements. Parcouru à rebours de
 *    l'installation : les enfants partent avant leurs parents.
 *
 * Une seule exception, qui protège l'exploitation : une ligne du jeu que des
 * données réelles citent encore reste en place — une chambre de démonstration
 * qui porte depuis une vraie réservation, par exemple. Elle reste inscrite au
 * registre : une purge suivante la retirera quand plus rien ne la citera.
 */
class Purger
{
    /** @var array<int, string> Ce qui a été conservé, et pourquoi. */
    private array $conserves = [];

    /** @var array<string, bool> */
    private array $tablesConnues = [];

    /** @var array<string, bool> */
    private array $colonnesConnues = [];

    /** @var array<string, list<array{0: string, 1: string}>> table => [table qui la cite, colonne] */
    private array $citations = [];

    /** @param  array<string, array>  $donnees  le contenu de /demo/data, pour les installations antérieures au registre */
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
        $supprimes += $this->purgerCommandesRestaurant($log);
        $supprimes += $this->purgerReservations($log);
        $supprimes += $this->purgerMenage($log);
        $supprimes += $this->purgerCharges($log);
        // Les comptes avant le registre : leurs rôles, équipes et spécialités
        // partent avec eux, ce qui libère les équipes qu'ils occupaient.
        $supprimes += $this->purgerPersonnel($log);
        $supprimes += $this->purgerRegistre($log);

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

    // ── Registre ─────────────────────────────────────────────────────────────

    /** Libellés des tables du registre, pour le journal et ce qui est conservé. */
    private const LIBELLES = [
        'room_types' => 'catégories de chambres', 'rooms' => 'chambres', 'customers' => 'clients',
        'partner_organizations' => 'partenaires', 'shop_categories' => 'rayons', 'shop_products' => 'articles boutique',
        'restaurant_menu_categories' => 'catégories de la carte', 'restaurant_menu_items' => 'plats',
        'housekeeping_teams' => 'équipes de ménage', 'suppliers' => 'fournisseurs', 'stock_categories' => 'familles de stock',
        'stock_units' => 'unités de stock', 'stock_items' => 'articles de stock', 'role_user' => 'rôles du personnel',
        'housekeeping_team_user' => 'membres des équipes', 'point_of_sale_user' => 'rattachements au restaurant',
        'technician_specialties' => 'spécialités techniques',
    ];

    /**
     * Tout ce que le registre inscrit, à rebours de l'installation.
     *
     * Les comptes sont l'affaire de purgerPersonnel, passé avant, qui sait
     * désactiver celui qu'on ne peut pas supprimer.
     */
    private function purgerRegistre(callable $log): int
    {
        if (!Registre::existe($this->pdo)) {
            return 0;
        }

        $lignes  = $this->pdo->query('SELECT id, table_name, record_id FROM ' . Registre::TABLE . ' ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $retires = [];
        $gardes  = [];

        foreach ($lignes as $ligne) {
            $table = $ligne['table_name'];
            $id    = (int) $ligne['record_id'];

            if ($table === 'users') {
                continue;
            }

            // Déjà partie (avec son parent, ou effacée depuis) : l'inscription aussi.
            if (!$this->table($table) || $this->ids("SELECT id FROM \"{$table}\" WHERE id = ?", [$id]) === []) {
                $this->desinscrire((int) $ligne['id']);
                continue;
            }

            if ($this->estCitee($table, $id) || !$this->supprimerLigne($table, $id)) {
                $gardes[$table] = ($gardes[$table] ?? 0) + 1;
                continue;
            }

            $this->desinscrire((int) $ligne['id']);
            $retires[$table] = ($retires[$table] ?? 0) + 1;
        }

        if ($retires !== []) {
            $log('purge', '· Catalogue, clients et rattachements : ' . $this->detail($retires) . '.', 'info');
        }

        if ($gardes !== []) {
            $this->conserves[] = 'Conservé parce que des données réelles le citent encore : ' . $this->detail($gardes) . '.';
        }

        return array_sum($retires);
    }

    /**
     * Une ligne réelle cite-t-elle celle-ci ? Toutes les clés étrangères de la
     * base sont lues : une table ajoutée par une version future de
     * l'application est prise en compte sans toucher à ce code.
     */
    private function estCitee(string $table, int $id): bool
    {
        foreach ($this->citationsDe($table) as [$enfant, $colonne]) {
            if ($this->ids("SELECT 1 FROM \"{$enfant}\" WHERE \"{$colonne}\" = ? LIMIT 1", [$id]) !== []) {
                return true;
            }
        }

        // Une unité est citée par son nom, sans clé étrangère.
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

    /** Supprime une ligne ; false si une contrainte s'y oppose. */
    private function supprimerLigne(string $table, int $id): bool
    {
        // Un point de reprise par ligne : sous PostgreSQL, une contrainte
        // violée annule toute la transaction si on ne le pose pas.
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

    /** « 12 chambres, 3 plats » */
    private function detail(array $parTable): string
    {
        return implode(', ', array_map(
            fn (string $table, int $n) => $n . ' ' . (self::LIBELLES[$table] ?? $table),
            array_keys($parTable),
            $parTable
        ));
    }

    // ── Personnel ────────────────────────────────────────────────────────────

    /**
     * Les comptes du personnel de démonstration, reconnus à leur domaine.
     *
     * Ce qui n'appartient qu'au compte (sessions, notifications, préférences)
     * part avec lui. Un compte que des données d'exploitation citent — une
     * caisse, une écriture — ne peut pas disparaître sans les casser : il est
     * alors désactivé, et son mot de passe remplacé par un tirage que personne
     * ne connaît. Il ne s'ouvre plus, et l'historique reste lisible.
     */
    private function purgerPersonnel(callable $log): int
    {
        if (!$this->table('users')) {
            return 0;
        }

        $ids = $this->ids('SELECT id FROM users WHERE lower(email) LIKE ?', ['%@' . StaffSeeder::DOMAINE]);
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
