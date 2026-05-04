@extends('layouts.app')

@section('title', 'Gestion des services')
@section('page-title', 'Gestion des services')

@section('content')
    <section class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Total services</div>
                <div class="fs-3 fw-bold">{{ $stats['total'] }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Fournisseurs</div>
                <div class="fs-3 fw-bold text-primary">{{ $stats['providers'] }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Avec tarif</div>
                <div class="fs-3 fw-bold text-success">{{ $stats['priced'] }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Avec keyword</div>
                <div class="fs-3 fw-bold text-warning">{{ $stats['keywords'] }}</div>
            </div>
        </div>
    </section>

    <section class="soft-card p-3 p-lg-4">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="fs-5 fw-bold mb-1">Catalogue des services VAS/SMS+</h2>
                <p class="text-muted mb-0">Gestion des services, short codes, keywords, tarifs et fournisseurs.</p>
            </div>

            <a href="{{ route('operations.services.create') }}" class="btn btn-dark">
                <i class="bi bi-plus-circle me-1"></i>
                Ajouter service
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

        <form method="GET" action="{{ route('operations.services.index') }}" class="row g-2 mb-4">
            <div class="col-12 col-lg-10">
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    class="form-control"
                    placeholder="Recherche par service, short code, keyword, type ou fournisseur"
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
                        <th>Service</th>
                        <th>Short code</th>
                        <th>Keyword</th>
                        <th>Type</th>
                        <th>Prix</th>
                        <th>Fournisseur</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td class="fw-semibold">{{ $service->id }}</td>
                            <td>{{ $service->service_name }}</td>
                            <td>
                                <span class="badge text-bg-light border">{{ $service->short_code }}</span>
                            </td>
                            <td>{{ $service->keyword ?: '-' }}</td>
                            <td>{{ $service->type ?: '-' }}</td>
                            <td>
                                {{ $service->price !== null ? number_format((float) $service->price, 3, ',', ' ') . ' TND' : '-' }}
                            </td>
                            <td>{{ $service->provider?->provider_name ?: '-' }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('operations.services.edit', $service) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('operations.services.destroy', $service) }}"
                                        onsubmit="return confirm('Confirmer la suppression de ce service ?')"
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
                            <td colspan="8" class="text-center text-muted py-5">
                                Aucun service trouve.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $services->links('pagination::bootstrap-5') }}
        </div>
    </section>
@endsection
