<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport business VAS</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            background: #fff;
        }
        .toolbar {
            margin: 0 0 16px;
            text-align: right;
        }
        .toolbar button {
            border: 1px solid #111827;
            background: #111827;
            color: #fff;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
        }
        .header {
            border-bottom: 2px solid #1a3a6b;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header h1 {
            margin: 0;
            color: #1a3a6b;
            font-size: 24px;
        }
        .muted { color: #64748b; }
        .grid {
            display: table;
            width: 100%;
            border-spacing: 8px;
            margin-left: -8px;
        }
        .card {
            display: table-cell;
            width: 25%;
            border: 1px solid #dbe3ef;
            border-radius: 6px;
            padding: 10px;
            vertical-align: top;
        }
        .card .label {
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
        }
        .card .value {
            margin-top: 5px;
            font-size: 18px;
            font-weight: 700;
        }
        h2 {
            margin: 18px 0 8px;
            color: #1a3a6b;
            font-size: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        th, td {
            border: 1px solid #dbe3ef;
            padding: 7px 8px;
            text-align: left;
        }
        th {
            background: #eaf6fa;
            color: #0f5d73;
        }
        .right { text-align: right; }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 999px;
            background: #f1f5f9;
        }
        @media print {
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    @isset($printFallback)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Imprimer / Enregistrer PDF</button>
        </div>
    @endisset

    <div class="header">
        <h1>Rapport business VAS / SMS+</h1>
        <div class="muted">
            Periode : {{ $startOfWeek->format('d/m/Y') }} - {{ $endOfWeek->format('d/m/Y') }}
            &nbsp;|&nbsp;
            Generation : {{ $generatedAt->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <div class="label">Revenu OCC</div>
            <div class="value">{{ number_format($revenueTotal, 2, ',', ' ') }}</div>
            <div class="muted">{{ $variation >= 0 ? '+' : '' }}{{ number_format($variation, 1, ',', ' ') }}%</div>
        </div>
        <div class="card">
            <div class="label">Moyenne jour</div>
            <div class="value">{{ number_format($dailyAverage, 2, ',', ' ') }}</div>
        </div>
        <div class="card">
            <div class="label">Trafic OCC</div>
            <div class="value">{{ number_format($occTotal, 0, ',', ' ') }}</div>
        </div>
        <div class="card">
            <div class="label">Alertes ouvertes</div>
            <div class="value">{{ $stats['open'] }}</div>
            <div class="muted">{{ $stats['critical'] }} critiques</div>
        </div>
    </div>

    <h2>Comparaison MMG / OCC</h2>
    <table>
        <thead>
            <tr>
                <th>Indicateur</th>
                <th class="right">Valeur</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total MMG</td>
                <td class="right">{{ number_format($mmgTotal, 0, ',', ' ') }}</td>
            </tr>
            <tr>
                <td>Total OCC</td>
                <td class="right">{{ number_format($occTotal, 0, ',', ' ') }}</td>
            </tr>
            <tr>
                <td>Ecart</td>
                <td class="right">{{ $gap >= 0 ? '+' : '' }}{{ number_format($gap, 1, ',', ' ') }}%</td>
            </tr>
        </tbody>
    </table>

    <h2>Top services</h2>
    <table>
        <thead>
            <tr>
                <th>Service</th>
                <th>Fournisseur</th>
                <th class="right">Revenu</th>
                <th class="right">SMS+</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($topServices as $service)
                <tr>
                    <td>{{ $service['name'] }}</td>
                    <td>{{ $service['provider'] }}</td>
                    <td class="right">{{ number_format($service['revenue'], 2, ',', ' ') }}</td>
                    <td class="right">{{ number_format($service['sms'], 0, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">Aucun service disponible pour cette periode.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Alertes recentes</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Service</th>
                <th>Motif</th>
                <th class="right">Variation</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($anomalies as $anomaly)
                <tr>
                    <td>{{ $anomaly['date'] }}</td>
                    <td>{{ $anomaly['service'] }}</td>
                    <td>{{ $anomaly['motif'] }}</td>
                    <td class="right">{{ $anomaly['variation'] >= 0 ? '+' : '' }}{{ number_format($anomaly['variation'], 1, ',', ' ') }}%</td>
                    <td><span class="badge">{{ $anomaly['status'] }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Aucune alerte recente.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
