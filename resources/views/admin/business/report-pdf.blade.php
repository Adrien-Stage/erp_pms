@php
    $t = $report['totals'];
    $cur = $report['currency'];
    $money = fn ($c) => number_format(($c ?? 0) / 100, 0, ',', ' ');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1e293b; font-size: 11px; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 2px; color: #4f46e5; }
        .sub { color: #64748b; font-size: 10px; margin-bottom: 16px; }
        h2 { font-size: 13px; margin: 18px 0 6px; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th { background: #4f46e5; color: #fff; text-align: left; padding: 5px 7px; font-size: 9px; text-transform: uppercase; }
        td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .num { text-align: right; }
        .kpi { width: 100%; margin-bottom: 6px; }
        .kpi td { border: none; padding: 6px 10px; }
        .kpi .box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
        .kpi .label { font-size: 9px; color: #64748b; text-transform: uppercase; }
        .kpi .value { font-size: 15px; font-weight: bold; color: #0f172a; }
        .bar-track { background: #f1f5f9; border-radius: 3px; height: 8px; width: 100%; }
        .bar { background: #4f46e5; height: 8px; border-radius: 3px; }
        .neg { color: #dc2626; }
        .foot { margin-top: 24px; color: #94a3b8; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    <h1>Rapport financier consolidé</h1>
    <div class="sub">
        Propriétaire : {{ $ownerName }} · Période : {{ $report['period'] }} · {{ $report['count'] }} établissement(s) · Généré le {{ $report['generated_at'] }}
    </div>

    {{-- KPI --}}
    <table class="kpi">
        <tr>
            <td class="box"><div class="label">Revenu total</div><div class="value">{{ $money($t['revenue']['total']) }} {{ $cur }}</div></td>
            <td class="box"><div class="label">Encaissé</div><div class="value">{{ $money($t['collected']) }} {{ $cur }}</div></td>
            <td class="box"><div class="label">Dépenses</div><div class="value">{{ $money($t['expenses']) }} {{ $cur }}</div></td>
            <td class="box"><div class="label">Résultat net</div><div class="value">{{ $money($t['net']) }} {{ $cur }}</div></td>
        </tr>
    </table>

    <h2>Revenus par pôle</h2>
    @php
        $poles = [['Hôtel', $t['revenue']['hotel']], ['Restaurant', $t['revenue']['restaurant']], ['Boutique', $t['revenue']['shop']]];
        $maxPole = max(1, $t['revenue']['hotel'], $t['revenue']['restaurant'], $t['revenue']['shop']);
    @endphp
    <table>
        @foreach($poles as $p)
            <tr>
                <td style="width:22%">{{ $p[0] }}</td>
                <td style="width:55%"><div class="bar-track"><div class="bar" style="width: {{ $maxPole > 0 ? round($p[1] / $maxPole * 100) : 0 }}%"></div></div></td>
                <td class="num" style="width:23%"><strong>{{ $money($p[1]) }}</strong> {{ $cur }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Facturation & créances</h2>
    <table>
        <tr><th>Facturé</th><th>Payé</th><th>Créances (dû)</th><th>Écart de caisse</th></tr>
        <tr>
            <td>{{ $money($t['invoiced']) }} {{ $cur }}</td>
            <td>{{ $money($t['paid']) }} {{ $cur }}</td>
            <td class="{{ $t['due'] > 0 ? 'neg' : '' }}">{{ $money($t['due']) }} {{ $cur }}</td>
            <td class="{{ $t['cash_discrepancy'] < 0 ? 'neg' : '' }}">{{ $money($t['cash_discrepancy']) }} {{ $cur }}</td>
        </tr>
    </table>

    <h2>Encaissements par méthode</h2>
    <table>
        <tr><th>Méthode</th><th class="num">Nombre</th><th class="num">Total</th></tr>
        @forelse($report['payment_methods'] as $m)
            <tr><td>{{ ucfirst(str_replace('_', ' ', $m['method'])) }}</td><td class="num">{{ $m['count'] }}</td><td class="num">{{ $money($m['total']) }} {{ $cur }}</td></tr>
        @empty
            <tr><td colspan="3" style="color:#94a3b8">Aucun encaissement sur la période.</td></tr>
        @endforelse
    </table>

    <h2>Détail par établissement</h2>
    <table>
        <tr><th>Établissement</th><th class="num">Revenu</th><th class="num">Encaissé</th><th class="num">Dû</th><th class="num">Dépenses</th><th class="num">Net</th></tr>
        @foreach($report['establishments'] as $e)
            <tr>
                <td>{{ $e['name'] }}</td>
                <td class="num">{{ $money($e['revenue']) }}</td>
                <td class="num">{{ $money($e['collected']) }}</td>
                <td class="num {{ $e['due'] > 0 ? 'neg' : '' }}">{{ $money($e['due']) }}</td>
                <td class="num">{{ $money($e['expenses']) }}</td>
                <td class="num"><strong>{{ $money($e['net']) }}</strong></td>
            </tr>
        @endforeach
    </table>

    @if(count($report['expenses']) > 0)
        <h2>Dépenses</h2>
        <table>
            <tr><th>Établissement</th><th class="num">Montant</th><th>Motif</th><th>Par</th><th>Date</th></tr>
            @foreach(array_slice($report['expenses'], 0, 30) as $e)
                <tr>
                    <td>{{ $e['establishment'] ?? '' }}</td>
                    <td class="num">{{ $money($e['amount'] ?? 0) }} {{ $cur }}</td>
                    <td>{{ $e['reason'] ?? '' }}</td>
                    <td>{{ $e['user'] ?? '' }}</td>
                    <td>{{ $e['at'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="foot">MEKA ERP — Rapport généré automatiquement. Les montants sont en {{ $cur }}.</div>
</body>
</html>
