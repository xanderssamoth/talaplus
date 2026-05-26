<x-guest-layout>
    <div class="pt-4 pb-2">
        <h5 class="card-title text-center pb-0 fs-4">Connexion administrateur</h5>
        <p class="text-center small">Connectez-vous pour gerer les donnees de fonctionnement.</p>
    </div>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form class="row g-3" method="POST" action="{{ route('login') }}">
        @csrf

        <div class="col-12">
            <label class="form-label" for="email">Adresse email</label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <label class="form-label" for="password">Mot de passe</label>
            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" id="remember_me" name="remember" type="checkbox">
                <label class="form-check-label" for="remember_me">Se souvenir de moi</label>
            </div>
        </div>

        <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">Se connecter</button>
        </div>

        <div class="col-12 d-flex justify-content-between gap-3 small">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">Mot de passe oublie ?</a>
            @endif
            <a href="{{ route('register') }}">Creer un compte</a>
        </div>
    </form>
</x-guest-layout>
