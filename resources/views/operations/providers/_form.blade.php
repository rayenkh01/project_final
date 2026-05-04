@php
    $isEdit = $provider->exists;
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <label for="provider_name" class="form-label">Nom fournisseur</label>
        <input
            id="provider_name"
            type="text"
            name="provider_name"
            value="{{ old('provider_name', $provider->provider_name) }}"
            class="form-control @error('provider_name') is-invalid @enderror"
            maxlength="100"
            required
        >
        @error('provider_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-3">
        <label for="nationnalite" class="form-label">Nationalite</label>
        <input
            id="nationnalite"
            type="text"
            name="nationnalite"
            value="{{ old('nationnalite', $provider->nationnalite) }}"
            class="form-control @error('nationnalite') is-invalid @enderror"
            maxlength="100"
            placeholder="Ex: Tunisienne"
        >
        @error('nationnalite')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-3">
        <label for="id_fiscale" class="form-label">ID fiscale</label>
        <input
            id="id_fiscale"
            type="text"
            name="id_fiscale"
            value="{{ old('id_fiscale', $provider->id_fiscale) }}"
            class="form-control @error('id_fiscale') is-invalid @enderror"
            maxlength="50"
        >
        @error('id_fiscale')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="adresse" class="form-label">Adresse</label>
        <textarea
            id="adresse"
            name="adresse"
            class="form-control @error('adresse') is-invalid @enderror"
            maxlength="200"
            rows="3"
        >{{ old('adresse', $provider->adresse) }}</textarea>
        @error('adresse')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
    <a href="{{ route('operations.providers.index') }}" class="btn btn-outline-secondary">
        Annuler
    </a>
    <button class="btn btn-dark" type="submit">
        <i class="bi bi-check2-circle me-1"></i>
        {{ $isEdit ? 'Enregistrer' : 'Ajouter' }}
    </button>
</div>
