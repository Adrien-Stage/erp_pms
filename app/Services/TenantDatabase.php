<?php

namespace App\Services;

use App\Models\Tenant;
use PDO;
use PDOException;

/**
 * Accès à la base applicative d'un établissement.
 *
 * Chaque établissement a sa propre base dans son propre conteneur : il n'y a
 * pas de copie des employés côté ERP. Modifier un utilisateur depuis l'ERP
 * revient donc à écrire dans la base que wetchah_app lit — la « synchronisation »
 * est immédiate parce qu'il n'y a qu'une seule donnée.
 */
class TenantDatabase
{
    /**
     * Connexion PDO à la base d'un établissement.
     *
     * Passe par le nom du conteneur DB sur le réseau Docker partagé — et non
     * par l'alias générique « db », qui résoudrait vers la base de l'ERP
     * lui-même. Repli sur le port hôte mappé quand l'ERP tourne hors conteneur.
     */
    public function connect(Tenant $tenant): PDO
    {
        $safeDbName  = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
        $dbUser      = $tenant->db_username ?? 'pms';
        $dbPass      = $tenant->db_password ?? 'secret';
        $dbContainer = $tenant->docker_db_container ?: ('meka-erp-' . $tenant->slug . '-db');

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ];

        try {
            return new PDO("pgsql:host={$dbContainer};port=5432;dbname={$safeDbName}", $dbUser, $dbPass, $options);
        } catch (PDOException $e) {
            return new PDO("pgsql:host=127.0.0.1;port={$tenant->db_port};dbname={$safeDbName}", $dbUser, $dbPass, $options);
        }
    }

    /** Employés de l'établissement, avec leurs rôles et niveaux d'accès. */
    public function users(Tenant $tenant): array
    {
        $pdo = $this->connect($tenant);

        $users = $pdo->query('SELECT id, name, email, phone, role, is_active FROM users ORDER BY name')
            ->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            return [];
        }

        // Rôles du pivot, rattachés en une requête plutôt qu'une par employé.
        $pivot = [];
        try {
            $rows = $pdo->query('
                SELECT ru.user_id, r.slug, r.name, r.module, ru.level
                FROM role_user ru
                JOIN roles r ON r.id = ru.role_id
                ORDER BY r.sort_order, r.name
            ')->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $pivot[$row['user_id']][] = $row;
            }
        } catch (PDOException $e) {
            // Établissement sur une version antérieure au multi-rôles : on
            // retombe sur la colonne « role », toujours présente.
        }

        return array_map(function (array $user) use ($pivot) {
            $user['roles'] = $pivot[$user['id']] ?? [];

            return (object) $user;
        }, $users);
    }

    /** Un employé précis, ou null s'il n'existe pas dans cet établissement. */
    public function findUser(Tenant $tenant, int $userId): ?object
    {
        $stmt = $this->connect($tenant)->prepare(
            'SELECT id, name, email, phone, role, is_active FROM users WHERE id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (object) $row : null;
    }

    /**
     * Rôles assignables de l'établissement, groupés par module.
     * Vide si l'établissement n'a pas encore la table des rôles.
     *
     * @return array<int, object>
     */
    public function assignableRoles(Tenant $tenant): array
    {
        try {
            $rows = $this->connect($tenant)->query('
                SELECT id, name, slug, description, module, icon
                FROM roles
                WHERE is_assignable = true
                ORDER BY sort_order, name
            ')->fetchAll(PDO::FETCH_ASSOC);

            return array_map(fn ($r) => (object) $r, $rows);
        } catch (PDOException $e) {
            return [];
        }
    }
}
