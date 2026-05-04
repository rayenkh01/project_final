@extends('layouts.app')

@section('title', 'Ajouter fournisseur')
@section('page-title', 'Ajouter fournisseur')

@section('content')
    <section class="soft-card p-3 p-lg-4">
        <div class="mb-4">
            <h2 class="fs-5 fw-bold mb-1">Nouveau fournisseur</h2>
            <p class="text-muted mb-0">Creation d'un fournisseur de services VAS/SMS+.</p>
        </div>

        <form method="POST" action="{{ route('operations.providers.store') }}">
            @csrf
            @include('operations.providers._form')
        </form>
    </section>
@endsection
