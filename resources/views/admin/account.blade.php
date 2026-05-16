@extends('layouts.admin')

@section('title', __('admin.account'))

@section('content')
<section class="section profile">
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
                    <i class="bi bi-person-circle display-1 text-primary"></i>
                    <h2>{{ auth()->user()->firstname ?? auth()->user()->name ?? 'Admin' }}</h2>
                    <h3>{{ auth()->user()->email }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body pt-3">
                    <p class="text-muted">{{ __('admin.profile_hint') }}</p>
                    @include('profile.partials.update-profile-information-form')
                    <hr>
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
