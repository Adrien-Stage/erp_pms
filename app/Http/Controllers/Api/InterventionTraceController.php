<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantIntervention;
use App\Services\TenantSecrets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reçoit la trace d'une intervention de l'administrateur d'un établissement.
 *
 * L'établissement la transmet à l'ouverture, à la clôture, et rejoue l'envoi
 * tant que la console ne l'a pas reçu : la même référence met à jour la même
 * ligne. Il s'authentifie avec son propre secret d'orchestration — celui que
 * la console lui a remis dans son Compose.
 */
class InterventionTraceController extends Controller
{
    public function store(Request $request, Tenant $tenant, TenantSecrets $secrets): JsonResponse
    {
        $secret = $secrets->current($tenant, TenantSecrets::ORCHESTRATION);
        $fourni = (string) $request->bearerToken();

        if ($secret === '' || $fourni === '' || !hash_equals($secret, $fourni)) {
            return response()->json(['message' => 'Non autorisé.'], 401);
        }

        $valide = $request->validate([
            'reference'      => ['required', 'integer', 'min:1'],
            'administrateur' => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'string', 'max:255'],
            'motif'          => ['required', 'string', 'max:500'],
            'perimetres'     => ['present', 'array'],
            'perimetres.*'   => ['string', 'max:100'],
            'debut'          => ['required', 'date'],
            'fin_prevue'     => ['required', 'date'],
            'fin_reelle'     => ['nullable', 'date'],
            'cloture'        => ['nullable', 'in:terminee,expiree'],
            'actions'        => ['nullable', 'integer', 'min:0'],
            'tardive'        => ['nullable', 'boolean'],
        ]);

        $existante = TenantIntervention::where('tenant_id', $tenant->id)
            ->where('reference', $valide['reference'])->first();

        $intervention = TenantIntervention::updateOrCreate(
            ['tenant_id' => $tenant->id, 'reference' => $valide['reference']],
            [
                'administrateur' => $valide['administrateur'] ?? null,
                'email'          => $valide['email'] ?? null,
                'motif'          => $valide['motif'],
                'perimetres'     => $valide['perimetres'],
                'debut'          => $valide['debut'],
                'fin_prevue'     => $valide['fin_prevue'],
                'fin_reelle'     => $valide['fin_reelle'] ?? null,
                'cloture'        => $valide['cloture'] ?? null,
                'actions'        => $valide['actions'] ?? 0,
                'tardive'        => ($existante?->tardive ?? false) || $request->boolean('tardive'),
            ]
        );

        AuditLog::record(null, 'tenant_intervention',
            ($existante ? 'Mise à jour' : 'Ouverture') . " de l'intervention #{$intervention->reference} de "
                . ($intervention->administrateur ?? "l'administrateur") . " à {$tenant->name}"
                . ($intervention->tardive ? ' (transmise en retard)' : '') . " : {$intervention->motif}",
            'support', ['tenant_id' => $tenant->id, 'reference' => $intervention->reference]);

        return response()->json(['id' => $intervention->id], $existante ? 200 : 201);
    }
}
