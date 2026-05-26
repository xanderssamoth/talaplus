<x-guest-layout>
    <div class="pt-4 pb-2">
        <h5 class="card-title text-center pb-0 fs-4">Confirmation de securite</h5>
        <p class="text-center small">Confirmez votre mot de passe avant de continuer.</p>
    </div>

    <form class="row g-3" method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="col-12">
            <label class="form-label" for="password">Mot de passe</label>
            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">Confirmer</button>
        </div>
    </form>
</x-guest-layout>
