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
            return $this->alignerFuseau(
                new PDO("pgsql:host={$dbContainer};port=5432;dbname={$safeDbName}", $dbUser, $dbPass, $options)
            );
        } catch (PDOException $e) {
            return $this->alignerFuseau(
                new PDO("pgsql:host=127.0.0.1;port={$tenant->db_port};dbname={$safeDbName}", $dbUser, $dbPass, $options)
            );
        }
    }

    /**
     * Cale la session sur le fuseau de la plateforme.
     *
     * Ces connexions sont ouvertes en PDO direct, hors du connecteur Laravel :
     * elles n'héritent donc pas du « timezone » de config/database.php. Sans ce
     * réglage, un NOW() écrit par l'ERP dans la base d'un établissement serait
     * décalé d'une heure par rapport aux dates que l'application y écrit.
     */
    private function alignerFuseau(PDO $connexion): PDO
    {
        $fuseau = str_replace("'", "''", (string) config('app.timezone'));
        $connexion->exec("SET TIME ZONE '{$fuseau}'");

        return $connexion;
    }

    /** Employés de l'établissement, avec leurs départements, rôles et niveaux d'accès. */
    public function users(Tenant $tenant): array
    {
        $pdo = $this->connect($tenant);

        try {
            $users = $pdo->query('
                SELECT u.id, u.name, u.email, u.phone, u.role, u.is_active, u.department_id,
                       d.name AS department_name, d.code AS department_code, d.slug AS department_slug,
                       d.icon AS department_icon, d.accent AS department_accent
                FROM users u
                LEFT JOIN departments d ON d.id = u.department_id
                ORDER BY u.name
            ')->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $users = $pdo->query('SELECT id, name, email, phone, role, is_active FROM users ORDER BY name')
                ->fetchAll(PDO::FETCH_ASSOC);
        }

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
            // Établissement sur une version antérieure au multi-rôles
        }

        // Surcharges de permissions modulaires
        $permissionsByUser = [];
        try {
            $permRows = $pdo->query('
                SELECT user_id, module_key, access_level
                FROM user_module_permissions
            ')->fetchAll(PDO::FETCH_ASSOC);

            foreach ($permRows as $p) {
                $permissionsByUser[$p['user_id']][$p['module_key']] = $p['access_level'];
            }
        } catch (PDOException $e) {
            // Table non présente sur les bases non migrées
        }

        return array_map(function (array $user) use ($pivot, $permissionsByUser) {
            $user['roles'] = $pivot[$user['id']] ?? [];
            $user['module_permissions'] = $permissionsByUser[$user['id']] ?? [];

            return (object) $user;
        }, $users);
    }

    /** Un employé précis, ou null s'il n'existe pas dans cet établissement. */
    public function findUser(Tenant $tenant, int $userId): ?object
    {
        $stmt = $this->connect($tenant)->prepare(
            'SELECT id, name, email, phone, role, is_active, department_id FROM users WHERE id = ?'
        );
        try {
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stmt = $this->connect($tenant)->prepare(
                'SELECT id, name, email, phone, role, is_active FROM users WHERE id = ?'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $row ? (object) $row : null;
    }

    /**
     * Fiche complète d'un employé : identité, département, rôles et permissions modulaires.
     */
    public function userDetail(Tenant $tenant, int $userId): ?object
    {
        $pdo = $this->connect($tenant);

        try {
            $stmt = $pdo->prepare(
                'SELECT u.id, u.name, u.email, u.phone, u.role, u.is_active, u.department_id,
                        u.last_login_at, u.email_verified_at, u.created_at, u.updated_at,
                        d.name AS department_name, d.code AS department_code, d.slug AS department_slug,
                        d.icon AS department_icon, d.accent AS department_accent
                 FROM users u
                 LEFT JOIN departments d ON d.id = u.department_id
                 WHERE u.id = ?'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT id, name, email, phone, role, is_active, last_login_at,
                            email_verified_at, created_at, updated_at
                     FROM users WHERE id = ?'
                );
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                $stmt = $pdo->prepare('SELECT id, name, email, created_at, updated_at FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        if (!$row) {
            return null;
        }

        $row += ['phone' => null, 'role' => null, 'is_active' => true,
                 'last_login_at' => null, 'email_verified_at' => null, 'department_id' => null];

        $row['roles'] = [];
        try {
            $stmt = $pdo->prepare('
                SELECT r.id, r.slug, r.name, r.module, r.description, ru.level
                FROM role_user ru
                JOIN roles r ON r.id = ru.role_id
                WHERE ru.user_id = ?
                ORDER BY r.sort_order, r.name
            ');
            $stmt->execute([$userId]);
            $row['roles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Établissement antérieur au multi-rôles
        }

        // Surcharges de permissions modulaires
        $row['module_permissions'] = [];
        try {
            $stmt = $pdo->prepare('SELECT module_key, access_level FROM user_module_permissions WHERE user_id = ?');
            $stmt->execute([$userId]);
            $row['module_permissions'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (PDOException $e) {
            // Table non existante
        }

        return (object) $row;
    }

    /**
     * Liste des départements d'un établissement avec leurs modules attachés.
     */
    public function departments(Tenant $tenant): array
    {
        $pdo = $this->connect($tenant);

        try {
            $depts = $pdo->query('
                SELECT id, name, slug, code, description, icon, accent, sort_order, is_active
                FROM departments
                WHERE is_active = true
                ORDER BY sort_order, name
            ')->fetchAll(PDO::FETCH_ASSOC);

            if (empty($depts)) {
                return [];
            }

            $modRows = $pdo->query('
                SELECT department_id, module_key, default_level
                FROM department_module
            ')->fetchAll(PDO::FETCH_ASSOC);

            $modsByDept = [];
            foreach ($modRows as $m) {
                $modsByDept[$m['department_id']][] = [
                    'key'   => $m['module_key'],
                    'level' => $m['default_level'],
                ];
            }

            return array_map(function ($d) use ($modsByDept) {
                $d['modules'] = $modsByDept[$d['id']] ?? [];
                return (object) $d;
            }, $depts);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Enregistre le département et les surcharges de permissions par module d'un employé.
     *
     * @param Tenant   $tenant
     * @param int      $userId
     * @param int|null $departmentId
     * @param array<string, string> $modulePermissions [module_key => inherit|write|read|none]
     */
    public function syncUserPermissions(Tenant $tenant, int $userId, ?int $departmentId, array $modulePermissions): void
    {
        $pdo = $this->connect($tenant);

        // 1. Mise à jour du département
        try {
            $stmt = $pdo->prepare('UPDATE users SET department_id = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$departmentId, $userId]);
        } catch (PDOException $e) {
            // Colonne department_id absente sur ancien schéma
        }

        // 2. Synchronisation des surcharges modulaires
        try {
            $pdo->prepare('DELETE FROM user_module_permissions WHERE user_id = ?')->execute([$userId]);

            if (!empty($modulePermissions)) {
                $insert = $pdo->prepare(
                    'INSERT INTO user_module_permissions (user_id, module_key, access_level, created_at, updated_at)
                     VALUES (?, ?, ?, NOW(), NOW())'
                );

                foreach ($modulePermissions as $moduleKey => $level) {
                    $level = trim(strtolower((string) $level));
                    // 'inherit' signifie "suit la règle du département", aucune ligne de surcharge stockée
                    if ($level === 'inherit' || empty($level)) {
                        continue;
                    }
                    if (in_array($level, ['write', 'read', 'none'], true)) {
                        $insert->execute([$userId, $moduleKey, $level]);
                    }
                }
            }
        } catch (PDOException $e) {
            // Table non présente sur ancien schéma
        }
    }

    /** Nombre de managers actifs — un établissement doit en garder au moins un. */
    public function activeManagerCount(Tenant $tenant): int
    {
        try {
            return (int) $this->connect($tenant)
                ->query("SELECT COUNT(*) FROM users WHERE role = 'manager' AND is_active = true")
                ->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
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

    /**
     * Tickets remontés par le personnel depuis le bouton « Suggestion » de
     * l'application. Il n'y a pas de copie côté ERP : le kanban lit ces lignes
     * là où elles sont écrites.
     *
     * Une base sans la table « support_tickets » tourne sur une image
     * antérieure à la fonctionnalité. Ce n'est pas une panne : on le signale
     * séparément d'un établissement injoignable, qui lui demande une action.
     *
     * @return array{tickets: array<int, array>, disponible: bool}
     */
    public function supportTickets(Tenant $tenant, int $limite = 120): array
    {
        // La connexion reste hors du try : une base injoignable doit remonter
        // au contrôleur, alors qu'une table absente se traite ici.
        $pdo = $this->connect($tenant);

        try {
            $stmt = $pdo->prepare(
                'SELECT id, author_name, author_role, type, subject, message, context_url,
                        status, reply, handled_by, handled_at, created_at
                 FROM support_tickets
                 ORDER BY created_at DESC
                 LIMIT :lim'
            );
            $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            return ['tickets' => [], 'disponible' => false];
        }

        return ['tickets' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'disponible' => true];
    }

    /**
     * Une ligne de ticket précise. Sert aux traitements qui doivent s'appuyer
     * sur le contenu réel du ticket plutôt que sur ce que le navigateur
     * affirme — l'ouverture d'une session d'assistance, dont le motif part au
     * journal d'audit.
     */
    public function supportTicket(Tenant $tenant, int $ticketId): ?array
    {
        $stmt = $this->connect($tenant)->prepare(
            'SELECT id, author_name, author_role, type, subject, message, context_url,
                    status, reply, handled_by, handled_at, created_at
               FROM support_tickets
              WHERE id = :id'
        );

        $stmt->execute([':id' => $ticketId]);
        $ligne = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ligne ?: null;
    }

    /**
     * Traitement d'un ticket depuis l'ERP : nouveau statut et, éventuellement,
     * la réponse que l'auteur verra dans son application.
     *
     * « updated_at » est renseigné à la main : cette base n'est pas touchée par
     * Eloquent ici, aucun horodatage automatique ne s'applique.
     */
    public function updateSupportTicket(
        Tenant $tenant,
        int $ticketId,
        string $statut,
        ?string $reponse,
        string $traitePar
    ): bool {
        $stmt = $this->connect($tenant)->prepare(
            'UPDATE support_tickets
                SET status = :statut,
                    reply = COALESCE(:reponse, reply),
                    handled_by = :par,
                    handled_at = NOW(),
                    updated_at = NOW()
              WHERE id = :id'
        );

        $stmt->execute([
            ':statut'  => $statut,
            ':reponse' => ($reponse === null || $reponse === '') ? null : $reponse,
            ':par'     => $traitePar,
            ':id'      => $ticketId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Création d'un ticket de support directement dans la base d'un établissement.
     */
    public function createSupportTicket(
        Tenant $tenant,
        string $authorName,
        ?string $authorRole,
        string $type,
        string $subject,
        string $message,
        ?string $contextUrl = null
    ): ?int {
        $pdo = $this->connect($tenant);
        $stmt = $pdo->prepare(
            'INSERT INTO support_tickets (author_name, author_role, type, subject, message, context_url, status, created_at, updated_at)
             VALUES (:author_name, :author_role, :type, :subject, :message, :context_url, :status, NOW(), NOW())
             RETURNING id'
        );

        $stmt->execute([
            ':author_name' => $authorName,
            ':author_role' => $authorRole,
            ':type'        => $type,
            ':subject'     => $subject,
            ':message'     => $message,
            ':context_url' => $contextUrl,
            ':status'      => 'nouveau',
        ]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? (int) $res['id'] : (int) $pdo->lastInsertId();
    }
}
