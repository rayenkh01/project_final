@php
    $isEdit = $user->exists;
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <label for="email" class="form-label">Email</label>
        <input
            id="email"
            type="email"
            name="email"
            value="{{ old('email', $user->email) }}"
            class="form-control @error('email') is-invalid @enderror"
            maxlength="150"
            required
        >
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-6">
        <label for="password" class="form-label">
            Mot de passe
            @if ($isEdit)
                <span class="text-muted small">(laisser vide pour ne pas changer)</span>
            @endif
        </label>
        <input
            id="password"
            type="password"
            name="password"
            class="form-control @error('password') is-invalid @enderror"
            minlength="6"
            maxlength="120"
            {{ $isEdit ? '' : 'required' }}
        >
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-4">
        <label for="role" class="form-label">Role</label>
        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role ?: \App\Models\User::ORACLE_ROLE_BUSINESS) === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('role')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-4">
        <label for="direction" class="form-label">Direction</label>
        <input
            id="direction"
            type="text"
            name="direction"
            value="{{ old('direction', $user->direction) }}"
            class="form-control @error('direction') is-invalid @enderror"
            maxlength="100"
            placeholder="Ex: IT, VAS, Finance"
        >
        @error('direction')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-4">
        <label for="tel" class="form-label">Telephone</label>
        <input
            id="tel"
            type="text"
            name="tel"
            value="{{ old('tel', $user->tel) }}"
            class="form-control @error('tel') is-invalid @enderror"
            maxlength="20"
            placeholder="Ex: 12345678"
        >
        @error('tel')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
        Annuler
    </a>
    <button class="btn btn-dark" type="submit">
        <i class="bi bi-check2-circle me-1"></i>
        {{ $isEdit ? 'Enregistrer' : 'Ajouter' }}
    </button>
</div>
