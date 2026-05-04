@extends('layouts.app')

@section('title', 'Ajouter service')
@section('page-title', 'Ajouter service')

@section('content')
    <section class="soft-card p-3 p-lg-4">
        <div class="mb-4">
            <h2 class="fs-5 fw-bold mb-1">Nouveau service VAS/SMS+</h2>
            <p class="text-muted mb-0">Creation d'un service rattache optionnellement a un fournisseur.</p>
        </div>

        <form method="POST" action="{{ route('operations.services.store') }}">
            @csrf
            @include('operations.services._form')
        </form>
    </section>
@endsection
