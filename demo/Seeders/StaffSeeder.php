<?php

namespace Demo\Seeders;

use Demo\Registre;

/**
 * Le personnel de l'hôtel : un compte par personne du jeu, avec son rôle, son
 * département et ce que son service lui rattache (équipe de ménage,
 * restaurant, spécialités techniques, planning de quarts de la semaine).
 *
 * Chaque service compte au moins deux personnes et chaque rôle du PMS au moins
 * une : on peut se connecter sous n'importe quel poste pour montrer ce qu'il
 * voit et ce qu'il fait.
 *
 * Garde-fous, parce que ces comptes ouvrent une session :
 *  - les adresses sont sur le domaine réservé demo.wetchah.test — aucun
 *    courrier ne part, et c'est le marqueur qu'utilise la purge ;
 *  - un seul mot de passe pour tous, celui du jeu (staff.json) : relancer
 *    l'installation le remet sur les comptes existants et les réactive ;
 *  - un rôle par personne : aucun cumul que la séparation des tâches refuserait ;
 *  - jamais d'administrateur : ce niveau ne vient que du provisioning.
 */
class StaffSeeder extends AbstractModuleSeeder
{
    /** Domaine réservé (RFC 2606) des adresses du jeu : le marqueur de la purge. */
    public const DOMAINE = 'demo.wetchah.test';

    /** Ce que le jeu ne crée jamais, même si un fichier le demandait. */
    private const ROLES_EXCLUS = ['admin', 'support', 'customer_guest'];

    public function module(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return "Personnel de l'hôtel et planning des quarts";
    }

    public function seed(): int
    {
        $s = $this->data['staff'] ?? [];

        if (empty($s['password']) || !$this->tableExists('role_user')) {
            return 0;
        }

        $empreinte = password_hash($s['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $roles        = $this->roles();
        $departements = $this->departements();
        $equipes      = $this->equipesDeMenage();
        $restaurant   = $this->restaurant();
        $suffixe      = '@' . self::DOMAINE;
        $crees        = 0;

        foreach ($s['staff'] as $personne) {
            $roleId = $roles[$personne['role']] ?? null;

            // Rôle exclu, ou inconnu d'un établissement resté sur une version
            // antérieure : la personne n'est pas créée, plutôt que sans droits.
            if ($roleId === null || in_array($personne['role'], self::ROLES_EXCLUS, true)
                || !str_ends_with($personne['email'], $suffixe)) {
                continue;
            }

            $userId = $this->findId('users', ['email' => $personne['email']]);

            if ($userId === null) {
                $userId = $this->insert('users', array_merge([
                    'name'                 => $personne['name'],
                    'email'                => $personne['email'],
                    'phone'                => $personne['phone'],
                    'password'             => $empreinte,
                    'must_change_password' => false,
                    'email_verified_at'    => $this->now(),
                    // Colonne héritée, encore présente sur les établissements en retard.
                    'role'                 => $personne['role'],
                    'department_id'        => $departements[$personne['department'] ?? ''] ?? null,
                    'is_active'            => true,
                ], $this->timestamps()));
                $crees++;
            } else {
                // Compte déjà là : il reprend le mot de passe du jeu et rouvre ;
                // sans département, il reçoit le sien s'il existe désormais.
                $this->pdo->prepare('UPDATE users SET password = ?, is_active = true WHERE id = ?')
                    ->execute([$empreinte, $userId]);

                if (($departement = $departements[$personne['department'] ?? ''] ?? null) !== null) {
                    $this->pdo->prepare('UPDATE users SET department_id = ? WHERE id = ? AND department_id IS NULL')
                        ->execute([$departement, $userId]);
                }
            }

            $this->rattacher('role_user', ['user_id' => $userId, 'role_id' => $roleId], ['level' => 'write']);

            foreach ($personne['housekeeping_teams'] ?? [] as $equipe) {
                if (isset($equipes[$equipe])) {
                    $this->rattacher('housekeeping_team_user', ['housekeeping_team_id' => $equipes[$equipe], 'user_id' => $userId]);
                }
            }

            if (!empty($personne['restaurant']) && $restaurant !== null) {
                $this->rattacher('point_of_sale_user', ['point_of_sale_id' => $restaurant, 'user_id' => $userId]);
            }

            foreach ($personne['specialties'] ?? [] as $specialite) {
                $this->rattacher('technician_specialties', ['user_id' => $userId, 'specialty' => $specialite]);
            }
        }

        $this->resetUserCache();
        $this->completerChefsEquipes($equipes);
        $crees += $this->planningDesQuarts($s['staff'] ?? [], $departements);

        return $crees;
    }

    /** Une ligne de rattachement, posée une seule fois. */
    private function rattacher(string $table, array $reperes, array $plus = []): void
    {
        if ($this->tableExists($table)) {
            $this->insertOnce($table, $reperes, array_merge($plus, $this->timestamps()));
        }
    }

    /** @return array<string, int> slug => id */
    private function roles(): array
    {
        return $this->pdo->query('SELECT slug, id FROM roles')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /**
     * Les départements de l'établissement, par slug. Celui qui manque laisse
     * la personne sans département : la console les gère, le jeu n'en crée pas.
     *
     * @return array<string, int>
     */
    private function departements(): array
    {
        return $this->tableExists('departments')
            ? $this->pdo->query('SELECT slug, id FROM departments')->fetchAll(\PDO::FETCH_KEY_PAIR)
            : [];
    }

    /**
     * Les équipes de ménage du jeu, créées ici si besoin pour recevoir leurs
     * membres ; l'étape de l'exploitation les retrouve ensuite par leur nom.
     *
     * @return array<string, int> nom => id
     */
    private function equipesDeMenage(): array
    {
        if (!$this->tableExists('housekeeping_teams')) {
            return [];
        }

        $equipes = [];

        foreach ($this->data['operations']['housekeeping_teams'] ?? [] as $equipe) {
            $equipes[$equipe['name']] = $this->insertOnce(
                'housekeeping_teams',
                ['name' => $equipe['name']],
                array_merge([
                    'code'      => $equipe['code'] ?? null,
                    'notes'     => $equipe['notes'] ?? null,
                    'is_active' => true,
                ], $this->timestamps())
            );
        }

        return $equipes;
    }

    /** Affecte le code et le responsable (leader_id) des équipes créées par le jeu. */
    private function completerChefsEquipes(array $equipes): void
    {
        if (!$this->tableExists('housekeeping_teams') || !$this->columnExists('housekeeping_teams', 'leader_id')) {
            return;
        }

        foreach ($this->data['operations']['housekeeping_teams'] ?? [] as $equipe) {
            $id = $equipes[$equipe['name']] ?? null;
            if ($id === null) {
                continue;
            }

            $leaderId = !empty($equipe['leader_email'])
                ? $this->userIdByEmail($equipe['leader_email'], false)
                : null;

            $stmt = $this->pdo->prepare(
                'UPDATE housekeeping_teams
                 SET code = COALESCE(code, ?),
                     notes = COALESCE(notes, ?),
                     leader_id = COALESCE(leader_id, ?)
                 WHERE id = ?'
            );
            $stmt->execute([$equipe['code'] ?? null, $equipe['notes'] ?? null, $leaderId, $id]);
        }
    }

    /**
     * Planning des quarts de la semaine en cours (Lundi -> Dimanche) pour les
     * départements à quarts accueillant du personnel de démonstration.
     */
    private function planningDesQuarts(array $staff, array $departements): int
    {
        if (
            !$this->tableExists('shift_weeks')
            || !$this->tableExists('shift_assignments')
            || !$this->tableExists('work_regimes')
            || !$this->tableExists('work_shifts')
            || !$this->columnExists('work_regimes', 'mode')
            || !$this->columnExists('departments', 'work_regime_id')
            || !$this->columnExists('work_shifts', 'work_regime_id')
        ) {
            return 0;
        }

        $regimeParDept = $this->pdo->query('SELECT id, work_regime_id FROM departments')->fetchAll(\PDO::FETCH_KEY_PAIR);
        $regimeDefaut  = $this->pdo->query('SELECT id FROM work_regimes WHERE is_default = true AND is_active = true ORDER BY id LIMIT 1')->fetchColumn();
        $regimesFixes  = $this->pdo->query("SELECT id FROM work_regimes WHERE mode = 'fixed'")->fetchAll(\PDO::FETCH_COLUMN);
        $regimesFixes  = array_map('intval', $regimesFixes);

        $quartsParRegime = [];
        foreach ($this->pdo->query('SELECT id, work_regime_id, name FROM work_shifts WHERE is_active = true ORDER BY sort_order, starts_at, id')->fetchAll(\PDO::FETCH_ASSOC) as $ws) {
            $quartsParRegime[(int) $ws['work_regime_id']][] = ['id' => (int) $ws['id'], 'name' => $ws['name']];
        }

        $lundiTs   = strtotime('-' . ((int) date('N') - 1) . ' days', strtotime('today'));
        $lundi     = date('Y-m-d', $lundiTs);
        $managerId = $this->userIdByEmail('jean-paul.mbida@' . self::DOMAINE);
        $crees     = 0;

        // Regrouper le personnel démo par département.
        $membresParDept = [];
        foreach ($staff as $personne) {
            $slug = $personne['department'] ?? null;
            if ($slug === null || !isset($departements[$slug])) {
                continue;
            }
            $uid = $this->userIdByEmail($personne['email'], false);
            if ($uid === null) {
                continue;
            }
            $membresParDept[(int) $departements[$slug]][] = ['id' => $uid, 'personne' => $personne];
        }

        foreach ($membresParDept as $deptId => $membres) {
            $regimeId = (int) ($regimeParDept[$deptId] ?: $regimeDefaut ?: 0);
            if ($regimeId === 0 || in_array($regimeId, $regimesFixes, true)) {
                // Les services à horaire fixe (Direction, Finance, Qualité) se pré-remplissent d'office.
                continue;
            }

            $quarts = $quartsParRegime[$regimeId] ?? [];
            if ($quarts === []) {
                continue;
            }

            $quartJour = $quarts[0]['id'];
            $quartNuit = $quarts[1]['id'] ?? $quartJour;

            $semaineAvant = $this->findId('shift_weeks', ['department_id' => $deptId, 'week_start' => $lundi]);
            if ($semaineAvant === null) {
                $this->insert('shift_weeks', array_merge([
                    'department_id' => $deptId,
                    'week_start'    => $lundi,
                    'published_at'  => $this->now(),
                    'published_by'  => $managerId,
                    'sent'          => $this->json([]),
                ], $this->timestamps()));
                $crees++;
            }

            foreach ($membres as $idx => $m) {
                $estNuit = str_contains(mb_strtolower($m['personne']['title'] ?? ''), 'nuit');
                $shiftId = $estNuit ? $quartNuit : $quartJour;

                for ($jour = 0; $jour < 7; $jour++) {
                    // 1 jour de repos hebdomadaire rotatif (aucune assignation ce jour-là).
                    if (($idx + $jour) % 7 === 6) {
                        continue;
                    }

                    $dateStr = date('Y-m-d', strtotime("+{$jour} days", $lundiTs));
                    if ($this->findId('shift_assignments', ['user_id' => $m['id'], 'date' => $dateStr, 'work_shift_id' => $shiftId]) !== null) {
                        continue;
                    }

                    $this->insert('shift_assignments', array_merge([
                        'user_id'       => $m['id'],
                        'work_shift_id' => $shiftId,
                        'department_id' => $deptId,
                        'date'          => $dateStr,
                        'assigned_by'   => $managerId,
                        'source'        => 'manual',
                    ], $this->timestamps()));
                    $crees++;
                }
            }
        }

        return $crees;
    }

    /** Le premier restaurant actif, où travaille l'équipe de salle et de cuisine. */
    private function restaurant(): ?int
    {
        if (!$this->tableExists('points_of_sale') || !$this->tableExists('point_of_sale_user')) {
            return null;
        }

        $id = $this->pdo->query(
            "SELECT id FROM points_of_sale WHERE kind = 'restauration' AND is_active = true ORDER BY sort_order, id LIMIT 1"
        )->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
