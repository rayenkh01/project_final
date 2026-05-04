@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard principal')

@push('styles')
    <style>
        .dashboard-export-pdf {
            transition: background-color .18s ease, border-color .18s ease, color .18s ease;
        }

        .dashboard-export-pdf:hover,
        .dashboard-export-pdf:focus,
        .dashboard-export-pdf:active {
            background: #dc2626;
            border-color: #dc2626;
            color: #fff;
        }
    </style>
@endpush

@section('content')
    @php
        $maxServiceRevenue = max(array_column($topServices, 'revenue'));
        $maxDailyRevenue = max($revenueEvolution['daily']['values']);
        $chartColors = ['#00a6b2', '#2563eb', '#f59e0b', '#10b981', '#ef4444'];
    @endphp

    <section class="soft-card p-3 p-lg-4 mb-4">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge text-bg-info">Demo statique</span>
                    <span class="badge text-bg-light border">MMG / OCC</span>
                    <span class="badge text-bg-light border">Oracle ready</span>
                </div>
                <h2 class="fs-4 fw-bold mb-1">Synthese revenus VAS / SMS+</h2>
                <p class="text-muted mb-0">Vue consolidee des revenus, volumes SMS+, services, fournisseurs et alertes.</p>
            </div>

            <div class="d-flex flex-column gap-3 align-items-stretch align-items-xl-end">
                <button type="button" class="btn btn-outline-dark dashboard-export-pdf align-self-xl-end">
                    <i class="bi bi-file-earmark-pdf me-1"></i>
                    Exporter PDF
                </button>

                <div class="stat-strip">
                    <div class="stat-strip-item">
                        <div class="text-muted small">Moyenne jour</div>
                        <div class="fw-bold fs-5">165 729 TND</div>
                    </div>
                    <div class="stat-strip-item">
                        <div class="text-muted small">Part top 5</div>
                        <div class="fw-bold fs-5">65,3%</div>
                    </div>
                    <div class="stat-strip-item">
                        <div class="text-muted small">Tendance mois</div>
                        <div class="fw-bold fs-5 text-success">+5,4%</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 g-xl-4 mb-4">
        @foreach ($kpis as $kpi)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card kpi-card h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">{{ $kpi['label'] }}</div>
                            <div class="display-6 fs-2 fw-bold mt-2">{{ $kpi['value'] }}</div>
                            <div class="text-muted small">{{ $kpi['unit'] }}</div>
                        </div>
                        <div class="kpi-icon {{ $kpi['tone'] }}">
                            <i class="bi {{ $kpi['icon'] }}"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="badge text-bg-light border">{{ $kpi['trend'] }}</span>
                        <span class="text-muted small ms-2">vs periode precedente</span>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>{{ $kpi['description'] }}</span>
                            <span>{{ $kpi['progress'] }}%</span>
                        </div>
                        <div class="metric-progress">
                            <span style="width: {{ $kpi['progress'] }}%"></span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="row g-3 g-xl-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="soft-card chart-box h-100">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="fs-5 fw-bold mb-1">Evolution des revenus</h2>
                        <p class="text-muted small mb-0">Vue jour / mois</p>
                    </div>
                    <div class="btn-group" role="group" aria-label="Periode revenus">
                        <button type="button" class="btn btn-sm btn-dark active" data-period="daily">Jour</button>
                        <button type="button" class="btn btn-sm btn-outline-dark" data-period="monthly">Mois</button>
                    </div>
                </div>

                <div class="chart-canvas-wrap mb-3">
                    <canvas id="revenueTrendChart"></canvas>
                    <div class="chart-fallback" data-chart-fallback="revenue">
                        @foreach ($revenueEvolution['daily']['labels'] as $index => $label)
                            @php
                                $value = $revenueEvolution['daily']['values'][$index];
                                $width = round(($value / $maxDailyRevenue) * 100);
                            @endphp
                            <div class="fallback-row">
                                <span class="text-muted">{{ $label }}</span>
                                <div class="fallback-track"><span style="width: {{ $width }}%"></span></div>
                                <strong>{{ number_format($value, 0, ',', ' ') }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="soft-card chart-box h-100">
                <h2 class="fs-5 fw-bold mb-3">Top 5 services par revenus</h2>
                <div class="chart-canvas-wrap compact mb-3">
                    <canvas id="topServicesChart"></canvas>
                </div>
                <div class="chart-fallback">
                    @foreach ($topServices as $service)
                        @php
                            $width = round(($service['revenue'] / $maxServiceRevenue) * 100);
                        @endphp
                        <div class="fallback-row">
                            <span class="fw-semibold">{{ $service['name'] }}</span>
                            <div class="fallback-track"><span style="width: {{ $width }}%"></span></div>
                            <strong>{{ number_format($service['revenue'], 0, ',', ' ') }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 g-xl-4">
        <div class="col-12 col-xl-5">
            <div class="soft-card chart-box h-100">
                <h2 class="fs-5 fw-bold mb-3">Repartition par fournisseur</h2>
                <div class="chart-canvas-wrap compact mb-3">
                    <canvas id="providerRevenueChart"></canvas>
                </div>
                <div class="provider-list">
                    @foreach ($providerRevenue['labels'] as $index => $provider)
                        @php
                            $value = $providerRevenue['values'][$index];
                            $color = $chartColors[$index % count($chartColors)];
                        @endphp
                        <div>
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <span class="fw-semibold">
                                    <span class="provider-dot me-2" style="background: {{ $color }}"></span>{{ $provider }}
                                </span>
                                <span class="text-muted small">{{ $value }}%</span>
                            </div>
                            <div class="fallback-track">
                                <span style="width: {{ $value }}%; background: {{ $color }}"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="soft-card p-3 p-lg-4 h-100">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <h2 class="fs-5 fw-bold mb-0">Services et alertes</h2>
                    <span class="badge text-bg-danger">{{ count($activeAlerts) }} actives</span>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Fournisseur</th>
                                <th class="text-end">Revenu</th>
                                <th class="text-end">SMS+</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topServices as $service)
                                <tr>
                                    <td class="fw-semibold">{{ $service['name'] }}</td>
                                    <td class="text-muted">{{ $service['provider'] }}</td>
                                    <td class="text-end">{{ number_format($service['revenue'], 0, ',', ' ') }} TND</td>
                                    <td class="text-end">{{ number_format($service['sms'], 0, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="vstack gap-2">
                    @foreach ($activeAlerts as $alert)
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded-3 p-3">
                            <div>
                                <span class="badge-severity text-bg-warning me-2">{{ $alert['severity'] }}</span>
                                <span class="fw-semibold">{{ $alert['message'] }}</span>
                            </div>
                            <span class="text-muted small">{{ $alert['time'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const revenueEvolution = @json($revenueEvolution);
        const topServices = @json($topServices);
        const providerRevenue = @json($providerRevenue);
        const chartColors = @json($chartColors);

        const moneyFormatter = new Intl.NumberFormat('fr-FR', {
            maximumFractionDigits: 0
        });

        if (typeof Chart === 'undefined') {
            document.querySelectorAll('canvas').forEach((canvas) => canvas.remove());
        } else {
            document.querySelectorAll('[data-chart-fallback="revenue"]').forEach((fallback) => {
                fallback.style.display = 'none';
            });

            const revenueChart = new Chart(document.getElementById('revenueTrendChart'), {
                type: 'line',
                data: {
                    labels: revenueEvolution.daily.labels,
                    datasets: [{
                        label: 'Revenus TND',
                        data: revenueEvolution.daily.values,
                        borderColor: '#00a6b2',
                        backgroundColor: (context) => {
                            const chart = context.chart;
                            const {ctx, chartArea} = chart;

                            if (!chartArea) {
                                return 'rgba(0, 166, 178, .12)';
                            }

                            const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            gradient.addColorStop(0, 'rgba(0, 166, 178, .28)');
                            gradient.addColorStop(1, 'rgba(0, 166, 178, 0)');

                            return gradient;
                        },
                        borderWidth: 3,
                        fill: true,
                        tension: .38,
                        pointRadius: 4,
                        pointBackgroundColor: '#00a6b2'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${moneyFormatter.format(context.parsed.y)} TND`
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: (value) => `${moneyFormatter.format(value)}`
                            },
                            grid: { color: '#eef2f7' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            document.querySelectorAll('[data-period]').forEach((button) => {
                button.addEventListener('click', () => {
                    const period = button.dataset.period;

                    document.querySelectorAll('[data-period]').forEach((item) => {
                        item.classList.toggle('btn-dark', item === button);
                        item.classList.toggle('btn-outline-dark', item !== button);
                        item.classList.toggle('active', item === button);
                    });

                    revenueChart.data.labels = revenueEvolution[period].labels;
                    revenueChart.data.datasets[0].data = revenueEvolution[period].values;
                    revenueChart.update();
                });
            });

            new Chart(document.getElementById('topServicesChart'), {
                type: 'bar',
                data: {
                    labels: topServices.map((service) => service.name),
                    datasets: [{
                        label: 'Revenus TND',
                        data: topServices.map((service) => service.revenue),
                        backgroundColor: chartColors,
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${moneyFormatter.format(context.parsed.x)} TND`
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                callback: (value) => `${moneyFormatter.format(value)}`
                            },
                            grid: { color: '#eef2f7' }
                        },
                        y: {
                            grid: { display: false }
                        }
                    }
                }
            });

            new Chart(document.getElementById('providerRevenueChart'), {
                type: 'doughnut',
                data: {
                    labels: providerRevenue.labels,
                    datasets: [{
                        data: providerRevenue.values,
                        backgroundColor: chartColors,
                        borderColor: '#fff',
                        borderWidth: 4,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.label}: ${context.parsed}%`
                            }
                        }
                    },
                    cutout: '62%'
                }
            });
        }
    </script>
@endpush
