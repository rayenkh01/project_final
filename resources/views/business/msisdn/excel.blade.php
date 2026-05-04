@extends('layouts.app')

@section('title', 'Recherche par liste Excel des MSISDN')
@section('page-title', 'Liste Excel des MSISDN')

@section('content')
    <section class="soft-card p-3 p-lg-4 mb-4">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="fs-5 fw-bold mb-1">Recherche par fichier Excel ou CSV</h2>
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
