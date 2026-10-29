<?php

namespace Demo\Seeders;

/**
 * Le personnel de l'hôtel : un compte par personne du jeu, avec son rôle, son
 * département et ce que son service lui rattache (équipe de ménage,
 * restaurant, spécialités techniques).
 *
 * Chaque service compte au moins deux personnes et chaque rôle du PMS au moins
 * une : on peut se connecter sous n'importe quel poste pour montrer ce qu'il
 * voit et ce qu'il fait.
 *
 * Garde-fous, parce que ces comptes ouvrent une session :
 *  - les adresses sont sur le domaine réservé demo.wetchah.test — aucun
 *    courrier ne part, et c'est le marqueur qu'utilise la purge ;
 *  - le mot de passe, commun à ces comptes, est tiré au hasard par la console
 *    pour chaque établissement ; ce seeder n'en reçoit que l'empreinte ;
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
        return "Personnel de l'hôtel";
    }

    public function seed(): int
    {
        $s         = $this->data['staff'] ?? [];
        $empreinte = $s['password_hash'] ?? null;

        if ($empreinte === null || !$this->tableExists('role_user')) {
            return 0;
        }

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
                array_merge(['color' => $equipe['color'], 'is_active' => true], $this->timestamps())
            );
        }

        return $equipes;
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
