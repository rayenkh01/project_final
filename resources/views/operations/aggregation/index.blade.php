@extends('layouts.app')

@section('title', 'Agregation')
@section('page-title', 'Agregation CDR')

@push('styles')
    <style>
        .agg-page {
            color: #0f172a;
        }

        .agg-card-title {
            color: #123b7a;
            font-size: 1rem;
            font-weight: 750;
        }

        .agg-kpi {
            min-height: 132px;
            padding: 1rem;
        }

        .agg-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .agg-blue { background: #dbeafe; color: #1d4ed8; }
        .agg-green { background: #dcfce7; color: #15803d; }
        .agg-amber { background: #fef3c7; color: #b45309; }
        .agg-red { background: #fee2e2; color: #b91c1c; }
        .agg-teal { background: #ccfbf1; color: #0f766e; }
        .agg-slate { background: #e2e8f0; color: #334155; }

        .agg-bar {
            height: 8px;
            background: #eef2f7;
            border-radius: 999px;
            overflow: hidden;
            min-width: 120px;
        }

        .agg-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #00a6b2, #2563eb);
        }

        .agg-hour-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: .75rem;
        }

        .agg-hour {
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            border-radius: 8px;
            padding: .85rem;
        }

        .agg-filter {
            display: grid;
            grid-template-columns: minmax(130px, 180px) repeat(2, minmax(150px, 190px)) auto auto;
            gap: .75rem;
            align-items: end;
        }

        @media (max-width: 1199.98px) {
            .agg-hour-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .agg-filter {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .agg-hour-grid,
            .agg-filter {
                grid-template-columns: 1fr;
            }

            .agg-filter .btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $maxDaily = max(1, collect($dailyRows)->map(fn ($row) => $row['mmg_cdr'] + $row['occ_cdr'])->max() ?: 1);
        $maxHourly = max(1, collect($hourlyRows)->map(fn ($row) => $row['mmg_cdr'] + $row['occ_cdr'])->max() ?: 1);
        $maxTop = max(1, collect($topRows)->max('cdr_count') ?: 1);
        $sourceLabels = ['all' => 'MMG + OCC', 'mmg' => 'MMG', 'occ' => 'OCC'];
    @endphp

    <div class="agg-page">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 mb-3">
            <div>
                <h2 class="fs-4 fw-bold mb-1">Agregation CDR</h2>
                <div class="text-muted">Operations / Suivi des tables AGG MMG et OCC</div>
            </div>
            <span class="badge text-bg-light border align-self-start align-self-xl-center">
                Source: {{ $sourceLabels[$filters['source']] ?? 'MMG + OCC' }}
            </span>
        </div>

        @if ($errors !== [])
            <div class="alert alert-danger border">
                <div class="fw-semibold">Certaines donnees AGG ne sont pas disponibles.</div>
                <ul class="mb-0 mt-2">
                    @foreach ($errors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="soft-card p-3 p-lg-4 mb-3">
            <form method="GET" action="{{ route('operations.aggregation.index') }}" class="agg-filter">
                <div>
                    <label class="form-label fw-semibold">Source</label>
                    <select name="source" class="form-select">
                        <option value="all" @selected($filters['source'] === 'all')>MMG + OCC</option>
                        <option value="mmg" @selected($filters['source'] === 'mmg')>MMG</option>
                        <option value="occ" @selected($filters['source'] === 'occ')>OCC</option>
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold">Date debut</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control">
                </div>
                <div>
                    <label class="form-label fw-semibold">Date fin</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control">
                </div>
                <button class="btn btn-dark" type="submit">
                    <i class="bi bi-filter me-1"></i>
                    Filtrer
                </button>
                <a href="{{ route('operations.aggregation.index') }}" class="btn btn-light border">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                    Reset
                </a>
            </form>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-12 col-sm-6 col-xl-2">
                <div class="soft-card agg-kpi h-100">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Total CDR</div>
                            <div class="fs-4 fw-bold mt-2">{{ number_format($summary['total_cdr'], 0, ',', ' ') }}</div>
                        </div>
                        <span class="agg-icon agg-blue"><i class="bi bi-stack"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-2">
                <div class="soft-card agg-kpi h-100">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">CDR MMG</div>
                            <div class="fs-4 fw-bold mt-2">{{ number_format($summary['mmg_cdr'], 0, ',', ' ') }}</div>
                        </div>
                        <span class="agg-icon agg-teal"><i class="bi bi-chat-dots"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-2">
                <div class="soft-card agg-kpi h-100">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">CDR OCC</div>
                            <div class="fs-4 fw-bold mt-2">{{ number_format($summary['occ_cdr'], 0, ',', ' ') }}</div>
                        </div>
                        <span class="agg-icon agg-green"><i class="bi bi-phone"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-2">
                <div class="soft-card agg-kpi h-100">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Montant OCC</div>
                            <div class="fs-5 fw-bold mt-2">{{ number_format($summary['occ_amount'], 3, ',', ' ') }}</div>
                        </div>
                        <span class="agg-icon agg-amber"><i class="bi bi-cash-stack"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-2">
                <div class="soft-card agg-kpi h-100">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Lignes AGG</div>
                            <div class="fs-4 fw-bold mt-2">{{ number_format($summary['agg_rows'], 0, ',', ' ') }}</div>
                        </div>
                        <span class="agg-icon agg-slate"><i class="bi bi-table"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-2">
                <div class="soft-card agg-kpi h-100">
                    <div class="text-muted small fw-semibold">Derniere agregation</div>
                    <div class="fw-bold mt-2">{{ $summary['latest_agg'] }}</div>
                </div>
            </div>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-12 col-xl-7">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="agg-card-title mb-3">
                        <i class="bi bi-calendar3 me-2"></i>
                        Agregation par jour
                    </h3>

                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">MMG</th>
                                    <th class="text-end">OCC</th>
                                    <th class="text-end">Total</th>
                                    <th>Volume</th>
                                    <th class="text-end">Montant OCC</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dailyRows as $row)
                                    @php($total = $row['mmg_cdr'] + $row['occ_cdr'])
                                    <tr>
                                        <td class="fw-semibold">{{ $row['date'] }}</td>
                                        <td class="text-end">{{ number_format($row['mmg_cdr'], 0, ',', ' ') }}</td>
                                        <td class="text-end">{{ number_format($row['occ_cdr'], 0, ',', ' ') }}</td>
                                        <td class="text-end fw-semibold">{{ number_format($total, 0, ',', ' ') }}</td>
                                        <td>
                                            <div class="agg-bar"><span style="width: {{ round(($total / $maxDaily) * 100) }}%"></span></div>
                                        </td>
                                        <td class="text-end">{{ number_format($row['amount'], 3, ',', ' ') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Aucune agregation journaliere trouvee.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="agg-card-title mb-3">
                        <i class="bi bi-clock me-2"></i>
                        Repartition par heure
                    </h3>

                    <div class="agg-hour-grid">
                        @forelse ($hourlyRows as $row)
                            @php($total = $row['mmg_cdr'] + $row['occ_cdr'])
                            <div class="agg-hour">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <span class="fw-semibold">{{ str_pad((string) $row['hour'], 2, '0', STR_PAD_LEFT) }}h</span>
                                    <span class="text-muted small">{{ number_format($total, 0, ',', ' ') }}</span>
                                </div>
                                <div class="agg-bar"><span style="width: {{ round(($total / $maxHourly) * 100) }}%"></span></div>
                                <div class="text-muted small mt-2">
                                    MMG {{ number_format($row['mmg_cdr'], 0, ',', ' ') }} / OCC {{ number_format($row['occ_cdr'], 0, ',', ' ') }}
                                </div>
                            </div>
                        @empty
                            <div class="text-muted">Aucune repartition horaire trouvee.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-12 col-xl-5">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="agg-card-title mb-3">
                        <i class="bi bi-trophy me-2"></i>
                        Top services / keywords
                    </h3>

                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Service / keyword</th>
                                    <th class="text-end">CDR</th>
                                    <th>Poids</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topRows as $row)
                                    <tr>
                                        <td><span class="badge text-bg-light border">{{ $row['source'] }}</span></td>
                                        <td class="fw-semibold">{{ $row['dimension'] }}</td>
                                        <td class="text-end">{{ number_format($row['cdr_count'], 0, ',', ' ') }}</td>
                                        <td>
                                            <div class="agg-bar"><span style="width: {{ round(($row['cdr_count'] / $maxTop) * 100) }}%"></span></div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Aucun top disponible.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-7">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="agg-card-title mb-3">
                        <i class="bi bi-database-check me-2"></i>
                        Etat des tables AGG
                    </h3>

                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Table</th>
                                    <th class="text-end">Lignes</th>
                                    <th class="text-end">CDR</th>
                                    <th class="text-end">Montant</th>
                                    <th>Derniere maj</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($tableStats as $table)
                                    <tr>
                                        <td><span class="badge text-bg-light border">{{ $table['source'] }}</span></td>
                                        <td class="fw-semibold">{{ $table['table'] }}</td>
                                        <td class="text-end">{{ number_format($table['rows'], 0, ',', ' ') }}</td>
                                        <td class="text-end">{{ number_format($table['cdr_count'], 0, ',', ' ') }}</td>
                                        <td class="text-end">{{ number_format($table['amount'], 3, ',', ' ') }}</td>
                                        <td>{{ $table['latest'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Aucune table AGG disponible.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="soft-card p-3 p-lg-4">
            <h3 class="agg-card-title mb-3">
                <i class="bi bi-list-check me-2"></i>
                Dernieres lignes agregees
            </h3>

            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Service / keyword</th>
                            <th>Event</th>
                            <th>Call</th>
                            <th class="text-end">CDR</th>
                            <th class="text-end">Montant</th>
                            <th>Creation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentRows as $row)
                            <tr>
                                <td><span class="badge text-bg-light border">{{ $row['source'] }}</span></td>
                                <td>{{ $row['start_date'] }}</td>
                                <td>{{ $row['start_hour'] }}</td>
                                <td class="fw-semibold">{{ $row['dimension'] }}</td>
                                <td>{{ $row['event_type'] }}</td>
                                <td>{{ $row['call_type'] }}</td>
                                <td class="text-end">{{ number_format($row['cdr_count'], 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($row['amount'], 3, ',', ' ') }}</td>
                                <td>{{ $row['created_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Aucune ligne agregee trouvee.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
