@extends('layouts.app')

@section('title', 'Gestion fournisseurs')
@section('page-title', 'Gestion fournisseurs')

@section('content')
    <section class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Total fournisseurs</div>
                <div class="fs-3 fw-bold">{{ $stats['total'] }}</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Avec services</div>
                <div class="fs-3 fw-bold text-success">{{ $stats['with_services'] }}</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Sans services</div>
                <div class="fs-3 fw-bold text-warning">{{ $stats['without_services'] }}</div>
            </div>
        </div>
    </section>

    <section class="soft-card p-3 p-lg-4">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="fs-5 fw-bold mb-1">Fournisseurs de services</h2>
                <p class="text-muted mb-0">Gestion des partenaires rattaches aux services VAS/SMS+.</p>
            </div>

            <a href="{{ route('operations.providers.create') }}" class="btn btn-dark">
                <i class="bi bi-building-add me-1"></i>
                Ajouter fournisseur
            </a>
        </div>

        @if (session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="GET" action="{{ route('operations.providers.index') }}" class="row g-2 mb-4">
            <div class="col-12 col-lg-10">
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    class="form-control"
                    placeholder="Recherche par nom, nationalite, ID fiscale ou adresse"
                >
            </div>
            <div class="col-12 col-lg-2 d-grid">
                <button class="btn btn-outline-dark" type="submit">
                    <i class="bi bi-search me-1"></i>
                    Rechercher
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-modern align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fournisseur</th>
                        <th>Nationalite</th>
                        <th>ID fiscale</th>
                        <th>Adresse</th>
                        <th>Services</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($providers as $provider)
                        <tr>
                            <td class="fw-semibold">{{ $provider->id }}</td>
                            <td>{{ $provider->provider_name }}</td>
                            <td>{{ $provider->nationnalite ?: '-' }}</td>
                            <td>{{ $provider->id_fiscale ?: '-' }}</td>
                            <td>{{ $provider->adresse ?: '-' }}</td>
                            <td>
                                <span class="badge text-bg-light border">{{ $provider->services_count }}</span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('operations.providers.edit', $provider) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('operations.providers.destroy', $provider) }}"
                                        onsubmit="return confirm('Confirmer la suppression de ce fournisseur ?')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                Aucun fournisseur trouve.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $providers->links('pagination::bootstrap-5') }}
        </div>
    </section>
@endsection
