@extends('layouts.admin')

@section('title', __('admin.account'))

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">
<style>
    .avatar-crop-frame {
        max-height: min(62vh, 460px);
        overflow: hidden;
    }

    .avatar-crop-frame img {
        max-width: 100%;
    }
</style>
@endpush

@section('content')
<section class="section profile">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center pt-4">
                    <div class="mx-auto position-relative" style="height: 132px; width: 132px;">
                        @if ($user->avatar_url)
                            <img class="rounded-circle border w-100 h-100 object-fit-cover" id="account-avatar" src="{{ $user->avatar_url }}" alt="{{ $user->firstname ?? 'Avatar' }}">
                        @else
                            <svg class="rounded-circle border w-100 h-100" id="account-avatar-fallback" viewBox="0 0 132 132" role="img" aria-label="Avatar">
                                <rect width="132" height="132" rx="66" fill="#e7f1ff"/>
                                <circle cx="66" cy="50" r="26" fill="#0d6efd"/>
                                <path d="M29 116c7-24 23-38 37-38s30 14 37 38" fill="#0d6efd"/>
                            </svg>
                            <img class="rounded-circle border w-100 h-100 object-fit-cover d-none" id="account-avatar" src="" alt="{{ $user->firstname ?? 'Avatar' }}">
                        @endif
                    </div>
                    <button class="btn btn-sm btn-outline-primary mt-3" id="change-avatar" type="button">
                        <i class="bi bi-camera"></i> Modifier l’avatar
                    </button>
                    <input class="d-none" id="avatar-input" type="file" accept="image/*">
                    <div class="small text-muted mt-2" id="avatar-status"></div>
                    <h5 class="mt-3 mb-1">{{ $user->firstname ?? $user->name ?? 'Admin' }} {{ $user->lastname }}</h5>
                    <p class="text-muted mb-0">{{ $user->email }}</p>
                    @if ($user->username)
                        <span class="badge bg-light text-dark mt-3">{{ $user->username }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body pt-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="card-title mb-0 p-0">Identité</h5>
                            <p class="text-muted small mb-0">{{ __('admin.profile_hint') }}</p>
                        </div>
                    </div>

                    @if (session('status') === 'profile-updated')
                        <div class="alert alert-success">Informations enregistrées.</div>
                    @endif

                    <form class="row g-3" method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_redirect" value="account">

                        <div class="col-md-6">
                            <label class="form-label" for="firstname">Prénom</label>
                            <input class="form-control @error('firstname') is-invalid @enderror" id="firstname" name="firstname" value="{{ old('firstname', $user->firstname) }}" required>
                            @error('firstname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="lastname">Nom</label>
                            <input class="form-control @error('lastname') is-invalid @enderror" id="lastname" name="lastname" value="{{ old('lastname', $user->lastname) }}">
                            @error('lastname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="surname">Postnom</label>
                            <input class="form-control @error('surname') is-invalid @enderror" id="surname" name="surname" value="{{ old('surname', $user->surname) }}">
                            @error('surname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="username">Nom d’utilisateur</label>
                            <input class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $user->username) }}">
                            @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="email">Adresse email</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="phone">Téléphone</label>
                            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="gender">Genre</label>
                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                <option value="">-</option>
                                <option value="male" @selected(old('gender', $user->gender) === 'male')>Masculin</option>
                                <option value="female" @selected(old('gender', $user->gender) === 'female')>Féminin</option>
                            </select>
                            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="partner_name">Nom du partenaire</label>
                            <input class="form-control @error('partner_name') is-invalid @enderror" id="partner_name" name="partner_name" value="{{ old('partner_name', $user->partner_name) }}">
                            @error('partner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="birthdate">Date de naissance</label>
                            <input class="form-control @error('birthdate') is-invalid @enderror" id="birthdate" name="birthdate" type="date" value="{{ old('birthdate', optional($user->birthdate)->format('Y-m-d')) }}">
                            @error('birthdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="country">Pays</label>
                            <input class="form-control @error('country') is-invalid @enderror" id="country" name="country" value="{{ old('country', $user->country) }}">
                            @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="city">Ville</label>
                            <input class="form-control @error('city') is-invalid @enderror" id="city" name="city" value="{{ old('city', $user->city) }}">
                            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="currency">Devise</label>
                            <input class="form-control @error('currency') is-invalid @enderror" id="currency" name="currency" value="{{ old('currency', $user->currency) }}" placeholder="USD">
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="p_o_box">Boîte postale</label>
                            <input class="form-control @error('p_o_box') is-invalid @enderror" id="p_o_box" name="p_o_box" value="{{ old('p_o_box', $user->p_o_box) }}">
                            @error('p_o_box')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="address_1">Adresse 1</label>
                            <textarea class="form-control @error('address_1') is-invalid @enderror" id="address_1" name="address_1" rows="2">{{ old('address_1', $user->address_1) }}</textarea>
                            @error('address_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="address_2">Adresse 2</label>
                            <textarea class="form-control @error('address_2') is-invalid @enderror" id="address_2" name="address_2" rows="2">{{ old('address_2', $user->address_2) }}</textarea>
                            @error('address_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-save"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="avatar-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Recadrer l’avatar</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="avatar-crop-frame border rounded bg-light p-2">
                    <img class="img-fluid d-block mx-auto" id="avatar-crop-image" alt="Avatar à recadrer">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-primary" id="save-avatar" type="button">
                    <i class="bi bi-check2"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
    $(function () {
        let cropper = null;
        const modal = new bootstrap.Modal('#avatar-modal');

        $('#change-avatar').on('click', function () {
            $('#avatar-input').trigger('click');
        });

        $('#avatar-input').on('change', function () {
            const file = this.files?.[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                $('#avatar-crop-image').attr('src', event.target.result);
                modal.show();
            };
            reader.readAsDataURL(file);
        });

        $('#avatar-modal').on('shown.bs.modal', function () {
            cropper?.destroy();
            cropper = new Cropper(document.getElementById('avatar-crop-image'), {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 1,
            });
        }).on('hidden.bs.modal', function () {
            cropper?.destroy();
            cropper = null;
            $('#avatar-input').val('');
        });

        $('#save-avatar').on('click', function () {
            if (!cropper) return;

            $('#avatar-status').text('Envoi en cours...');
            cropper.getCroppedCanvas({width: 512, height: 512}).toBlob(function (blob) {
                const formData = new FormData();
                formData.append('_method', 'PATCH');
                formData.append('avatar', blob, 'avatar.png');

                $.ajax({
                    url: @json(route('account.avatar')),
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                }).done(function (response) {
                    $('#account-avatar').attr('src', response.avatar_url).removeClass('d-none');
                    $('#account-avatar-fallback').addClass('d-none');
                    $('#topbar-avatar').attr('src', response.avatar_url).removeClass('d-none');
                    $('#topbar-avatar-fallback').addClass('d-none');
                    $('#avatar-status').text(response.message || 'Avatar mis à jour.');
                    modal.hide();
                }).fail(function (xhr) {
                    $('#avatar-status').text(xhr.responseJSON?.message || 'Impossible d’envoyer l’avatar.');
                });
            }, 'image/png');
        });
    });
</script>
@endpush
