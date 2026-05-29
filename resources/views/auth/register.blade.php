<x-guest-layout>
    <div class="pt-4 pb-2">
        <h5 class="card-title text-center pb-0 fs-4">Création du compte administrateur</h5>
        <p class="text-center small">Le compte recevra automatiquement le rôle Administrateur.</p>
    </div>

    <form class="row g-3" method="POST" action="{{ route('register') }}">
        @csrf

        <div class="col-md-6">
            <label class="form-label" for="firstname">Prénom</label>
            <input class="form-control @error('firstname') is-invalid @enderror" id="firstname" name="firstname" type="text" value="{{ old('firstname') }}" required autofocus autocomplete="given-name">
            @error('firstname')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="lastname">Nom</label>
            <input class="form-control @error('lastname') is-invalid @enderror" id="lastname" name="lastname" type="text" value="{{ old('lastname') }}" autocomplete="family-name">
            @error('lastname')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="surname">Postnom</label>
            <input class="form-control @error('surname') is-invalid @enderror" id="surname" name="surname" type="text" value="{{ old('surname') }}">
            @error('surname')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="username">Nom d’utilisateur</label>
            <input class="form-control @error('username') is-invalid @enderror" id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username">
            @error('username')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="email">Adresse email</label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="phone">Téléphone</label>
            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" type="text" value="{{ old('phone') }}" autocomplete="tel">
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="password">Mot de passe</label>
            <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required autocomplete="new-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="password_confirmation">Confirmation</label>
            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>

        <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">Créer le compte</button>
        </div>

        <div class="col-12">
            <p class="small mb-0">Vous avez déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
        </div>
    </form>
</x-guest-layout>
