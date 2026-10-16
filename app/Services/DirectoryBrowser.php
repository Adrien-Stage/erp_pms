<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Parcourt les dossiers du système où tourne la console, pour que
 * l'administrateur TECH choisisse celui des docker-compose d'un établissement.
 *
 * Aucun fichier n'est lu ni listé : seulement des noms de dossiers. L'accès est
 * réservé à l'espace TECH, déjà équivalent à root sur ce serveur (socket Docker).
 */
class DirectoryBrowser
{
    private const MAX_ENTREES = 300;

    /** Dossier d'ouverture : celui du réglage, ou son plus proche parent existant. */
    public function depart(): string
    {
        $chemin = rtrim((string) config('provisioning.tenants_base_path'), '/\\') ?: '/';
        while ($chemin !== '/' && !is_dir($chemin)) {
            $chemin = dirname($chemin);
        }

        return realpath($chemin) ?: '/';
    }

    /** @return array{path:string,parent:?string,writable:bool,persistent:bool,directories:list<array{name:string,path:string,writable:bool}>,truncated:bool} */
    public function lister(string $chemin): array
    {
        $chemin = $this->normaliser($chemin);

        $dossiers = [];
        $tronque = false;
        foreach (@scandir($chemin) ?: [] as $nom) {
            if ($nom === '.' || $nom === '..' || $nom[0] === '.') {
                continue;
            }
            $complet = ($chemin === '/' ? '' : $chemin) . '/' . $nom;
            if (!is_dir($complet) || is_link($complet)) {
                continue;
            }
            if (count($dossiers) >= self::MAX_ENTREES) {
                $tronque = true;
                break;
            }
            $dossiers[] = ['name' => $nom, 'path' => $complet, 'writable' => is_writable($complet)];
        }

        return [
            'path' => $chemin,
            'parent' => $chemin === '/' ? null : dirname($chemin),
            'writable' => is_writable($chemin),
            'persistent' => $this->estPersistant($chemin),
            'directories' => $dossiers,
            'truncated' => $tronque,
        ];
    }

    public function creer(string $parent, string $nom): string
    {
        $parent = $this->normaliser($parent);

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $nom)) {
            throw new InvalidArgumentException("Nom de dossier invalide : lettres, chiffres, point, tiret et soulignement seulement.");
        }
        if (!is_writable($parent)) {
            throw new InvalidArgumentException("Le dossier {$parent} n'est pas inscriptible par la console.");
        }

        $cible = ($parent === '/' ? '' : $parent) . '/' . $nom;
        if (file_exists($cible)) {
            throw new InvalidArgumentException("« {$nom} » existe déjà dans {$parent}.");
        }
        if (!@mkdir($cible, 0775)) {
            throw new InvalidArgumentException("Impossible de créer {$cible}.");
        }

        return $cible;
    }

    /** Chemin absolu, existant, sans « .. » ni lien symbolique. */
    public function normaliser(string $chemin): string
    {
        if ($chemin === '' || $chemin[0] !== '/') {
            throw new InvalidArgumentException("Le chemin doit être absolu (commencer par /).");
        }
        $reel = realpath($chemin);
        if ($reel === false || !is_dir($reel)) {
            throw new InvalidArgumentException("Le dossier {$chemin} n'existe pas.");
        }

        return $reel;
    }

    /**
     * Un dossier survit-il à la recréation du conteneur de la console ? Dans
     * Docker, seul un volume ou un dossier monté le garantit : le reste du
     * système de fichiers disparaît avec le conteneur — et avec lui les
     * docker-compose, donc les clés de l'établissement.
     */
    public function estPersistant(string $chemin): bool
    {
        if (!is_file('/.dockerenv')) {
            return true;
        }

        $meilleur = 0;
        foreach (@file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES) ?: [] as $ligne) {
            $champs = explode(' ', $ligne);
            $point = str_replace('\040', ' ', $champs[4] ?? '');
            $sep = array_search('-', $champs, true);
            $type = $sep === false ? '' : ($champs[$sep + 1] ?? '');
            // Montages propres au conteneur (/proc, /sys, /dev…) et racine : sans intérêt.
            // tmpfs reste compté, car un dossier de l'hôte peut très bien en être un.
            $interne = $point === '/' || preg_match('#^/(proc|sys|dev)(/|$)#', $point) === 1;
            if ($interne || in_array($type, ['proc', 'sysfs', 'cgroup', 'cgroup2', 'mqueue', 'overlay'], true)) {
                continue;
            }
            $dessous = $chemin === $point || str_starts_with($chemin . '/', rtrim($point, '/') . '/');
            if ($dessous) {
                $meilleur = max($meilleur, strlen($point));
            }
        }

        return $meilleur > 0;
    }
}
