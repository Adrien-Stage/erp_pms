<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client de la console business : interroge l'API de reporting de chaque
 * établissement d'un propriétaire (meka_template) et consolide les données
 * pour la vue 360°. Chaque établissement calcule SES propres chiffres
 * (source unique de vérité) ; ce service agrège entre établissements,
 * classe, et remonte les alertes avec attribution.
 */
class BusinessReportingClient
{
    /** URL interne Docker de l'app d'un établissement. */
    private function baseUrl(Tenant $tenant): string
    {
        $container = $tenant->docker_app_container ?: ('meka-erp-' . $tenant->slug . '-app');
        return 'http://' . $container;
    }

    /**
     * Appel authentifié d'un endpoint reporting d'un établissement.
     * Retourne le JSON décodé, ou null si injoignable / non provisionné.
     */
    public function fetch(Tenant $tenant, string $path, array $query = []): ?array
    {
        if (!$tenant->provisioned_at) {
            return null;
        }

        $secret = (string) config('provisioning.reporting_secret');
        if ($secret === '') {
            Log::warning('[BusinessReporting] REPORTING_SECRET non configuré côté pms.');
            return null;
        }

        try {
            $response = Http::withToken($secret)
                ->timeout(4)
                ->acceptJson()
                ->get($this->baseUrl($tenant) . '/api/reporting/' . ltrim($path, '/'), $query);

            return $response->successful() ? (array) $response->json() : null;
        } catch (\Throwable $e) {
            Log::info("[BusinessReporting] {$tenant->slug} /{$path} injoignable : " . $e->getMessage());
            return null;
        }
    }

    /**
     * Vue d'ensemble 360° consolidée pour tous les établissements d'un
     * propriétaire, sur une période donnée.
     */
    public function overview(Collection $tenants, string $period): array
    {
        $establishments = [];
        $unreachable    = [];
        $alerts         = [];

        $totals = [
            'revenue'          => ['hotel' => 0, 'restaurant' => 0, 'shop' => 0, 'total' => 0],
            'revenue_previous' => 0,
            'rooms_total'      => 0,
            'rooms_occupied'   => 0,
            'bookings'         => 0,
            'staff'            => 0,
            'cash_discrepancy' => 0,
        ];

        foreach ($tenants as $tenant) {
            $summary = $this->fetch($tenant, 'summary', ['period' => $period]);

            if ($summary === null) {
                $unreachable[] = $tenant->name;
                $establishments[] = [
                    'id'        => $tenant->id,
                    'name'      => $tenant->name,
                    'slug'      => $tenant->slug,
                    'reachable' => false,
                ];
                continue;
            }

            $rev     = $summary['revenue'] ?? [];
            $revPrev = ($summary['revenue_previous']['total'] ?? 0);
            $occ     = $summary['occupancy'] ?? [];
            $cash    = $summary['cash'] ?? [];

            $totals['revenue']['hotel']      += (int) ($rev['hotel'] ?? 0);
            $totals['revenue']['restaurant'] += (int) ($rev['restaurant'] ?? 0);
            $totals['revenue']['shop']       += (int) ($rev['shop'] ?? 0);
            $totals['revenue']['total']      += (int) ($rev['total'] ?? 0);
            $totals['revenue_previous']      += (int) $revPrev;
            $totals['rooms_total']           += (int) ($occ['rooms_total'] ?? 0);
            $totals['rooms_occupied']        += (int) ($occ['rooms_occupied'] ?? 0);
            $totals['bookings']              += (int) ($summary['bookings']['total'] ?? 0);
            $totals['staff']                 += (int) ($summary['staff']['total'] ?? 0);
            $totals['cash_discrepancy']      += (int) ($cash['total_discrepancy'] ?? 0);

            $establishments[] = [
                'id'          => $tenant->id,
                'name'        => $tenant->name,
                'slug'        => $tenant->slug,
                'reachable'   => true,
                'revenue'     => (int) ($rev['total'] ?? 0),
                'revenue_trend' => $this->trend((int) ($rev['total'] ?? 0), (int) $revPrev),
                'occupancy'   => (float) ($occ['rate'] ?? 0),
                'bookings'    => (int) ($summary['bookings']['total'] ?? 0),
                'cash_gap'    => (int) ($cash['total_discrepancy'] ?? 0),
                'alerts'      => (int) ($summary['alerts_count'] ?? 0),
            ];

            // Remontée des alertes locales, avec attribution à l'établissement
            $detail = $this->fetch($tenant, 'alerts', ['period' => $period]);
            foreach (($detail['alerts'] ?? []) as $a) {
                $alerts[] = array_merge($a, [
                    'establishment' => $tenant->name,
                    'slug'          => $tenant->slug,
                ]);
            }
        }

        // Classement des établissements par revenu décroissant (les joignables d'abord)
        usort($establishments, function ($a, $b) {
            if (($a['reachable'] ?? false) !== ($b['reachable'] ?? false)) {
                return ($b['reachable'] ?? false) <=> ($a['reachable'] ?? false);
            }
            return ($b['revenue'] ?? 0) <=> ($a['revenue'] ?? 0);
        });

        // Tri des alertes par sévérité
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($alerts, fn ($a, $b) => ($order[$a['severity'] ?? 'low'] ?? 3) <=> ($order[$b['severity'] ?? 'low'] ?? 3));

        return [
            'period'          => $period,
            'currency'        => 'XAF',
            'totals'          => $totals,
            'revenue_trend'   => $this->trend($totals['revenue']['total'], $totals['revenue_previous']),
            'occupancy_rate'  => $totals['rooms_total'] > 0
                                    ? round($totals['rooms_occupied'] / $totals['rooms_total'] * 100, 1)
                                    : 0.0,
            'establishments'  => $establishments,
            'alerts'          => $alerts,
            'unreachable'     => $unreachable,
            'count_total'     => $tenants->count(),
            'count_reachable' => count($tenants) - count($unreachable),
            'generated_at'    => now()->format('H:i:s'),
        ];
    }

    /**
     * Variation en % entre la valeur courante et la précédente.
     */
    private function trend(int $current, int $previous): array
    {
        if ($previous <= 0) {
            return ['pct' => $current > 0 ? 100.0 : 0.0, 'direction' => $current > 0 ? 'up' : 'flat'];
        }

        $pct = round(($current - $previous) / $previous * 100, 1);

        return [
            'pct'       => abs($pct),
            'direction' => $pct > 0.5 ? 'up' : ($pct < -0.5 ? 'down' : 'flat'),
        ];
    }
}
