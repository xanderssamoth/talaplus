<x-guest-layout>
    <div class="pt-4 pb-2">
        <h5 class="card-title text-center pb-0 fs-4">Mot de passe oublie</h5>
        <p class="text-center small">Indiquez votre adresse email pour recevoir un lien de reinitialisation.</p>
    </div>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form class="row g-3" method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="col-12">
            <label class="form-label" for="email">Adresse email</label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">Envoyer le lien</button>
        </div>
    </form>
</x-guest-layout>
