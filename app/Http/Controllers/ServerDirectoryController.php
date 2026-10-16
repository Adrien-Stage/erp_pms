<?php

namespace App\Http\Controllers;

use App\Services\DirectoryBrowser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Sélecteur de dossier du formulaire « Nouvel établissement » : parcourt le
 * système de fichiers du serveur (pas celui du poste de l'utilisateur, qu'un
 * navigateur ne peut de toute façon pas exposer).
 */
class ServerDirectoryController extends Controller
{
    public function __construct(private readonly DirectoryBrowser $dossiers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json($this->dossiers->lister($request->query('path') ?: $this->dossiers->depart()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate(['path' => ['required', 'string'], 'name' => ['required', 'string', 'max:64']]);

        try {
            $cree = $this->dossiers->creer($donnees['path'], $donnees['name']);

            return response()->json($this->dossiers->lister($cree), 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
