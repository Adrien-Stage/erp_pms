<?php

namespace App\Services;

use App\Models\Tenant;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Installation du jeu de démonstration dans un établissement.
 *
 * Le contenu vit dans /demo, à la racine du dépôt : c'est la source de vérité,
 * éditable sans reconstruire l'image applicative. Ce service se contente de
 * l'ordonnancer et de l'écrire dans la base de l'établissement, par la même
 * voie PDO que la gestion des employés (voir TenantDatabase).
 *
 * Écrire depuis l'ERP plutôt que d'embarquer un seeder dans wetchah_app est un
 * choix assumé : un seeder embarqué aurait figé les données dans l'image, et
 * enrichir le jeu aurait imposé un build puis une mise à jour de chaque
 * établissement déjà en service.
 */
class DemoDataService
{
    public function __construct(private TenantDatabase $tenantDb) {}

    /** Racine du dossier /demo. */
    private function racine(): string
    {
        return base_path('demo');
    }

    /**
     * Installe le jeu complet.
     *
     * @param  callable  $log  fn(string $etape, string $message, string $niveau)
     * @return array{total:int, etapes:array<int, array{label:string, count:int, skipped:bool}>}
     */
    public function install(Tenant $tenant, callable $log): array
    {
        $manifeste = $this->manifeste();
        $donnees   = $this->donnees();
        $modules   = $this->modulesActifs($tenant);

        $log('start', "Installation des données de démonstration pour « {$tenant->name} »…", 'info');

        $pdo = $this->tenantDb->connect($tenant);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->verifierBase($pdo);

        $etapes = [];
        $total  = 0;

        // Une seule transaction : un jeu à moitié installé — des réservations
        // sans leurs paiements — serait plus difficile à rattraper qu'un échec
        // franc qui ne laisse rien derrière lui.
        $pdo->beginTransaction();

        try {
            // Le registre de ce que le jeu crée : la purge s'y fie pour tout retirer.
            \Demo\Registre::preparer($pdo, $donnees);

            foreach ($manifeste['steps'] as $etape) {
                $classe = 'Demo\\Seeders\\' . $etape['seeder'];

                if (!class_exists($classe)) {
                    throw new RuntimeException("Seeder introuvable : {$etape['seeder']}.");
                }

                /** @var \Demo\Seeders\AbstractModuleSeeder $seeder */
                $seeder = new $classe($pdo, $donnees, $manifeste['volume'] ?? []);
                $module = $seeder->module();

                if ($module !== null && !in_array($module, $modules, true)) {
                    $log('skip', "· {$seeder->label()} — module « {$module} » inactif, ignoré.", 'info');
                    $etapes[] = ['label' => $seeder->label(), 'count' => 0, 'skipped' => true];
                    continue;
                }

                $nb = $seeder->seed();
                $total += $nb;

                $log(
                    'step',
                    $nb > 0
                        ? "✅ {$seeder->label()} : {$nb} enregistrement(s)."
                        : "· {$seeder->label()} : déjà en place, rien à ajouter.",
                    $nb > 0 ? 'success' : 'info'
                );

                $etapes[] = ['label' => $seeder->label(), 'count' => $nb, 'skipped' => false];
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $log('error', "Échec : {$e->getMessage()} — aucune donnée n'a été conservée.", 'error');

            throw $e;
        }

        $log('done', "Terminé : {$total} enregistrement(s) au total.", 'success');

        return ['total' => $total, 'etapes' => $etapes];
    }

    /**
     * Retire le jeu de démonstration.
     *
     * Ne supprime que ce qui porte un marqueur posé à l'installation, et laisse
     * en place tout ce qui sert encore à l'exploitation — le détail des règles
     * est dans Demo\Purger.
     *
     * @param  callable  $log  fn(string $etape, string $message, string $niveau)
     * @return array{total:int, kept:array<int, string>}
     */
    public function purge(Tenant $tenant, callable $log): array
    {
        $log('start', "Retrait des données de démonstration de « {$tenant->name} »…", 'info');

        $pdo = $this->tenantDb->connect($tenant);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->verifierBase($pdo);

        // Même raison qu'à l'installation : une purge à moitié faite laisserait
        // des réservations sans leurs folios, plus difficiles à rattraper qu'un
        // échec franc.
        $pdo->beginTransaction();

        try {
            $resultat = (new \Demo\Purger($pdo, $this->donnees()))->purge($log);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $log('error', "Échec : {$e->getMessage()} — rien n'a été supprimé.", 'error');

            throw $e;
        }

        foreach ($resultat['kept'] as $note) {
            $log('kept', "· {$note}", 'info');
        }

        $log('done', "Terminé : {$resultat['total']} enregistrement(s) supprimé(s).", 'success');

        return $resultat;
    }

    /** Mot de passe commun aux comptes du personnel de démonstration (staff.json). */
    public function motDePasse(): string
    {
        return (string) ($this->lireJson($this->racine() . '/data/staff.json')['password'] ?? '');
    }

    /**
     * Les comptes du personnel de démonstration présents dans l'établissement,
     * dans l'ordre du jeu : nom, poste, équipe, identifiant, rôle et ce que le
     * rôle permet. Vide si l'établissement est injoignable.
     *
     * @return list<array{name:string, email:string, team:string, title:?string, role:?string, role_name:?string, acces:?string, department:?string, actif:bool}>
     */
    public function comptes(Tenant $tenant): array
    {
        $jeu = $this->lireJson($this->racine() . '/data/staff.json');

        try {
            $pdo  = $this->tenantDb->connect($tenant);
            $stmt = $pdo->prepare(
                "SELECT u.email, u.is_active, d.name AS department, r.slug, r.name AS role_name, r.description
                 FROM users u
                 LEFT JOIN departments d ON d.id = u.department_id
                 LEFT JOIN role_user ru ON ru.user_id = u.id
                 LEFT JOIN roles r ON r.id = ru.role_id
                 WHERE lower(u.email) LIKE ?"
            );
            $stmt->execute(['%@' . \Demo\Seeders\StaffSeeder::DOMAINE]);
            $presents = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
                $presents[strtolower($ligne['email'])] ??= $ligne;
            }
        } catch (Throwable) {
            return [];
        }

        $comptes = [];

        foreach ($jeu['staff'] as $personne) {
            $present = $presents[strtolower($personne['email'])] ?? null;

            if ($present === null) {
                continue;
            }

            $comptes[] = [
                'name'       => $personne['name'],
                'email'      => $personne['email'],
                'team'       => $personne['team'],
                'title'      => $personne['title'] ?? null,
                'role'       => $present['slug'],
                'role_name'  => $present['role_name'],
                'acces'      => $present['description'],
                'department' => $present['department'],
                'actif'      => (bool) $present['is_active'],
            ];
        }

        return $comptes;
    }

    /**
     * L'établissement porte-t-il déjà des données de démonstration ?
     *
     * Sert à l'écran, pour proposer « installer » ou « compléter » plutôt que de
     * laisser l'utilisateur découvrir après coup que tout était déjà là.
     */
    public function estInstalle(Tenant $tenant): bool
    {
        try {
            $pdo = $this->tenantDb->connect($tenant);

            if (\Demo\Registre::nonVide($pdo)) {
                return true;
            }

            $stmt = $pdo->prepare('SELECT 1 FROM bookings WHERE booking_number LIKE ? LIMIT 1');
            $stmt->execute([\Demo\Seeders\BookingSeeder::PREFIXE . '%']);

            return $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            // Conteneur arrêté ou base pas encore migrée : l'écran doit
            // s'afficher malgré tout, l'action se chargera de refuser.
            return false;
        }
    }

    /**
     * La base est-elle prête à recevoir le jeu ?
     *
     * Un établissement fraîchement créé dont les migrations n'ont pas encore
     * tourné n'a aucune table : mieux vaut le dire clairement que de laisser
     * remonter une erreur SQL brute.
     */
    private function verifierBase(PDO $pdo): void
    {
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = 'public' AND table_name IN ('customers', 'rooms', 'bookings')"
        );

        if ((int) $stmt->fetchColumn() < 3) {
            throw new RuntimeException(
                "La base de cet établissement n'est pas encore migrée. "
                . "Terminez le provisioning avant d'installer les données de démonstration."
            );
        }
    }

    /** Modules actifs, le socle hôtelier étant toujours présent. */
    private function modulesActifs(Tenant $tenant): array
    {
        return array_values(array_unique(array_merge(['hotel'], $tenant->modules ?? [])));
    }

    /** @return array<string, mixed> */
    private function manifeste(): array
    {
        return $this->lireJson($this->racine() . '/manifest.json');
    }

    /**
     * Tous les fichiers de /demo/data, indexés par nom sans extension :
     * data/people.json devient $donnees['people'].
     *
     * @return array<string, array>
     */
    private function donnees(): array
    {
        $donnees = [];

        foreach (glob($this->racine() . '/data/*.json') ?: [] as $fichier) {
            $donnees[basename($fichier, '.json')] = $this->lireJson($fichier);
        }

        return $donnees;
    }

    private function lireJson(string $chemin): array
    {
        if (!is_file($chemin)) {
            throw new RuntimeException("Fichier de démonstration manquant : {$chemin}");
        }

        $contenu = json_decode((string) file_get_contents($chemin), true);

        if (!is_array($contenu)) {
            throw new RuntimeException("JSON invalide dans : {$chemin}");
        }

        return $contenu;
    }
}
