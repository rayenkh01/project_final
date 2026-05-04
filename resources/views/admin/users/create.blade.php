@extends('layouts.app')

@section('title', 'Ajouter utilisateur')
@section('page-title', 'Ajouter utilisateur')

@section('content')
    <section class="soft-card p-3 p-lg-4">
        <div class="mb-4">
            <h2 class="fs-5 fw-bold mb-1">Nouveau compte</h2>
            <p class="text-muted mb-0">Le mot de passe sera chiffre avant enregistrement dans Oracle.</p>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            @include('admin.users._form')
        </form>
    </section>
@endsection
