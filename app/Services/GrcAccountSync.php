<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Report d'un compte de contrôleur de gestion vers le conteneur GRC de
 * l'établissement.
 *
 * Le GRC tient sa propre base : un compte créé ou modifié dans le PMS n'y
 * existe pas tant qu'il n'y a pas été poussé. Cette logique vivait en double
 * — une copie dans la création d'un contrôleur, rien du tout dans la
 * modification d'un employé — et c'est précisément par là que le défaut est
 * passé : la création visait 127.0.0.1, adresse qui depuis ce conteneur
 * désigne l'ERP lui-même, et le changement de mot de passe ne poussait rien.
 * Un seul endroit, désormais, pour les deux appelants.
 */
class GrcAccountSync
{
    /**
     * Pousse un compte vers le GRC de cet établissement.
     *
     * @param  array{email:string,password:string,full_name:string,phone?:?string,previous_email?:?string}  $compte
     * @return bool|null  true si reporté, false si l'appel a échoué,
     *                    null si l'établissement n'a pas le module GRC.
     */
    public function push(Tenant $tenant, array $compte): ?bool
    {
        if (!$tenant->hasGrc()) {
            return null;
        }

        $secret = (string) config('provisioning.reporting_secret');
        if ($secret === '') {
            Log::warning("[GRC] REPORTING_SECRET non configuré : compte non reporté pour {$tenant->slug}.");
            return false;
        }

        // Les services d'un établissement se joignent par leur nom sur le
        // réseau Docker, jamais par 127.0.0.1 ni par le port publié sur l'hôte.
        $conteneur = $tenant->docker_grc_container ?: ('meka-erp-' . $tenant->slug . '-grc');

        try {
            $reponse = Http::timeout(5)
                ->withToken($secret)
                ->acceptJson()
                ->post("http://{$conteneur}:8000/api/v1/users/provision-from-erp", array_filter([
                    'email'          => $compte['email'],
                    'password'       => $compte['password'],
                    'full_name'      => $compte['full_name'],
                    'phone'          => $compte['phone'] ?? null,
                    // Permet au GRC de renommer le compte existant au lieu
                    // d'en créer un second quand l'adresse a changé.
                    'previous_email' => $compte['previous_email'] ?? null,
                    'role'           => 'controller',
                    'department'     => 'Contrôle de Gestion & Finance',
                    'is_active'      => true,
                ], fn ($v) => $v !== null));

            if ($reponse->successful()) {
                return true;
            }

            Log::warning(
                "[GRC] Report refusé pour {$tenant->slug} : " . $reponse->status() . ' ' . $reponse->body()
            );
        } catch (\Throwable $e) {
            Log::warning("[GRC] Conteneur injoignable pour {$tenant->slug} : " . $e->getMessage());
        }

        return false;
    }

    /**
     * Phrase à ajouter au message rendu à l'opérateur.
     *
     * Taire un report manqué laisserait croire à un accès au portail GRC qui
     * n'existe pas — c'est ce qui rendait le défaut si difficile à voir.
     */
    public function message(?bool $reporte): string
    {
        return match ($reporte) {
            true  => " Le compte a été reporté dans le module GRC.",
            false => " Attention : le compte n'a pas pu être reporté dans le module GRC —"
                   . " il n'ouvre pas encore le portail. Vérifiez que le module est démarré,"
                   . " puis réenregistrez un mot de passe pour relancer le report.",
            null  => '',
        };
    }
}
