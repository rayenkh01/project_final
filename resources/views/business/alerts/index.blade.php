@extends('layouts.app')

@section('title', 'Alertes business')
@section('page-title', 'Alertes et incidents')

@push('styles')
    <style>
        .alert-stat {
            min-height: 132px;
            padding: 1rem;
        }

        .alert-stat .icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #e0f2fe;
            color: #0369a1;
        }

        .alert-status {
            border-radius: 999px;
            padding: .3rem .55rem;
            font-size: .74rem;
        }

        .alert-status.resolved {
            background: #dcfce7;
            color: #166534;
        }

        .alert-status.open {
            background: #fef3c7;
            color: #92400e;
        }

        .severity-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            display: inline-block;
        }

        .severity-dot.critique { background: #dc2626; }
        .severity-dot.elevee { background: #ea580c; }
        .severity-dot.moyenne { background: #d97706; }
        .severity-dot.faible { background: #0891b2; }
    </style>
@endpush

@section('content')
    @php
        $comparison = $trafficComparison;
        $colors = ['#00a6b2', '#2563eb', '#f59e0b', '#10b981', '#ef4444'];
        $maxServiceCount = max(1, max(array_column($services, 'count') ?: [0]));
    @endphp

    <section class="soft-card p-3 p-lg-4 mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge text-bg-light border">Periode: {{ $periodLabel }}</span>
                    <span class="badge {{ $comparison['has_gap'] ? 'text-bg-danger' : 'text-bg-success' }}">
                        Ecart MMG/OCC: {{ number_format($comparison['gap'], 1, ',', ' ') }}%
                    </span>
                </div>
                <h2 class="fs-4 fw-bold mb-1">Suivi des alertes SMS+</h2>
                <p class="text-muted mb-0">Controle des ecarts MMG/OCC, croissance des services et incidents ouverts.</p>
            </div>
            <div class="text-lg-end">
                <div class="text-muted small">Trafic semaine</div>
                <div class="fs-4 fw-bold">{{ number_format($comparison['occ_total'], 0, ',', ' ') }} SMS+</div>
                <a href="{{ route('business.alerts.pdf') }}" class="btn btn-outline-dark btn-sm mt-2" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i>
                    Exporter PDF
                </a>
            </div>
        </div>
    </section>

    <section class="row g-3 g-xl-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="soft-card alert-stat">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <div class="text-muted small fw-semibold">Alertes semaine</div>
                        <div class="fs-2 fw-bold">{{ $stats['total'] }}</div>
                    </div>
                    <span class="icon"><i class="bi bi-bell"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="soft-card alert-stat">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <div class="text-muted small fw-semibold">Critiques</div>
                        <div class="fs-2 fw-bold text-danger">{{ $stats['critical'] }}</div>
                    </div>
                    <span class="icon"><i class="bi bi-exclamation-octagon"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="soft-card alert-stat">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <div class="text-muted small fw-semibold">En investigation</div>
                        <div class="fs-2 fw-bold">{{ $stats['open'] }}</div>
                    </div>
                    <span class="icon"><i class="bi bi-search"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="soft-card alert-stat">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <div class="text-muted small fw-semibold">Resolues aujourd'hui</div>
                        <div class="fs-2 fw-bold text-success">{{ $stats['resolved_today'] }}</div>
                    </div>
                    <span class="icon"><i class="bi bi-check2-circle"></i></span>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 g-xl-4 mb-4">
        <div class="col-12 col-xl-7">
            <div class="soft-card chart-box h-100">
                <h2 class="fs-5 fw-bold mb-1">Comparaison MMG / OCC</h2>
                <p class="text-muted small mb-3">Volumes journaliers sur les 7 derniers jours.</p>
                <div class="chart-canvas-wrap">
                    <canvas id="trafficComparisonChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="soft-card chart-box h-100">
                <h2 class="fs-5 fw-bold mb-3">Top services sous surveillance</h2>
                <div class="vstack gap-3">
                    @forelse ($services as $service)
                        @php
                            $width = round(($service['count'] / $maxServiceCount) * 100);
                        @endphp
                        <div>
                            <div class="d-flex justify-content-between gap-3 mb-1">
                                <div>
                                    <div class="fw-semibold">{{ $service['name'] }}</div>
                                    <div class="text-muted small">{{ $service['provider'] }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold">{{ number_format($service['count'], 0, ',', ' ') }}</div>
                                    <span class="badge {{ $service['alert'] ? 'text-bg-warning' : 'text-bg-light border' }}">
                                        {{ $service['growth'] >= 0 ? '+' : '' }}{{ number_format($service['growth'], 1, ',', ' ') }}%
                                    </span>
                                </div>
                            </div>
                            <div class="fallback-track"><span style="width: {{ $width }}%"></span></div>
                        </div>
                    @empty
                        <div class="text-muted">Aucun service detecte pour cette periode.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 g-xl-4">
        <div class="col-12 col-xl-5">
            <div class="soft-card chart-box h-100">
                <h2 class="fs-5 fw-bold mb-3">Evolution top services</h2>
                <div class="chart-canvas-wrap compact">
                    <canvas id="serviceTrafficChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="soft-card p-3 p-lg-4 h-100">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <h2 class="fs-5 fw-bold mb-0">Historique recent</h2>
                    <span class="badge text-bg-light border">{{ count($anomalies) }} lignes</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Severite</th>
                                <th class="text-end">SMS+</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($anomalies as $anomaly)
                                @php
                                    $severityClass = strtolower(str_replace('E', 'e', $anomaly['severity']));
                                    $statusClass = $anomaly['status'] === 'resolved' ? 'resolved' : 'open';
                                @endphp
                                <tr>
                                    <td class="text-muted">{{ $anomaly['date'] }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $anomaly['service'] }}</div>
                                        <div class="text-muted small">{{ $anomaly['motif'] }}</div>
                                    </td>
                                    <td>
                                        <span class="severity-dot {{ $severityClass }} me-2"></span>
                                        {{ $anomaly['severity'] }}
                                    </td>
                                    <td class="text-end">{{ number_format($anomaly['sms'], 0, ',', ' ') }}</td>
                                    <td>
                                        <span class="alert-status {{ $statusClass }}">
                                            {{ $anomaly['status'] === 'resolved' ? 'Resolue' : 'Investigation' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Aucune alerte recente.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const comparison = @json($trafficComparison);
        const topTraffic = @json($topTraffic);
        const palette = @json($colors);

        if (typeof Chart !== 'undefined') {
            new Chart(document.getElementById('trafficComparisonChart'), {
                type: 'line',
                data: {
                    labels: comparison.labels,
                    datasets: [
                        {
                            label: 'MMG',
                            data: comparison.mmg,
                            borderColor: '#00a6b2',
                            backgroundColor: 'rgba(0, 166, 178, .12)',
                            tension: .35,
                            fill: true
                        },
                        {
                            label: 'OCC',
                            data: comparison.occ,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, .10)',
                            tension: .35,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { grid: { color: '#eef2f7' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            new Chart(document.getElementById('serviceTrafficChart'), {
                type: 'bar',
                data: {
                    labels: topTraffic.labels,
                    datasets: topTraffic.series.map((item, index) => ({
                        label: item.name,
                        data: item.data,
                        backgroundColor: palette[index % palette.length],
                        borderRadius: 6
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { stacked: false, grid: { color: '#eef2f7' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    </script>
@endpush
