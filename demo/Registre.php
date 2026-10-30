<?php

namespace Demo;

use Demo\Seeders\BookingSeeder;
use Demo\Seeders\CustomerSeeder;
use Demo\Seeders\StaffSeeder;
use PDO;

/**
 * Registre des lignes que le jeu de démonstration a créées dans un
 * établissement : une ligne par enregistrement inséré, table et id.
 *
 * C'est ce qui permet à la purge de tout retirer, et rien d'autre. Une
 * chambre, un plat ou un fournisseur ne porte aucun marqueur : sans registre,
 * rien ne distinguerait la chambre 101 du jeu de celle que l'hôtel aurait
 * créée. Et ce que le jeu a seulement retrouvé (insertOnce sur une ligne
 * existante) n'y figure pas : la purge n'y touche pas.
 *
 * Le registre vit dans la base de l'établissement, écrit dans la même
 * transaction que les données : une installation annulée n'y laisse rien, et
 * une sauvegarde restaurée le garde aligné sur ce qu'elle contient.
 */
final class Registre
{
    public const TABLE = 'demo_records';

    /** Familles de stock d'avant octobre 2026, remplacées depuis par les 21 catégories. */
    private const ANCIENNES_FAMILLES = [
        "Produits d'entretien", 'Linge de maison', 'Accueil chambre', 'Consommables cuisine', 'Fournitures bureau',
    ];

    /** Fournisseurs des anciennes données, retirés du jeu : le linge, et un marché qui n'est pas un fournisseur. */
    private const ANCIENS_FOURNISSEURS = ['Linge & Textile SARL', 'Marché Central Douala'];

    /**
     * Unité que le jeu ajoute à la liste de l'application, qui ne l'a pas par
     * défaut. Bidon et flacon en font désormais partie : ils restent.
     */
    private const UNITES_DU_JEU = ['plateau', 'Plateau'];

    /**
     * Crée le registre s'il n'existe pas.
     *
     * Un établissement installé avant le registre a déjà des données de
     * démonstration : elles y sont inscrites à cette occasion, reconnues à leur
     * marqueur ou, pour le catalogue, à leur nom dans le jeu.
     */
    public static function preparer(PDO $pdo, array $donnees): void
    {
        if (self::existe($pdo)) {
            return;
        }

        $pdo->exec('CREATE TABLE ' . self::TABLE . ' (
            id BIGSERIAL PRIMARY KEY,
            table_name VARCHAR(80) NOT NULL,
            record_id BIGINT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT NOW(),
            UNIQUE (table_name, record_id)
        )');

        if (self::installationAnterieure($pdo)) {
            self::inscrireLesAnciennes($pdo, $donnees);
        }
    }

    public static function existe(PDO $pdo): bool
    {
        return self::table($pdo, self::TABLE);
    }

    public static function noter(PDO $pdo, string $table, int $id): void
    {
        $pdo->prepare('INSERT INTO ' . self::TABLE . ' (table_name, record_id) VALUES (?, ?) ON CONFLICT DO NOTHING')
            ->execute([$table, $id]);
    }

    /** Cette ligne a-t-elle été créée par le jeu ? */
    public static function contient(PDO $pdo, string $table, int $id): bool
    {
        if (!self::existe($pdo)) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM ' . self::TABLE . ' WHERE table_name = ? AND record_id = ?');
        $stmt->execute([$table, $id]);

        return $stmt->fetchColumn() !== false;
    }

    /** Le jeu a-t-il des lignes dans cet établissement ? */
    public static function nonVide(PDO $pdo): bool
    {
        return self::existe($pdo)
            && $pdo->query('SELECT 1 FROM ' . self::TABLE . ' LIMIT 1')->fetchColumn() !== false;
    }

    /** Une installation antérieure au registre a laissé ses marqueurs. */
    private static function installationAnterieure(PDO $pdo): bool
    {
        $sondes = [
            ['bookings', 'booking_number LIKE ?', BookingSeeder::PREFIXE . '%'],
            ['customers', 'notes = ?', CustomerSeeder::MARQUEUR],
            ['stock_items', 'reference LIKE ?', 'DEMO-S%'],
            ['users', 'lower(email) LIKE ?', '%@' . StaffSeeder::DOMAINE],
        ];

        foreach ($sondes as [$table, $condition, $valeur]) {
            if (self::table($pdo, $table) && self::ids($pdo, "SELECT id FROM {$table} WHERE {$condition} LIMIT 1", [$valeur]) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inscrit ce qu'une installation antérieure a créé, parents d'abord : la
     * purge parcourt le registre à rebours, les enfants partent avant eux.
     *
     * Les lignes transactionnelles (réservations, ventes, commandes,
     * affectations, charges) n'y figurent pas : leur marqueur suffit, et la
     * purge les retire avec ce qui en dérive.
     */
    private static function inscrireLesAnciennes(PDO $pdo, array $d): void
    {
        $noms = fn (array $liste, string $cle = 'name') => array_values(array_unique(array_column($liste, $cle)));

        $regles = [
            ['room_types', 'code', $noms($d['hotel']['room_types'] ?? [], 'code')],
            ['rooms', 'number', array_map('strval', $noms($d['hotel']['rooms'] ?? [], 'number'))],
            ['customers', 'notes', [CustomerSeeder::MARQUEUR]],
            ['partner_organizations', 'code LIKE', 'DEMO-P%'],
            ['shop_categories', 'name', $noms($d['shop']['categories'] ?? [])],
            ['shop_products', 'sku LIKE', 'DEMO-%'],
            ['restaurant_menu_categories', 'name', $noms($d['restaurant']['categories'] ?? [])],
            ['restaurant_menu_items', 'name', $noms($d['restaurant']['items'] ?? [])],
            ['housekeeping_teams', 'name', $noms($d['operations']['housekeeping_teams'] ?? [])],
            ['suppliers', 'name', array_merge($noms($d['operations']['suppliers'] ?? []), self::ANCIENS_FOURNISSEURS)],
            ['stock_categories', 'name', array_merge($noms($d['operations']['stock_categories'] ?? []), self::ANCIENNES_FAMILLES)],
            ['stock_units', 'name', self::UNITES_DU_JEU],
            // Le « Magasin central » est posé par l'application, pas par le jeu.
            ['storage_zones', 'name', array_values(array_diff($noms($d['operations']['storage_zones'] ?? []), ['Magasin central']))],
            ['stock_items', 'reference LIKE', 'DEMO-S%'],
            ['users', 'lower(email) LIKE', '%@' . StaffSeeder::DOMAINE],
        ];

        foreach ($regles as [$table, $colonne, $valeurs]) {
            if (!self::table($pdo, $table) || $valeurs === []) {
                continue;
            }

            [$condition, $params] = str_ends_with($colonne, ' LIKE')
                ? ["{$colonne} ?", [$valeurs]]
                : ["\"{$colonne}\"::text IN (" . implode(', ', array_fill(0, count($valeurs), '?')) . ')', $valeurs];

            foreach (self::ids($pdo, "SELECT id FROM {$table} WHERE {$condition} ORDER BY id", $params) as $id) {
                self::noter($pdo, $table, $id);
            }

            // Les tarifs des articles du jeu suivent leurs articles : sans
            // eux, ils les retiendraient à la purge.
            if ($table === 'stock_items' && self::table($pdo, 'stock_item_supplier')) {
                $tarifs = self::ids($pdo,
                    'SELECT t.id FROM stock_item_supplier t JOIN ' . self::TABLE . " r ON r.table_name = 'stock_items' AND r.record_id = t.stock_item_id ORDER BY t.id",
                    []);

                foreach ($tarifs as $id) {
                    self::noter($pdo, 'stock_item_supplier', $id);
                }
            }
        }
    }

    private static function table(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?");
        $stmt->execute([$table]);

        return $stmt->fetchColumn() !== false;
    }

    /** @return list<int> */
    private static function ids(PDO $pdo, string $sql, array $params): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
