@php
    $isEdit = $service->exists;
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <label for="service_name" class="form-label">Nom du service</label>
        <input
            id="service_name"
            type="text"
            name="service_name"
            value="{{ old('service_name', $service->service_name) }}"
            class="form-control @error('service_name') is-invalid @enderror"
            maxlength="100"
            required
        >
        @error('service_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-3">
        <label for="short_code" class="form-label">Short code</label>
        <input
            id="short_code"
            type="text"
            name="short_code"
            value="{{ old('short_code', $service->short_code) }}"
            class="form-control @error('short_code') is-invalid @enderror"
            maxlength="20"
            placeholder="Ex: 85000"
            required
        >
        @error('short_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-3">
        <label for="keyword" class="form-label">Keyword</label>
        <input
            id="keyword"
            type="text"
            name="keyword"
            value="{{ old('keyword', $service->keyword) }}"
            class="form-control @error('keyword') is-invalid @enderror"
            maxlength="50"
            placeholder="Ex: QUIZ"
        >
        @error('keyword')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-4">
        <label for="type" class="form-label">Type</label>
        <input
            id="type"
            type="text"
            name="type"
            value="{{ old('type', $service->type) }}"
            class="form-control @error('type') is-invalid @enderror"
            maxlength="50"
            list="serviceTypes"
            placeholder="Ex: SMS+"
        >
        <datalist id="serviceTypes">
            @foreach ($types as $type)
                <option value="{{ $type }}"></option>
            @endforeach
        </datalist>
        @error('type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-4">
        <label for="price" class="form-label">Prix TND</label>
        <input
            id="price"
            type="number"
            name="price"
            value="{{ old('price', $service->price) }}"
            class="form-control @error('price') is-invalid @enderror"
            min="0"
            step="0.001"
            placeholder="Ex: 0.500"
        >
        @error('price')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-4">
        <label for="provider_id" class="form-label">Fournisseur</label>
        <select id="provider_id" name="provider_id" class="form-select @error('provider_id') is-invalid @enderror">
            <option value="">Sans fournisseur</option>
            @foreach ($providers as $provider)
                <option value="{{ $provider->id }}" @selected((string) old('provider_id', $service->provider_id) === (string) $provider->id)>
                    {{ $provider->provider_name }}
                </option>
            @endforeach
        </select>
        @error('provider_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @if ($providers->isEmpty())
            <div class="form-text">Aucun fournisseur dans service_provider pour le moment.</div>
        @endif
    </div>
</div>

<div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
    <a href="{{ route('operations.services.index') }}" class="btn btn-outline-secondary">
        Annuler
    </a>
    <button class="btn btn-dark" type="submit">
        <i class="bi bi-check2-circle me-1"></i>
        {{ $isEdit ? 'Enregistrer' : 'Ajouter' }}
    </button>
</div>
