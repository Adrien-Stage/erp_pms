<?php

namespace App\Services;

use App\Models\Tenant;
use PDO;
use PDOException;

/**
 * Accès à la base applicative d'un établissement.
 *
 * Chaque établissement a sa propre base dans son propre conteneur : il n'y a
 * pas de copie des employés côté ERP. La console y lit le personnel et les
 * départements ; elle n'y écrit plus ni compte ni département — ceux-là
 * passent par l'API d'orchestration de l'application (TenantDirectoryClient),
 * seule à pouvoir les valider et les tracer. Restent les tickets de support.
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
        $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
        $dbUser = $tenant->db_username ?? 'pms';
        $dbPass = $tenant->db_password ?? 'secret';
        $dbContainer = $tenant->docker_db_container ?: ('meka-erp-'.$tenant->slug.'-db');

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

    /** La table existe-t-elle dans la base de l'établissement ? */
    private function aLaTable(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?");
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    /** La colonne existe-t-elle dans la base de l'établissement ? */
    private function aLaColonne(PDO $pdo, string $table, string $colonne): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $colonne]);

        return (bool) $stmt->fetchColumn();
    }

    /** Employés de l'établissement, avec leurs départements, rôles et niveaux d'accès. */
    public function users(Tenant $tenant): array
    {
        $pdo = $this->connect($tenant);

        // La colonne users.role a disparu des établissements à jour (leurs
        // droits ne viennent plus que des affectations) ; les autres l'ont
        // encore.
        $role = $this->aLaColonne($pdo, 'users', 'role') ? 'u.role' : 'NULL AS role';

        try {
            $users = $pdo->query("
                SELECT u.id, u.name, u.email, u.phone, {$role}, u.is_active, u.department_id, u.created_at,
                       d.name AS department_name, d.code AS department_code, d.slug AS department_slug,
                       d.icon AS department_icon, d.accent AS department_accent
                FROM users u
                LEFT JOIN departments d ON d.id = u.department_id
                ORDER BY u.name
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $users = $pdo->query("SELECT u.id, u.name, u.email, u.phone, {$role}, u.is_active, u.created_at FROM users u ORDER BY u.name")
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
            // Rôle principal : la colonne si elle existe encore, sinon la
            // première affectation.
            $user['role'] ??= $user['roles'][0]['slug'] ?? null;
            $user['module_permissions'] = $permissionsByUser[$user['id']] ?? [];

            return (object) $user;
        }, $users);
    }

    /**
     * Nombre total d'utilisateurs/employés enregistrés dans la base de l'établissement.
     */
    public function countUsers(Tenant $tenant): int
    {
        $pdo = $this->connect($tenant);

        return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /**
     * Synchronise le compteur dénormalisé « users_count » d'un établissement depuis sa base.
     */
    public function syncUsersCount(Tenant $tenant): int
    {
        try {
            $count = $this->countUsers($tenant);
            if ($tenant->users_count !== $count) {
                $tenant->update(['users_count' => $count]);
                $tenant->users_count = $count;
            }

            return $count;
        } catch (\Throwable $e) {
            return (int) ($tenant->users_count ?? 0);
        }
    }

    /**
     * Synchronise en lot le compteur d'utilisateurs pour une collection d'établissements.
     * Ne tente la connexion que pour les containers DB actuellement en cours d'exécution
     * pour éviter tout blocage ou timeout PDO sur les containers arrêtés.
     */
    public function syncUsersCountForTenants(iterable $tenants): void
    {
        $runningContainers = $this->getRunningContainers();

        foreach ($tenants as $tenant) {
            if (! $tenant->provisioned_at) {
                continue;
            }

            $dbContainer = $tenant->docker_db_container ?: ('meka-erp-'.$tenant->slug.'-db');

            // Si la liste des conteneurs actifs est disponible et que ce conteneur n'en fait pas partie, ignorer
            if ($runningContainers !== null && ! in_array($dbContainer, $runningContainers, true)) {
                continue;
            }

            $this->syncUsersCount($tenant);
        }
    }

    /**
     * Renvoie la liste des conteneurs Docker en cours d'exécution, ou null si Docker n'est pas accessible.
     *
     * @return array<string>|null
     */
    public function getRunningContainers(): ?array
    {
        try {
            $output = shell_exec('docker ps --filter "status=running" --format "{{.Names}}" 2>/dev/null');
            if ($output === null) {
                return null;
            }

            $lines = array_filter(array_map('trim', explode("\n", (string) $output)));

            return array_values($lines);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fiche complète d'un employé : identité, département, rôles et permissions modulaires.
     */
    public function userDetail(Tenant $tenant, int $userId): ?object
    {
        $pdo = $this->connect($tenant);

        $role = $this->aLaColonne($pdo, 'users', 'role') ? 'u.role' : 'NULL AS role';

        try {
            $stmt = $pdo->prepare(
                "SELECT u.id, u.name, u.email, u.phone, {$role}, u.is_active, u.department_id,
                        u.last_login_at, u.email_verified_at, u.created_at, u.updated_at,
                        d.name AS department_name, d.code AS department_code, d.slug AS department_slug,
                        d.icon AS department_icon, d.accent AS department_accent
                 FROM users u
                 LEFT JOIN departments d ON d.id = u.department_id
                 WHERE u.id = ?"
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT u.id, u.name, u.email, u.phone, {$role}, u.is_active, u.last_login_at,
                            u.email_verified_at, u.created_at, u.updated_at
                     FROM users u WHERE u.id = ?"
                );
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                $stmt = $pdo->prepare('SELECT id, name, email, created_at, updated_at FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        if (! $row) {
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

        $row['role'] ??= $row['roles'][0]['slug'] ?? null;

        // Restrictions de module de l'ancienne console : converties en
        // exceptions nominatives sur les établissements à jour.
        $row['module_permissions'] = [];
        try {
            $stmt = $pdo->prepare('SELECT module_key, access_level FROM user_module_permissions WHERE user_id = ?');
            $stmt->execute([$userId]);
            $row['module_permissions'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (\Throwable $e) {
            // Table non existante
        }

        return (object) $row;
    }

    /**
     * Historique d'actions et traçabilité d'un employé dans l'établissement.
     *
     * Remonte l'ensemble des événements déclenchés par cet utilisateur
     * ainsi que les actions d'administration ciblant son compte (création,
     * modification de rôle, réinitialisation de mot de passe, etc.).
     *
     * @return array<int, object>
     */
    public function userAuditLogs(Tenant $tenant, int $userId, int $limit = 150): array
    {
        try {
            $pdo = $this->connect($tenant);

            if (! $this->aLaTable($pdo, 'audit_logs')) {
                return [];
            }

            $stmt = $pdo->prepare('
                SELECT id, user_id, event_type, action, module, ip_address, user_agent, payload, created_at
                FROM audit_logs
                WHERE user_id = :userId
                   OR (payload IS NOT NULL AND (
                       CAST(payload AS text) LIKE :target1
                    OR CAST(payload AS text) LIKE :target2
                    OR CAST(payload AS text) LIKE :target3
                    OR CAST(payload AS text) LIKE :target4
                   ))
                ORDER BY created_at DESC
                LIMIT :limit
            ');

            $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':target1', '%"target_user_id":' . $userId . '%', PDO::PARAM_STR);
            $stmt->bindValue(':target2', '%"target_user_id":"' . $userId . '"%', PDO::PARAM_STR);
            $stmt->bindValue(':target3', '%"target_user_id": ' . $userId . '%', PDO::PARAM_STR);
            $stmt->bindValue(':target4', '%"target_user_id": "' . $userId . '"%', PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function ($row) {
                if (! empty($row['payload']) && is_string($row['payload'])) {
                    $decoded = json_decode($row['payload'], true);
                    $row['payload_decoded'] = is_array($decoded) ? $decoded : null;
                } else {
                    $row['payload_decoded'] = is_array($row['payload']) ? $row['payload'] : null;
                }

                return (object) $row;
            }, $rows);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Lecture audit logs tenant {$tenant->slug} échouée : " . $e->getMessage());

            return [];
        }
    }

    /**
     * Supprime définitivement un utilisateur dans la base de l'établissement.
     *
     * Contrôles stricts de sécurité :
     * 1. Vérifie l'existence du compte
     * 2. Empêche la suppression du dernier compte administrateur
     * 3. Nettoie les sessions actives et liaisons périphériques
     * 4. Enregistre une trace immuable dans le journal d'audit de l'établissement
     *
     * @throws \RuntimeException Si des contraintes métier ou d'intégrité légale bloquent la suppression
     */
    public function deleteUser(Tenant $tenant, int $userId): bool
    {
        $pdo = $this->connect($tenant);

        // 1. Vérification de l'existence du compte
        $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $user) {
            return false;
        }

        // 2. Empêcher la suppression du dernier administrateur de l'établissement
        if ($this->aLaTable($pdo, 'role_user') && $this->aLaTable($pdo, 'roles')) {
            $adminCountStmt = $pdo->query("
                SELECT COUNT(DISTINCT ru.user_id)
                FROM role_user ru
                JOIN roles r ON r.id = ru.role_id
                WHERE r.slug = 'admin'
            ");
            $totalAdmins = (int) ($adminCountStmt ? $adminCountStmt->fetchColumn() : 0);

            $isUserAdminStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM role_user ru
                JOIN roles r ON r.id = ru.role_id
                WHERE ru.user_id = ? AND r.slug = 'admin'
            ");
            $isUserAdminStmt->execute([$userId]);
            $isUserAdmin = (int) $isUserAdminStmt->fetchColumn() > 0;

            if ($isUserAdmin && $totalAdmins <= 1) {
                throw new \RuntimeException("Impossible de supprimer le dernier compte administrateur de l'établissement.");
            }
        }

        // 3. Exécution de la suppression dans une transaction SQL
        $pdo->beginTransaction();
        try {
            // Nettoyage des permissions spécifiques / exceptions
            if ($this->aLaTable($pdo, 'user_module_permissions')) {
                $pdo->prepare('DELETE FROM user_module_permissions WHERE user_id = ?')->execute([$userId]);
            }

            // Purge des sessions actives
            if ($this->aLaTable($pdo, 'sessions')) {
                $pdo->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$userId]);
            }

            // Détachement des points de vente
            if ($this->aLaTable($pdo, 'point_of_sale_user')) {
                $pdo->prepare('DELETE FROM point_of_sale_user WHERE user_id = ?')->execute([$userId]);
            }

            // Inscription au journal d'audit de l'établissement AVANT suppression du compte
            if ($this->aLaTable($pdo, 'audit_logs')) {
                $auditStmt = $pdo->prepare('
                    INSERT INTO audit_logs (user_id, event_type, action, module, payload, created_at, updated_at)
                    VALUES (NULL, :event_type, :action, :module, :payload, NOW(), NOW())
                ');
                $auditStmt->execute([
                    ':event_type' => 'user_deletion_by_erp',
                    ':action'     => "Suppression définitive du compte de {$user['name']} ({$user['email']}) effectuée depuis la console centrale ERP",
                    ':module'     => 'security',
                    ':payload'    => json_encode(['deleted_user_id' => $userId, 'deleted_email' => $user['email']]),
                ]);
            }

            // Suppression du compte utilisateur
            $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $deleteStmt->execute([$userId]);

            $pdo->commit();

            return true;
        } catch (\PDOException $e) {
            $pdo->rollBack();

            $msg = $e->getMessage();
            if (str_contains($msg, 'shop_orders') || str_contains($msg, 'shift_handovers') || str_contains($msg, 'foreign key constraint')) {
                throw new \RuntimeException("Des opérations comptables ou commerciales (commandes boutique, passations de service) sont rattachées à ce collaborateur pour des raisons légales de traçabilité. Veuillez désactiver son compte au lieu de le supprimer.");
            }

            throw $e;
        }
    }

    /**
     * Liste des départements d'un établissement avec leurs modules attachés.
     */
    public function departments(Tenant $tenant): array
    {
        try {
            $pdo = $this->connect($tenant);

            $stmt = $pdo->query('
                SELECT id, name, slug, code, description, icon, accent, sort_order, is_active
                FROM departments
                WHERE is_active = true
                ORDER BY sort_order, name
            ');

            if (! $stmt) {
                return [];
            }

            $depts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($depts)) {
                return [];
            }

            // Les modules par département ont disparu des établissements à
            // jour : un département range le personnel, il ne donne aucun droit.
            $modRows = [];
            if ($this->aLaTable($pdo, 'department_module')) {
                $modStmt = $pdo->query('SELECT department_id, module_key, default_level FROM department_module');
                $modRows = $modStmt ? $modStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }

            $modsByDept = [];
            foreach ($modRows as $m) {
                $modsByDept[$m['department_id']][] = [
                    'key' => $m['module_key'],
                    'level' => $m['default_level'],
                ];
            }

            $userCounts = [];
            try {
                $countRows = $pdo->query('SELECT department_id, COUNT(*) AS cnt FROM users WHERE department_id IS NOT NULL GROUP BY department_id')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($countRows as $cr) {
                    $userCounts[$cr['department_id']] = (int) $cr['cnt'];
                }
            } catch (\Throwable $e) {
            }

            return array_map(function ($d) use ($modsByDept, $userCounts) {
                $d['modules'] = $modsByDept[$d['id']] ?? [];
                $d['users_count'] = $userCounts[$d['id']] ?? 0;

                return (object) $d;
            }, $depts);
        } catch (\Throwable $e) {
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
            ':statut' => $statut,
            ':reponse' => ($reponse === null || $reponse === '') ? null : $reponse,
            ':par' => $traitePar,
            ':id' => $ticketId,
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
            ':type' => $type,
            ':subject' => $subject,
            ':message' => $message,
            ':context_url' => $contextUrl,
            ':status' => 'nouveau',
        ]);

        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return $res ? (int) $res['id'] : (int) $pdo->lastInsertId();
    }
}
