<?php

use App\Http\Controllers\Api\InterventionTraceController;
use Illuminate\Support\Facades\Route;

// ==========================================
// CANAL ENTRANT DES ÉTABLISSEMENTS
// Chaque établissement s'authentifie avec son propre secret d'orchestration :
// aucun ne peut écrire au nom d'un autre.
// ==========================================
Route::post('/etablissements/{tenant:slug}/interventions', [InterventionTraceController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('api.etablissements.interventions');
