<x-guest-layout>
    <div class="pt-4 pb-2">
        <h5 class="card-title text-center pb-0 fs-4">Verification email</h5>
        <p class="text-center small">Validez votre adresse email avec le lien que nous venons d envoyer.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success">Un nouveau lien de verification a ete envoye.</div>
    @endif

    <div class="d-flex justify-content-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Renvoyer le lien</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-secondary" type="submit">Se deconnecter</button>
        </form>
    </div>
</x-guest-layout>
