@extends('layouts.app')

@section('title', 'Modifier service')
@section('page-title', 'Modifier service')

@section('content')
    <section class="soft-card p-3 p-lg-4">
        <div class="mb-4">
            <h2 class="fs-5 fw-bold mb-1">{{ $service->service_name }}</h2>
            <p class="text-muted mb-0">Modification des parametres du service.</p>
        </div>

        <form method="POST" action="{{ route('operations.services.update', $service) }}">
            @csrf
            @method('PUT')
            @include('operations.services._form')
        </form>
    </section>
@endsection
