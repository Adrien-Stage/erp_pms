<?php

namespace Demo\Seeders;

use PDO;

/**
 * Socle commun aux seeders de démonstration.
 *
 * Chaque classe fille peuple un module dans la base d'un établissement. Elle
 * écrit en PDO préparé, jamais par concaténation : les libellés viennent de
 * fichiers JSON éditables, et une apostrophe dans « L'Éclat » ne doit pas
 * pouvoir casser une requête.
 *
 * Toutes les aides ci-dessous sont idempotentes par construction : relancer une
 * installation complète les manques sans jamais dupliquer.
 */
abstract class AbstractModuleSeeder
{
    /** Colonnes réellement présentes, par table — mémoïsé par instance. */
    private array $colonnesConnues = [];

    public function __construct(
        protected PDO $pdo,
        protected array $data,
        protected array $volume,
    ) {}

    /**
     * Module qui doit être actif pour que cette étape tourne.
     * null = socle, installé quels que soient les modules.
     */
    abstract public function module(): ?string;

    /** Ce qui s'affiche dans le journal d'installation. */
    abstract public function label(): string;

    /** Insère les données et renvoie le nombre d'enregistrements créés. */
    abstract public function seed(): int;

    // ── Aides d'écriture ─────────────────────────────────────────────────────

    /**
     * Insère une ligne et renvoie son id.
     *
     * Les colonnes absentes du schéma sont écartées silencieusement : un
     * établissement resté sur une version antérieure de wetchah_app n'a pas
     * toutes les colonnes des versions récentes, et l'installation doit y
     * fonctionner quand même plutôt que d'échouer sur une colonne inconnue.
     */
    protected function insert(string $table, array $valeurs): int
    {
        $valeurs = $this->filtrerColonnes($table, $valeurs);

        $colonnes = array_keys($valeurs);
        $trous    = implode(', ', array_fill(0, count($colonnes), '?'));
        $liste    = implode(', ', array_map(fn($c) => '"' . $c . '"', $colonnes));

        $stmt = $this->pdo->prepare(
            "INSERT INTO \"{$table}\" ({$liste}) VALUES ({$trous}) RETURNING id"
        );
        $this->lier($stmt, array_values($valeurs));
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Insère seulement si aucune ligne ne correspond déjà, et renvoie l'id dans
     * les deux cas. C'est ce qui rend l'action rejouable après l'activation
     * d'un nouveau module.
     */
    protected function insertOnce(string $table, array $reperes, array $valeurs): int
    {
        $existant = $this->findId($table, $reperes);

        return $existant ?? $this->insert($table, array_merge($reperes, $valeurs));
    }

    /** Id de la première ligne correspondant aux repères, ou null. */
    protected function findId(string $table, array $reperes): ?int
    {
        $reperes = $this->filtrerColonnes($table, $reperes);

        if ($reperes === []) {
            return null;
        }

        $ou = implode(' AND ', array_map(fn($c) => '"' . $c . '" = ?', array_keys($reperes)));

        $stmt = $this->pdo->prepare("SELECT id FROM \"{$table}\" WHERE {$ou} LIMIT 1");
        $this->lier($stmt, array_values($reperes));
        $stmt->execute();
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** Nombre de lignes d'une table, filtré par une clause facultative. */
    protected function count(string $table, string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM \"{$table}\"" . ($where !== '' ? " WHERE {$where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Ids d'une table, dans l'ordre d'insertion. */
    protected function ids(string $table, string $where = '', array $params = [], int $limit = 500): array
    {
        $sql = "SELECT id FROM \"{$table}\"" . ($where !== '' ? " WHERE {$where}" : '') . " ORDER BY id LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** La table existe-t-elle dans cet établissement ? */
    protected function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?"
        );
        $stmt->execute([$table]);

        return $stmt->fetchColumn() !== false;
    }

    /** Un id d'utilisateur pour les colonnes de traçabilité, ou null. */
    protected function anyUserId(): ?int
    {
        $id = $this->pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    // ── Aides de composition ─────────────────────────────────────────────────

    /** Un élément du tableau, choisi de façon cyclique et donc reproductible. */
    protected function cycle(array $liste, int $index)
    {
        return $liste[$index % count($liste)];
    }

    /** Horodatage à N jours du présent, au format attendu par PostgreSQL. */
    protected function jours(int $decalage, string $heure = '12:00:00'): string
    {
        return date('Y-m-d', strtotime("{$decalage} days")) . ' ' . $heure;
    }

    protected function date(int $decalage): string
    {
        return date('Y-m-d', strtotime("{$decalage} days"));
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** Horodatages de création, ajoutés à presque toutes les insertions. */
    protected function timestamps(?string $quand = null): array
    {
        $quand ??= $this->now();

        return ['created_at' => $quand, 'updated_at' => $quand];
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * Lie les paramètres en conservant leur type.
     *
     * Passer un tableau à execute() transmet tout en chaîne : `false` devient
     * '', que PostgreSQL refuse pour une colonne booléenne, et `null` perdrait
     * sa nullité. Le typage explicite est donc obligatoire ici, contrairement à
     * MySQL qui aurait laissé passer.
     */
    private function lier(\PDOStatement $stmt, array $valeurs): void
    {
        foreach ($valeurs as $rang => $valeur) {
            $type = match (true) {
                is_bool($valeur) => PDO::PARAM_BOOL,
                is_int($valeur)  => PDO::PARAM_INT,
                is_null($valeur) => PDO::PARAM_NULL,
                default          => PDO::PARAM_STR,
            };

            $stmt->bindValue($rang + 1, $valeur, $type);
        }
    }

    /** Écarte les colonnes que cette base ne connaît pas. */
    private function filtrerColonnes(string $table, array $valeurs): array
    {
        return array_intersect_key($valeurs, array_flip($this->colonnes($table)));
    }

    /** @return array<int, string> */
    private function colonnes(string $table): array
    {
        if (!isset($this->colonnesConnues[$table])) {
            $stmt = $this->pdo->prepare(
                'SELECT column_name FROM information_schema.columns
                 WHERE table_schema = \'public\' AND table_name = ?'
            );
            $stmt->execute([$table]);
            $this->colonnesConnues[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return $this->colonnesConnues[$table];
    }
}
