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
     * Fiche complète d'un employé : toutes ses colonnes et ses rôles détaillés.
     *
     * Distincte de findUser(), qui ne lit que le strict nécessaire aux actions.
     * Ici on veut de quoi remplir un écran — d'où les dates et les rôles.
     *
     * Les colonnes ajoutées par wetchah_app (rôle, téléphone, activation,
     * dernière connexion) peuvent manquer sur un établissement resté sur une
     * version antérieure : on retombe alors sur le socle Laravel plutôt que de
     * laisser l'écran en erreur.
     */
    public function userDetail(Tenant $tenant, int $userId): ?object
    {
        $pdo = $this->connect($tenant);

        try {
            $stmt = $pdo->prepare(
                'SELECT id, name, email, phone, role, is_active, last_login_at,
                        email_verified_at, created_at, updated_at
                 FROM users WHERE id = ?'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare('SELECT id, name, email, created_at, updated_at FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$row) {
            return null;
        }

        $row += ['phone' => null, 'role' => null, 'is_active' => true,
                 'last_login_at' => null, 'email_verified_at' => null];

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
            // Établissement antérieur au multi-rôles : la colonne « role »
            // reste sa seule source d'autorisation.
        }

        return (object) $row;
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
