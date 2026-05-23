@extends('layouts.app')

@section('title', 'Utilisateurs')
@section('page-title', 'Gestion des utilisateurs')

@section('content')
    <section class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Total utilisateurs</div>
                <div class="fs-3 fw-bold">{{ $stats['total'] }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Administrateurs</div>
                <div class="fs-3 fw-bold text-danger">{{ $stats['admin'] }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Analystes Business</div>
                <div class="fs-3 fw-bold text-primary">{{ $stats['business'] }}</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Analystes Operationnels</div>
                <div class="fs-3 fw-bold text-success">{{ $stats['operation'] }}</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl">
            <div class="soft-card p-3 h-100">
                <div class="text-muted small">Invitations en attente</div>
                <div class="fs-3 fw-bold text-warning">{{ $stats['pending'] }}</div>
            </div>
        </div>
    </section>

    <section class="soft-card p-3 p-lg-4">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="fs-5 fw-bold mb-1">Utilisateurs Oracle</h2>
                <p class="text-muted mb-0">Ajout, modification et suppression des comptes autorises.</p>
            </div>

            <a href="{{ route('admin.users.create') }}" class="btn btn-dark">
                <i class="bi bi-person-plus me-1"></i>
                Ajouter utilisateur
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

        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 mb-4">
            <div class="col-12 col-lg-10">
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    class="form-control"
                    placeholder="Recherche par email, direction, role ou telephone"
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
                        <th>Email</th>
                        <th>Direction</th>
                        <th>Role</th>
                        <th>Statut</th>
                        <th>Telephone</th>
                        <th>Date creation</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="fw-semibold">{{ $user->id }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->direction ?: '-' }}</td>
                            <td>
                                <span class="badge text-bg-light border">
                                    {{ $roles[$user->role] ?? \App\Models\User::roleLabel($user->role) }}
                                </span>
                            </td>
                            <td>
                                @if ($user->hasPendingInvitation())
                                    <span class="badge text-bg-warning">En attente</span>
                                @else
                                    <span class="badge text-bg-success">Actif</span>
                                @endif
                            </td>
                            <td>{{ $user->tel ?: '-' }}</td>
                            <td>
                                {{ $user->created_at ? \Illuminate\Support\Carbon::parse($user->created_at)->format('d/m/Y') : '-' }}
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.destroy', $user) }}"
                                        onsubmit="return confirm('Confirmer la suppression de cet utilisateur ?')"
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
                                Aucun utilisateur trouve.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $users->links('pagination::bootstrap-5') }}
        </div>
    </section>
@endsection
