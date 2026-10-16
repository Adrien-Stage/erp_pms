<?php

namespace App\Http\Controllers;

use App\Console\Commands\MettreAJourCode;
use App\Models\AuditLog;
use App\Services\CodeUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** Onglet Système (TECH) : mise à jour du code de la console par git pull. */
class CodeUpdateController extends Controller
{
    private const DELAI_MAX = 900; // une mise à jour « en cours » depuis plus de 15 min est considérée perdue

    public function __construct(private readonly CodeUpdater $mise)
    {
    }

    public function etat(): JsonResponse
    {
        return response()->json($this->mise->etat() + ['en_cours' => $this->enCours()]);
    }

    public function verifier(): JsonResponse
    {
        if (!$this->mise->estUnDepot()) {
            return response()->json(['ok' => false, 'message' => "Le code n'est pas un dépôt git."], 422);
        }

        return response()->json($this->mise->verifier());
    }

    public function lancer(): JsonResponse
    {
        if (!$this->mise->estUnDepot()) {
            return response()->json(['message' => "Le code de cette console est dans l'image Docker : reconstruisez l'image pour le mettre à jour."], 422);
        }
        if ($this->enCours()) {
            return response()->json(['message' => 'Une mise à jour est déjà en cours.'], 409);
        }

        $etat = $this->mise->etat();
        AuditLog::record(Auth::id(), 'code_update', 'Mise à jour du code de la console lancée (branche ' . ($etat['branche'] ?? '?') . ', commit ' . ($etat['commit']['hash'] ?? '?') . ')', 'tech_admin');

        Storage::disk('local')->put(MettreAJourCode::DOSSIER . '/statut.json', json_encode(['etat' => 'en_cours', 'debut' => now()->toIso8601String()]));
        $php = escapeshellarg(PHP_BINDIR . '/php');
        $artisan = escapeshellarg(base_path('artisan'));
        // Détaché : la requête web se termine, la mise à jour continue.
        exec("nohup {$php} {$artisan} code:mettre-a-jour > /dev/null 2>&1 &");

        return response()->json(['ok' => true], 202);
    }

    public function statut(): JsonResponse
    {
        $disque = Storage::disk('local');
        $statut = json_decode((string) $disque->get(MettreAJourCode::DOSSIER . '/statut.json'), true) ?: ['etat' => 'inactif'];

        $lignes = [];
        foreach (array_filter(explode("\n", (string) $disque->get(MettreAJourCode::DOSSIER . '/journal.log'))) as $l) {
            if ($d = json_decode($l, true)) {
                $lignes[] = $d;
            }
        }

        // Le code vient de changer sur le disque : OPcache (validate_timestamps=0) servirait
        // l'ancien. Une requête web est la seule à pouvoir vider celui de PHP-FPM.
        if (($statut['etat'] ?? '') === 'termine' && empty($statut['vide_opcache'])) {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $statut['vide_opcache'] = true;
            $disque->put(MettreAJourCode::DOSSIER . '/statut.json', json_encode($statut));
        }

        return response()->json(['statut' => $statut, 'lignes' => $lignes]);
    }

    private function enCours(): bool
    {
        $statut = json_decode((string) Storage::disk('local')->get(MettreAJourCode::DOSSIER . '/statut.json'), true);
        if (($statut['etat'] ?? '') !== 'en_cours') {
            return false;
        }

        return now()->diffInSeconds(\Illuminate\Support\Carbon::parse($statut['debut'] ?? now())) < self::DELAI_MAX;
    }
}
