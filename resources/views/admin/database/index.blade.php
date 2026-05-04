@extends('layouts.app')

@section('title', 'Gestion Base de Donnees')
@section('page-title', 'Gestion Base de Donnees')

@push('styles')
    <style>
        .db-page {
            color: #0f172a;
        }

        .db-card-title {
            color: #123b7a;
            font-size: 1rem;
            font-weight: 750;
        }

        .db-kpi {
            min-height: 128px;
            padding: 1rem;
        }

        .db-kpi-icon,
        .db-flow-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .db-tone-blue { background: #dbeafe; color: #1d4ed8; }
        .db-tone-green { background: #dcfce7; color: #15803d; }
        .db-tone-amber { background: #fef3c7; color: #b45309; }
        .db-tone-red { background: #fee2e2; color: #b91c1c; }

        .db-flow {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: .75rem;
        }

        .db-flow-step {
            border: 1px solid #bfdbfe;
            background: #f8fbff;
            border-radius: 8px;
            min-height: 168px;
            padding: 1rem;
        }

        .db-flow-step:nth-child(1) { border-color: #facc15; background: #fefce8; }
        .db-flow-step:nth-child(2) { border-color: #86efac; background: #f0fdf4; }
        .db-flow-step:nth-child(6) { border-color: #c4b5fd; background: #faf5ff; }

        .db-code {
            background: #111827;
            border-radius: 8px;
            color: #d1d5db;
            font-family: "Cascadia Code", Consolas, monospace;
            font-size: .84rem;
            margin: 0;
            max-height: 280px;
            overflow: auto;
            padding: 1rem;
            white-space: pre-wrap;
        }

        .db-folder-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .db-folder {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f8fafc;
            padding: .9rem;
        }

        .db-action-bar {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        @media (max-width: 1199.98px) {
            .db-flow {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .db-flow,
            .db-folder-grid {
                grid-template-columns: 1fr;
            }

            .db-action-bar .btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $operationResult = session('operationResult') ?? session('importResult');
        $flowSteps = [
            ['title' => 'Connexion FTP', 'icon' => 'bi-hdd-network', 'text' => 'Recherche des fichiers CDR MMG / OCC.'],
            ['title' => 'Telechargement', 'icon' => 'bi-cloud-arrow-down', 'text' => 'Depot des nouveaux fichiers dans incoming.'],
            ['title' => 'Chargement TEMP', 'icon' => 'bi-database-down', 'text' => 'Insertion dans RA_T_TMP_MMG et RA_T_TMP_OCC.'],
            ['title' => 'Transformation', 'icon' => 'bi-gear-wide-connected', 'text' => 'Nettoyage et remplissage des tables DETAIL.'],
            ['title' => 'Agregation', 'icon' => 'bi-bar-chart-line', 'text' => 'Calcul des volumes et montants dans AGG.'],
            ['title' => 'Historique & Logs', 'icon' => 'bi-clipboard-check', 'text' => 'Suivi du resultat dans les logs Scheduler.'],
        ];
    @endphp

    <div class="db-page">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 mb-3">
            <div>
                <h2 class="fs-4 fw-bold mb-1">Gestion Base de Donnees</h2>
                <div class="text-muted">Administration / Gerer BD / Laravel Scheduler</div>
                <div class="mt-2">
                    <span class="badge {{ $etlState['badge'] }}">
                        ETL {{ $etlState['status'] }}
                    </span>
                    @if ($etlState['paused_at'])
                        <span class="text-muted small ms-2">Depuis {{ $etlState['paused_at'] }}</span>
                    @endif
                </div>
            </div>

            <div class="db-action-bar align-self-start align-self-xl-center">
                <form method="POST" action="{{ route('admin.database.etl.toggle') }}">
                    @csrf
                    <input type="hidden" name="action" value="{{ $etlState['paused'] ? 'resume' : 'pause' }}">
                    <button type="submit" class="btn {{ $etlState['paused'] ? 'btn-success' : 'btn-warning' }}">
                        <i class="bi {{ $etlState['paused'] ? 'bi-play-fill' : 'bi-pause-fill' }} me-1"></i>
                        {{ $etlState['paused'] ? 'Demarrer ETL' : 'Pause ETL' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.database.import') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary" @disabled($etlState['paused'])>
                        <i class="bi bi-play-fill me-1"></i>
                        Lancer ETL maintenant
                    </button>
                </form>

                <a href="{{ route('admin.database.index') }}" class="btn btn-light border">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Actualiser statistiques
                </a>

                <form
                    method="POST"
                    action="{{ route('admin.database.cleanup') }}"
                    onsubmit="return confirm('Supprimer les donnees TMP et les fichiers processed de plus de 30 jours ?')"
                >
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash3 me-1"></i>
                        Nettoyer anciennes donnees
                    </button>
                </form>
            </div>
        </div>

        @if ($etlState['paused'])
            <div class="alert alert-warning border mb-3">
                <div class="fw-semibold">ETL en pause</div>
                <div class="small">
                    Le Scheduler continue a verifier la planification, mais la commande
                    <span class="fw-semibold">cdr:import</span> ne charge plus les fichiers tant que l'ETL reste en pause.
                </div>
            </div>
        @endif

        @if ($operationResult)
            <div class="alert {{ $operationResult['ok'] ? 'alert-success' : 'alert-danger' }} border mb-3">
                <div class="fw-semibold">{{ $operationResult['message'] }}</div>
                @if (! empty($operationResult['output']))
                    <pre class="db-code mt-3">{{ $operationResult['output'] }}</pre>
                @endif
            </div>
        @endif

        <section class="row g-3 mb-3">
            @foreach ($summary as $item)
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="soft-card db-kpi h-100">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <div class="text-muted small fw-semibold">{{ $item['label'] }}</div>
                                <div class="fs-5 fw-bold mt-2">{{ $item['value'] }}</div>
                            </div>
                            <span class="db-kpi-icon db-tone-{{ $item['tone'] }}">
                                <i class="bi {{ $item['icon'] }}"></i>
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="row g-3 mb-3">
            <div class="col-12 col-xl-7">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="db-card-title mb-3">
                        <i class="bi bi-clock-history me-2"></i>
                        Laravel Scheduler
                    </h3>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-5 text-muted">Statut</dt>
                                <dd class="col-7 fw-semibold">{{ $scheduler['status'] }}</dd>
                                <dt class="col-5 text-muted">Etat ETL</dt>
                                <dd class="col-7">
                                    <span class="badge {{ $etlState['badge'] }}">{{ $etlState['status'] }}</span>
                                </dd>
                                <dt class="col-5 text-muted">Frequence</dt>
                                <dd class="col-7">{{ $scheduler['frequency'] }}</dd>
                                <dt class="col-5 text-muted">Nettoyage</dt>
                                <dd class="col-7">{{ $scheduler['cleanup_frequency'] }}</dd>
                                <dt class="col-5 text-muted">Protection</dt>
                                <dd class="col-7">{{ $scheduler['overlap'] }}</dd>
                            </dl>
                        </div>
                        <div class="col-12 col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-5 text-muted">Cron serveur</dt>
                                <dd class="col-7">{{ $scheduler['server_cron'] }}</dd>
                                <dt class="col-5 text-muted">Log</dt>
                                <dd class="col-7">{{ $scheduler['log_path'] }}</dd>
                                <dt class="col-5 text-muted">Log nettoyage</dt>
                                <dd class="col-7">{{ $scheduler['cleanup_log_path'] }}</dd>
                                <dt class="col-5 text-muted">Dernier run</dt>
                                <dd class="col-7">{{ $scheduler['last_run_at'] }}</dd>
                                <dt class="col-5 text-muted">Flag pause</dt>
                                <dd class="col-7">{{ $etlState['flag_path'] }}</dd>
                            </dl>
                        </div>
                    </div>

                    <pre class="db-code mt-3">{{ $scheduler['command'] }}
{{ $scheduler['cleanup_command'] }}</pre>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="db-card-title mb-3">
                        <i class="bi bi-folder2-open me-2"></i>
                        Flux fichiers
                    </h3>

                    <div class="db-folder-grid">
                        @foreach ($folderStats as $folder)
                            <div class="db-folder">
                                <div class="fw-semibold">{{ $folder['label'] }}</div>
                                <div class="text-muted small text-truncate">{{ $folder['path'] }}</div>
                                <div class="d-flex justify-content-between mt-2">
                                    <span>{{ $folder['files'] }} fichiers</span>
                                    <span class="fw-semibold">{{ $folder['size'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="soft-card p-3 p-lg-4 mb-3">
            <h3 class="db-card-title mb-3">
                <i class="bi bi-diagram-3 me-2"></i>
                Processus ETL execute par le Scheduler
            </h3>

            <div class="db-flow">
                @foreach ($flowSteps as $step)
                    <div class="db-flow-step">
                        <span class="db-flow-icon db-tone-blue mb-3">
                            <i class="bi {{ $step['icon'] }}"></i>
                        </span>
                        <div class="fw-bold mb-2">{{ $loop->iteration }}. {{ $step['title'] }}</div>
                        <div class="small text-muted">{{ $step['text'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="row g-3 mb-3">
            <div class="col-12 col-xl-7">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="db-card-title mb-3">
                        <i class="bi bi-table me-2"></i>
                        Statistiques des tables
                    </h3>

                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Table</th>
                                    <th>Source</th>
                                    <th>Etape</th>
                                    <th class="text-end">Enregistrements</th>
                                    <th>Derniere mise a jour</th>
                                    <th>Etat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tableStats as $table)
                                    <tr>
                                        <td class="fw-semibold">{{ $table['name'] }}</td>
                                        <td>{{ $table['source'] }}</td>
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
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="db-card-title mb-3">
                        <i class="bi bi-terminal me-2"></i>
                        Logs Scheduler
                    </h3>

                    @if ($logPreview === [])
                        <div class="text-muted">Aucun log disponible pour le moment.</div>
                    @else
                        <pre class="db-code">@foreach ($logPreview as $line){{ $line }}
@endforeach</pre>
                    @endif
                </div>
            </div>
        </section>

        <section class="soft-card p-3 p-lg-4">
            <h3 class="db-card-title mb-3">
                <i class="bi bi-list-check me-2"></i>
                Historique des executions
            </h3>

            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Processus</th>
                            <th>Statut</th>
                            <th>Duree</th>
                            <th>Fichiers</th>
                            <th class="text-end">Enreg.</th>
                            <th>Erreurs</th>
                            <th>Fichier BD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($executions as $execution)
                            <tr>
                                <td>{{ $execution['date'] }}</td>
                                <td>{{ $execution['process'] }}</td>
                                <td><span class="badge text-bg-success">{{ $execution['status'] }}</span></td>
                                <td>{{ $execution['duration'] }}</td>
                                <td>{{ $execution['files'] }}</td>
                                <td class="text-end">{{ $execution['records'] }}</td>
                                <td>{{ $execution['errors'] }}</td>
                                <td class="text-truncate" style="max-width: 260px;">{{ $execution['file'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Aucune execution trouvee dans les tables DETAIL.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
