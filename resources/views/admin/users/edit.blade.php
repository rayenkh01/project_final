@extends('layouts.app')

@section('title', 'Modifier utilisateur')
@section('page-title', 'Modifier utilisateur')

@section('content')
    <section class="soft-card p-3 p-lg-4">
        <div class="mb-4">
            <h2 class="fs-5 fw-bold mb-1">{{ $user->email }}</h2>
            <p class="text-muted mb-0">Modification des informations du compte utilisateur.</p>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            @include('admin.users._form')
        </form>
    </section>
@endsection
