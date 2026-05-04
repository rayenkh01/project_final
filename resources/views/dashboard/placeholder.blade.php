@extends('layouts.app')

@section('title', $title)
@section('page-title', $title)

@section('content')
    <div class="soft-card p-4 p-lg-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <span class="badge text-bg-info mb-3">{{ $roleLabel }}</span>
                <h2 class="fw-bold mb-3">{{ $title }}</h2>
                <p class="text-muted mb-0">
                    Module prêt à connecter aux tables Oracle et aux traitements métier VAS/SMS+.
                </p>
            </div>
            <div class="col-lg-4">
                <div class="border rounded-3 p-3 bg-light">
                    <div class="small text-muted mb-2">Tables prévues</div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-secondary">ra_t_agg_mmg</span>
                        <span class="badge text-bg-secondary">ra_t_agg_occ</span>
                        <span class="badge text-bg-secondary">services</span>
                        <span class="badge text-bg-secondary">service_provider</span>
                        <span class="badge text-bg-secondary">alerts</span>
                        <span class="badge text-bg-secondary">users</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
