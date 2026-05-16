@extends('layouts.admin')

@section('title', __('admin.dashboard'))

@section('content')
<section class="section dashboard">
    <div class="row">
        @foreach ($stats as $key => $value)
            <div class="col-xxl-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('admin.'.$key) }}</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-bar-chart"></i>
                            </div>
                            <div class="ps-3"><h6>{{ $value }}</h6></div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endsection
