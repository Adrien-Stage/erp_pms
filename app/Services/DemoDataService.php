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
            $resultat = (new \Demo\Purger($pdo))->purge($log);
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
