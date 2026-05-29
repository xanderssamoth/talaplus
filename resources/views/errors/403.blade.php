<x-guest-layout>
    <div class="py-4 text-center">
        <div class="display-6 fw-bold text-primary mb-2">403</div>
        <h5 class="card-title pb-0 fs-4">Accès refusé</h5>
        <p class="text-muted mb-4">
            Vous n’avez pas l’autorisation d’accéder à cette page.
        </p>
        <a class="btn btn-primary" href="{{ route('login') }}">Retour à la connexion</a>
    </div>
</x-guest-layout>
