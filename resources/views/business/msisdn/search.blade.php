@extends('layouts.app')

@section('title', 'Recherche MSISDN')
@section('page-title', 'Recherche MSISDN')

@section('content')
    <section class="soft-card p-3 p-lg-4 mb-4">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="fs-5 fw-bold mb-1">Recherche des services et traces par MSISDN</h2>
                <p class="text-muted mb-0">Interrogation des flux MMG et OCC a partir d'un numero client.</p>
            </div>
            <span class="badge text-bg-light border">Sources: ra_t_tmp_mmg / ra_t_tmp_occ</span>
        </div>

        <form method="GET" action="{{ route('business.msisdn.search') }}" class="row g-2">
            <div class="col-12 col-lg-10">
                <input
                    type="search"
                    name="msisdn"
                    value="{{ $searchInput }}"
                    class="form-control"
                    placeholder="Exemple: 21699889095"
                    inputmode="numeric"
                >
            </div>
            <div class="col-12 col-lg-2 d-grid">
                <button class="btn btn-dark" type="submit">
                    <i class="bi bi-search me-1"></i>
                    Rechercher
                </button>
            </div>
        </form>

        @if ($searchError)
            <div class="alert alert-danger mt-3 mb-0">
                {{ $searchError }}
            </div>
        @elseif ($searchAttempted && $normalizedMsisdn)
            <div class="alert alert-info mt-3 mb-0">
                Resultats pour le MSISDN <strong>{{ $normalizedMsisdn }}</strong>.
            </div>
        @endif
    </section>

    @if ($searchAttempted && $normalizedMsisdn)
        <section class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card p-3 h-100">
                    <div class="text-muted small">Occurrences MMG</div>
                    <div class="fs-3 fw-bold">{{ $stats['mmg_count'] }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card p-3 h-100">
                    <div class="text-muted small">Occurrences OCC</div>
                    <div class="fs-3 fw-bold text-primary">{{ $stats['occ_count'] }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card p-3 h-100">
                    <div class="text-muted small">Montant OCC</div>
                    <div class="fs-3 fw-bold text-success">{{ number_format((float) $stats['occ_amount'], 3, ',', ' ') }}</div>
                    <div class="small text-muted">TND</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card p-3 h-100">
                    <div class="text-muted small">Derniere activite</div>
                    <div class="fw-bold">{{ $stats['last_activity'] ?: '-' }}</div>
                    <div class="small text-muted">{{ $stats['sources'] }} source(s) trouvee(s)</div>
                </div>
            </div>
        </section>

        <section class="soft-card p-3 p-lg-4 mb-4">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="fs-5 fw-bold mb-1">Activites recentes</h2>
                    <p class="text-muted mb-0">Apercu consolide des dernieres traces MMG et OCC.</p>
                </div>
                <span class="badge text-bg-secondary">{{ count($recentActivities) }} lignes</span>
            </div>

            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Date brute</th>
                            <th>Destination</th>
                            <th>Libelle</th>
                            <th>Indice service</th>
                            <th class="text-end">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentActivities as $activity)
                            <tr>
                                <td>
                                    <span class="badge {{ $activity['source'] === 'MMG' ? 'text-bg-info' : 'text-bg-warning' }}">
                                        {{ $activity['source'] }}
                                    </span>
                                </td>
                                <td>{{ $activity['activity_time'] ?: '-' }}</td>
                                <td>{{ $activity['b_msisdn'] ?: '-' }}</td>
                                <td>{{ $activity['activity_label'] ?: '-' }}</td>
                                <td>{{ $activity['service_hint'] ?: '-' }}</td>
                                <td class="text-end">
                                    {{ $activity['amount'] !== null ? number_format((float) $activity['amount'], 3, ',', ' ') . ' TND' : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Aucune activite trouvee pour ce MSISDN.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-xl-5">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="fs-5 fw-bold mb-1">Resume MMG</h2>
                            <p class="text-muted mb-0">Regroupement par service type, evenement et destination.</p>
                        </div>
                        <span class="badge text-bg-info">{{ count($mmgSummary) }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Service type</th>
                                    <th>Evenement</th>
                                    <th>Destination</th>
                                    <th>Subscriber</th>
                                    <th class="text-end">CDR</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($mmgSummary as $row)
                                    <tr>
                                        <td>{{ $row['service_type'] ?: '-' }}</td>
                                        <td>{{ $row['event_type_orig'] ?: '-' }}</td>
                                        <td>{{ $row['b_msisdn'] ?: '-' }}</td>
                                        <td>{{ $row['subscriber_type'] ?: '-' }}</td>
                                        <td class="text-end fw-semibold">{{ $row['cdr_count'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            Aucun resultat MMG.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-7">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="fs-5 fw-bold mb-1">Resume OCC</h2>
                            <p class="text-muted mb-0">Regroupement par service detecte, partenaire et montant.</p>
                        </div>
                        <span class="badge text-bg-warning">{{ count($occSummary) }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Short code</th>
                                    <th>Keyword</th>
                                    <th>Subscriber</th>
                                    <th class="text-end">CDR</th>
                                    <th class="text-end">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($occSummary as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row['service_name'] ?: '-' }}</td>
                                        <td>{{ $row['short_code'] ?: '-' }}</td>
                                        <td>{{ $row['keyword'] ?: ($row['partner'] ?: '-') }}</td>
                                        <td>{{ $row['subscriber_type'] ?: '-' }}</td>
                                        <td class="text-end">{{ $row['cdr_count'] }}</td>
                                        <td class="text-end fw-semibold">{{ number_format((float) $row['total_amount'], 3, ',', ' ') }} TND</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Aucun resultat OCC.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section id="liste-msisdn" class="soft-card p-3 p-lg-4 mt-4 mb-4">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="fs-5 fw-bold mb-1">Recherche par liste Excel ou CSV</h2>
                <p class="text-muted mb-0">Chargez une liste de MSISDN pour obtenir un resume MMG/OCC par numero.</p>
            </div>
            <span class="badge text-bg-light border">Formats: xlsx / csv / txt</span>
        </div>

        <form method="POST" action="{{ route('business.msisdn.excel.search') }}" enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="col-12 col-lg-9">
                <input type="file" name="file" class="form-control" accept=".xlsx,.csv,.txt">
                <div class="form-text">Utilisez idealement une colonne simple contenant les MSISDN.</div>
            </div>
            <div class="col-12 col-lg-3 d-grid">
                <button class="btn btn-dark" type="submit">
                    <i class="bi bi-upload me-1"></i>
                    Charger la liste
                </button>
            </div>
        </form>

        @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0">
                {{ $errors->first() }}
            </div>
        @endif
    </section>

    @if ($uploadedCount > 0)
        <section class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card p-3 h-100">
                    <div class="text-muted small">Fichier traite</div>
                    <div class="fw-bold">{{ $fileName }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card p-3 h-100">
                    <div class="text-muted small">MSISDN charges</div>
                    <div class="fs-3 fw-bold">{{ $uploadedCount }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card p-3 h-100">
                    <div class="text-muted small">MSISDN trouves</div>
                    <div class="fs-3 fw-bold text-primary">{{ $summary['matched_msisdns'] }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card p-3 h-100">
                    <div class="text-muted small">Montant OCC total</div>
                    <div class="fs-3 fw-bold text-success">{{ number_format((float) $summary['occ_amount'], 3, ',', ' ') }}</div>
                    <div class="small text-muted">TND</div>
                </div>
            </div>
        </section>

        <section class="soft-card p-3 p-lg-4">
            <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="fs-5 fw-bold mb-1">Resultats consolides</h2>
                    <p class="text-muted mb-0">Synthese des occurrences MMG, OCC et du montant OCC par numero.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-info">Hits MMG: {{ $summary['mmg_hits'] }}</span>
                    <span class="badge text-bg-warning">Hits OCC: {{ $summary['occ_hits'] }}</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>MSISDN</th>
                            <th class="text-end">MMG</th>
                            <th class="text-end">OCC</th>
                            <th class="text-end">Montant OCC</th>
                            <th>Derniere activite</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($results as $row)
                            @php
                                $totalHits = (int) $row['mmg_count'] + (int) $row['occ_count'];
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $row['msisdn'] }}</td>
                                <td class="text-end">{{ $row['mmg_count'] }}</td>
                                <td class="text-end">{{ $row['occ_count'] }}</td>
                                <td class="text-end">{{ number_format((float) $row['occ_amount'], 3, ',', ' ') }} TND</td>
                                <td>{{ $row['last_activity'] ?: '-' }}</td>
                                <td>
                                    @if ($totalHits > 0)
                                        <span class="badge text-bg-success">Trouve</span>
                                    @else
                                        <span class="badge text-bg-secondary">Aucune trace</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Aucun resultat a afficher.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
