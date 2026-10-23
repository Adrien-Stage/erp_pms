<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\MiseAJourServeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Onglet Système (TECH), console de production : la mise à jour du guide
 * (§9.1) lancée d'un bouton. Le travail se fait dans un conteneur à part ; la
 * page suit son journal, y compris pendant le redémarrage de la console.
 */
class ServerUpdateController extends Controller
{
    public function __construct(private readonly MiseAJourServeur $mise)
    {
    }

    public function etat(): JsonResponse
    {
        return response()->json(['contexte' => $this->mise->contexte(), 'etapes' => MiseAJourServeur::ETAPES]);
    }

    public function verifier(Request $request): JsonResponse
    {
        $contexte = $this->mise->contexte();
        if (!$contexte['disponible']) {
            return response()->json(['ok' => false, 'message' => $contexte['raison']], 422);
        }
        if ($this->enCours()) {
            return response()->json(['ok' => false, 'message' => 'Une mise à jour est en cours.'], 409);
        }

        return response()->json($this->mise->verifierDepuisLaConsole($contexte, $this->jeton($request)));
    }

    public function lancer(Request $request): JsonResponse
    {
        $contexte = $this->mise->contexte();
        if (!$contexte['disponible']) {
            return response()->json(['message' => $contexte['raison']], 422);
        }
        if ($this->enCours()) {
            return response()->json(['message' => 'Une mise à jour est déjà en cours.'], 409);
        }

        AuditLog::record(Auth::id(), 'console_update', "Mise à jour de la console lancée (version en service : {$contexte['version']})", 'tech_admin');

        try {
            $this->mise->demarrer($contexte, $this->jeton($request));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true], 202);
    }

    public function statut(): JsonResponse
    {
        $disque = Storage::disk('local');
        $statut = json_decode((string) $disque->get(MiseAJourServeur::DOSSIER . '/statut.json'), true) ?: ['etat' => 'inactif'];

        // L'exécutant a disparu sans écrire sa fin (serveur redémarré, conteneur tué).
        if (($statut['etat'] ?? '') === 'en_cours'
            && Carbon::parse($statut['debut'] ?? now())->diffInSeconds(now()) > 30
            && !$this->mise->executantActif()) {
            $statut = ['etat' => 'termine', 'ok' => false, 'interrompue' => true, 'fin' => now()->toIso8601String()] + $statut;
            $disque->put(MiseAJourServeur::DOSSIER . '/statut.json', json_encode($statut));
        }

        // La fin entre au journal d'audit une fois, lue par la console (peut-être la nouvelle).
        if (($statut['etat'] ?? '') === 'termine' && empty($statut['audite']) && !empty($statut['debut'])) {
            AuditLog::record(Auth::id(), 'console_update', match (true) {
                !empty($statut['rien_a_faire']) => 'Mise à jour de la console : déjà à jour',
                !empty($statut['ok']) => 'Console mise à jour : ' . ($statut['ancienne'] ?? 'latest') . ' → ' . ($statut['nouvelle'] ?? '?'),
                default => 'Mise à jour de la console arrêtée à l\'étape ' . ($statut['etape'] ?? '?'),
            }, 'tech_admin');
            $statut['audite'] = true;
            $disque->put(MiseAJourServeur::DOSSIER . '/statut.json', json_encode($statut));
        }

        $lignes = [];
        foreach (array_filter(explode("\n", (string) $disque->get(MiseAJourServeur::DOSSIER . '/journal.log'))) as $l) {
            if ($d = json_decode($l, true)) {
                $lignes[] = $d;
            }
        }
        $detail = preg_split('/\r\n|\r|\n/', (string) $disque->get(MiseAJourServeur::DOSSIER . '/detail.log'));

        return response()->json([
            'statut' => $statut,
            'lignes' => $lignes,
            'detail' => implode("\n", array_slice(array_filter($detail, fn ($l) => trim($l) !== ''), -80)),
        ]);
    }

    private function enCours(): bool
    {
        $statut = json_decode((string) Storage::disk('local')->get(MiseAJourServeur::DOSSIER . '/statut.json'), true);

        return ($statut['etat'] ?? '') === 'en_cours' || $this->mise->executantActif();
    }

    /** Jeton GitHub saisi pour cette fois (dépôt privé) : jamais conservé ni journalisé. */
    private function jeton(Request $request): ?string
    {
        $jeton = trim((string) $request->input('jeton', ''));

        return $jeton !== '' && preg_match('/^[A-Za-z0-9_\-]{10,255}$/', $jeton) ? $jeton : null;
    }
}
