<x-guest-layout>
    <div class="pt-4 pb-2">
        <h5 class="card-title text-center pb-0 fs-4">Reinitialisation du mot de passe</h5>
        <p class="text-center small">Choisissez un nouveau mot de passe pour votre compte.</p>
    </div>

    <form class="row g-3" method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="col-12">
            <label class="form-label" for="email">Adresse email</label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            @error('email')
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
            <button class="btn btn-primary w-100" type="submit">Changer le mot de passe</button>
        </div>
    </form>
</x-guest-layout>
