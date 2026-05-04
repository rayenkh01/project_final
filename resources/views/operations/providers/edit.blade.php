@extends('layouts.app')

@section('title', 'Modifier fournisseur')
@section('page-title', 'Modifier fournisseur')

@section('content')
    <section class="soft-card p-3 p-lg-4">
        <div class="mb-4">
            <h2 class="fs-5 fw-bold mb-1">{{ $provider->provider_name }}</h2>
            <p class="text-muted mb-0">Modification des informations du fournisseur.</p>
        </div>

        <form method="POST" action="{{ route('operations.providers.update', $provider) }}">
            @csrf
            @method('PUT')
            @include('operations.providers._form')
        </form>
    </section>
@endsection
