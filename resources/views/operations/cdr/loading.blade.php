@extends('layouts.app')

@section('title', 'Loading CDR MMG/OCC')
@section('page-title', 'Loading CDR MMG/OCC')

@push('styles')
    <style>
        .loading-page {
            color: #0f172a;
        }

        .loading-card-title {
            color: #123b7a;
            font-size: 1rem;
            font-weight: 750;
        }

        .loading-kpi {
            min-height: 122px;
            padding: 1rem;
        }

        .loading-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .loading-blue { background: #dbeafe; color: #1d4ed8; }
        .loading-green { background: #dcfce7; color: #15803d; }
        .loading-amber { background: #fef3c7; color: #b45309; }
        .loading-red { background: #fee2e2; color: #b91c1c; }

        .loading-source-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .loading-folder {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f8fafc;
            padding: .9rem;
            height: 100%;
        }

        .loading-file-list {
            display: grid;
            gap: .55rem;
            margin-top: .75rem;
        }

        .loading-file-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .75rem;
            align-items: center;
            border-top: 1px solid #e5e7eb;
            padding-top: .55rem;
        }

        .loading-code {
            background: #111827;
            border-radius: 8px;
            color: #d1d5db;
            font-family: "Cascadia Code", Consolas, monospace;
            font-size: .84rem;
            max-height: 300px;
            overflow: auto;
            padding: 1rem;
            white-space: pre-wrap;
        }
    </style>
@endpush

@section('content')
    @php
        $totalIncoming = collect($sources)->sum('incoming_count');
        $totalProcessed = collect($sources)->sum('processed_count');
        $totalErrors = collect($sources)->sum('error_count');
        $totalFiles = collect($sources)->sum('total_files');
    @endphp

    <div class="loading-page">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 mb-3">
            <div>
                <h2 class="fs-4 fw-bold mb-1">Loading CDR MMG/OCC</h2>
                <div class="text-muted">Operations / Surveillance des fichiers CDR et des tables Oracle</div>
            </div>
        </div>

        @if ($orphanFiles !== [])
            <div class="alert alert-warning border">
                <div class="fw-semibold">Fichiers non classes dans incoming</div>
                <div class="small">
                    Ces fichiers sont dans <span class="fw-semibold">storage/app/cdr/incoming</span>.
                    Les commandes importent seulement depuis <span class="fw-semibold">incoming/mmg</span> et <span class="fw-semibold">incoming/occ</span>.
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach ($orphanFiles as $file)
                        <span class="badge text-bg-light border">{{ $file['name'] }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        <section class="row g-3 mb-3">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card loading-kpi h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Fichiers incoming</div>
                            <div class="fs-3 fw-bold">{{ $totalIncoming }}</div>
                        </div>
                        <span class="loading-icon loading-blue"><i class="bi bi-inbox"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card loading-kpi h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Fichiers processed</div>
                            <div class="fs-3 fw-bold text-success">{{ $totalProcessed }}</div>
                        </div>
                        <span class="loading-icon loading-green"><i class="bi bi-check2-circle"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card loading-kpi h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Fichiers erreur</div>
                            <div class="fs-3 fw-bold text-danger">{{ $totalErrors }}</div>
                        </div>
                        <span class="loading-icon loading-red"><i class="bi bi-exclamation-triangle"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="soft-card loading-kpi h-100">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="text-muted small fw-semibold">Total fichiers suivis</div>
                            <div class="fs-3 fw-bold">{{ $totalFiles }}</div>
                        </div>
                        <span class="loading-icon loading-amber"><i class="bi bi-folder2-open"></i></span>
                    </div>
                </div>
            </div>
        </section>

        @foreach ($sources as $source)
            <section class="soft-card p-3 p-lg-4 mb-3">
                <div class="loading-source-header mb-3">
                    <div>
                        <h3 class="loading-card-title mb-1">Chargement {{ $source['label'] }}</h3>
                        <div class="text-muted small">Suivi des fichiers et des tables Oracle {{ $source['label'] }}.</div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    @foreach (['incoming' => 'Incoming', 'processed' => 'Processed', 'error' => 'Error'] as $folderKey => $folderLabel)
                        @php($folder = $source[$folderKey])
                        <div class="col-12 col-lg-4">
                            <div class="loading-folder">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-bold">{{ $folderLabel }}</div>
                                        <div class="text-muted small text-truncate">{{ $folder['path'] }}</div>
                                    </div>
                                    <span class="badge {{ $folderKey === 'error' && $folder['count'] > 0 ? 'text-bg-danger' : 'text-bg-light border' }}">
                                        {{ $folder['count'] }}
                                    </span>
                                </div>

                                <div class="d-flex justify-content-between mt-3 small">
                                    <span>{{ $folder['size'] }}</span>
                                    <span>{{ $folder['latest'] }}</span>
                                </div>

                                <div class="loading-file-list">
                                    @forelse ($folder['files'] as $file)
                                        <div class="loading-file-row">
                                            <div class="text-truncate">
                                                <div class="fw-semibold text-truncate">{{ $file['name'] }}</div>
                                                <div class="text-muted small">{{ $file['modified_at'] }}</div>
                                            </div>
                                            <span class="text-muted small">{{ $file['size'] }}</span>
                                        </div>
                                    @empty
                                        <div class="text-muted small border-top pt-2">Aucun fichier.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>Etape</th>
                                <th class="text-end">Enregistrements</th>
                                <th>Derniere mise a jour</th>
                                <th>Etat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($source['tables'] as $table)
                                <tr>
                                    <td class="fw-semibold">{{ $table['name'] }}</td>
                                    <td>{{ $table['stage'] }}</td>
                                    <td class="text-end">{{ $table['records'] }}</td>
                                    <td>{{ $table['last_update'] }}</td>
                                    <td>
                                        @if ($table['status'] === 'ok')
                                            <span class="badge text-bg-success">OK</span>
                                        @else
                                            <span class="badge text-bg-danger">Erreur</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <section class="soft-card p-3 p-lg-4">
            <h3 class="loading-card-title mb-3">
                <i class="bi bi-terminal me-2"></i>
                Logs import CDR
            </h3>

            @if ($logPreview === [])
                <div class="text-muted">Aucun log disponible pour le moment.</div>
            @else
                <pre class="loading-code mb-0">@foreach ($logPreview as $line){{ $line }}
@endforeach</pre>
            @endif
        </section>
    </div>
@endsection
