<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client de la console business : interroge l'API de reporting de chaque
 * établissement d'un propriétaire (wetchah_app) et consolide les données
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
     * Statistiques comparatives : par établissement, indicateurs de
     * performance + séries d'évolution, plus des KPI dérivés et des
     * classements (top/flop, plus forte hausse/baisse). Page « Statistiques ».
     */
    public function statistics(Collection $tenants, string $period): array
    {
        $rows = [];
        $labels = [];
        $unreachable = [];

        foreach ($tenants as $tenant) {
            $summary = $this->fetch($tenant, 'summary', ['period' => $period]);
            if ($summary === null) {
                $unreachable[] = $tenant->name;
                continue;
            }

            $rev      = $summary['revenue'] ?? [];
            $revTotal = (int) ($rev['total'] ?? 0);
            $revPrev  = (int) ($summary['revenue_previous']['total'] ?? 0);
            $occ      = $summary['occupancy'] ?? [];
            $rooms    = (int) ($occ['rooms_total'] ?? 0);
            $bookings = (int) ($summary['bookings']['total'] ?? 0);

            // Série d'évolution (totaux par point, en FCFA) pour le multi-courbes
            $series = $this->fetch($tenant, 'revenue', ['period' => $period]);
            $s = $series['series'] ?? null;
            $points = [];
            if ($s) {
                if (empty($labels)) { $labels = $s['labels'] ?? []; }
                $n = count($s['labels'] ?? []);
                for ($i = 0; $i < $n; $i++) {
                    $points[] = ($s['hotel'][$i] ?? 0) + ($s['restaurant'][$i] ?? 0) + ($s['shop'][$i] ?? 0);
                }
            }

            $rows[] = [
                'id'              => $tenant->id,
                'name'            => $tenant->name,
                'slug'            => $tenant->slug,
                'revenue'         => $revTotal,
                'by_pole'         => [
                    'hotel'      => (int) ($rev['hotel'] ?? 0),
                    'restaurant' => (int) ($rev['restaurant'] ?? 0),
                    'shop'       => (int) ($rev['shop'] ?? 0),
                ],
                'occupancy'       => (float) ($occ['rate'] ?? 0),
                'rooms'           => $rooms,
                'bookings'        => $bookings,
                'revenue_per_room' => $rooms > 0 ? intdiv($revTotal, $rooms) : 0,
                'avg_booking'     => $bookings > 0 ? intdiv($revTotal, $bookings) : 0,
                'trend'           => $this->trend($revTotal, $revPrev),
                'trend_raw'       => $revPrev > 0 ? round(($revTotal - $revPrev) / $revPrev * 100, 1) : ($revTotal > 0 ? 100.0 : 0.0),
                'series'          => $points,
            ];
        }

        $reachable = collect($rows);
        $count = $reachable->count();

        // KPI dérivés (moyennes de performance)
        $totalRevenue = $reachable->sum('revenue');
        $totalRooms   = $reachable->sum('rooms');
        $totalBookings = $reachable->sum('bookings');
        $derived = [
            'avg_revenue'          => $count > 0 ? intdiv($totalRevenue, $count) : 0,
            'avg_basket'           => $totalBookings > 0 ? intdiv($totalRevenue, $totalBookings) : 0,
            'avg_occupancy'        => $count > 0 ? round($reachable->avg('occupancy'), 1) : 0.0,
            'revenue_per_room'     => $totalRooms > 0 ? intdiv($totalRevenue, $totalRooms) : 0,
        ];

        // Classements
        $byRevenue = $reachable->sortByDesc('revenue')->values();
        $byTrend   = $reachable->sortByDesc('trend_raw')->values();
        $rankings = [
            'top'        => $byRevenue->first(),
            'flop'       => $count > 1 ? $byRevenue->last() : null,
            'top_riser'  => $byTrend->first(),
            'top_faller' => $count > 1 ? $byTrend->last() : null,
        ];

        return [
            'period'         => $period,
            'currency'       => 'XAF',
            'labels'         => $labels,
            'establishments' => $byRevenue->all(),
            'derived'        => $derived,
            'rankings'       => $rankings,
            'unreachable'    => $unreachable,
            'count'          => $count,
            'generated_at'   => now()->format('H:i:s'),
        ];
    }

    /**
     * Rapport financier consolidé (page Rapport / audit) : revenus,
     * encaissements par méthode, facturé/payé/dû, dépenses, écarts de caisse
     * et résultat net — agrégés sur tous les établissements du propriétaire.
     */
    public function financeReport(Collection $tenants, string $period): array
    {
        $establishments = [];
        $unreachable    = [];
        $methods        = [];   // consolidation par méthode de paiement
        $expenseItems   = [];

        $totals = [
            'revenue'   => ['hotel' => 0, 'restaurant' => 0, 'shop' => 0, 'total' => 0],
            'collected' => 0,
            'invoiced'  => 0,
            'paid'      => 0,
            'due'       => 0,
            'expenses'  => 0,
            'net'       => 0,
            'cash_discrepancy' => 0,
        ];

        foreach ($tenants as $tenant) {
            $f = $this->fetch($tenant, 'finance', ['period' => $period]);
            if ($f === null) {
                $unreachable[] = $tenant->name;
                continue;
            }

            $rev = $f['revenue'] ?? [];
            $inv = $f['invoices'] ?? [];
            $exp = $f['expenses'] ?? [];
            $collected = collect($f['payment_methods'] ?? [])->sum('total');

            $totals['revenue']['hotel']      += (int) ($rev['hotel'] ?? 0);
            $totals['revenue']['restaurant'] += (int) ($rev['restaurant'] ?? 0);
            $totals['revenue']['shop']       += (int) ($rev['shop'] ?? 0);
            $totals['revenue']['total']      += (int) ($rev['total'] ?? 0);
            $totals['collected']             += (int) $collected;
            $totals['invoiced']              += (int) ($inv['total_invoiced'] ?? 0);
            $totals['paid']                  += (int) ($inv['total_paid'] ?? 0);
            $totals['due']                   += (int) ($inv['total_due'] ?? 0);
            $totals['expenses']              += (int) ($exp['total'] ?? 0);
            $totals['net']                   += (int) ($f['net'] ?? 0);
            $totals['cash_discrepancy']      += (int) ($f['cash']['total_discrepancy'] ?? 0);

            foreach (($f['payment_methods'] ?? []) as $m) {
                $key = $m['method'] ?? 'autre';
                $methods[$key]['method'] = $key;
                $methods[$key]['count']  = ($methods[$key]['count'] ?? 0) + (int) ($m['count'] ?? 0);
                $methods[$key]['total']  = ($methods[$key]['total'] ?? 0) + (int) ($m['total'] ?? 0);
            }

            foreach (($exp['items'] ?? []) as $item) {
                $expenseItems[] = array_merge($item, ['establishment' => $tenant->name]);
            }

            $establishments[] = [
                'name'      => $tenant->name,
                'slug'      => $tenant->slug,
                'revenue'   => (int) ($rev['total'] ?? 0),
                'collected' => (int) $collected,
                'invoiced'  => (int) ($inv['total_invoiced'] ?? 0),
                'paid'      => (int) ($inv['total_paid'] ?? 0),
                'due'       => (int) ($inv['total_due'] ?? 0),
                'expenses'  => (int) ($exp['total'] ?? 0),
                'net'       => (int) ($f['net'] ?? 0),
                'cash_gap'  => (int) ($f['cash']['total_discrepancy'] ?? 0),
            ];
        }

        usort($establishments, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $paymentMethods = collect($methods)->sortByDesc('total')->values()->all();
        usort($expenseItems, fn ($a, $b) => ($b['amount'] ?? 0) <=> ($a['amount'] ?? 0));

        return [
            'period'          => $period,
            'currency'        => 'XAF',
            'totals'          => $totals,
            'payment_methods' => $paymentMethods,
            'establishments'  => $establishments,
            'expenses'        => $expenseItems,
            'unreachable'     => $unreachable,
            'count'           => count($establishments),
            'generated_at'    => now()->format('d/m/Y H:i'),
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
