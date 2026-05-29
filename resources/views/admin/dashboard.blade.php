@extends('layouts.admin')

@section('title', __('admin.dashboard'))

@push('styles')
<style>
    .dashboard-alert-fixed {
        left: 50%;
        max-width: 500px;
        position: fixed;
        top: 18px;
        transform: translateX(-50%);
        width: calc(100% - 32px);
        z-index: 2050;
    }

    .dashboard-stat-icon {
        height: 44px;
        width: 44px;
    }

    .dashboard-table td,
    .dashboard-table th {
        vertical-align: middle;
    }
</style>
@endpush

@section('content')
@php
    $paymentChartLabels = ['Paiement en cours', 'Paiement réussi', 'Paiement échoué'];
    $paymentChartValues = [
        $paymentStats['pending'] ?? 0,
        $paymentStats['successful'] ?? 0,
        $paymentStats['failed'] ?? 0,
    ];
@endphp

<section class="section dashboard">
    <div class="dashboard-alert-fixed" id="dashboard-alert"></div>

    <div class="row g-3">
        @foreach ($stats as $stat)
            <div class="col-xxl-2 col-md-4 col-sm-6">
                <div class="card info-card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-2">{{ $stat['label'] }}</h5>
                        <div class="d-flex align-items-center">
                            <div class="dashboard-stat-icon rounded-circle bg-{{ $stat['color'] }} bg-opacity-10 text-{{ $stat['color'] }} d-flex align-items-center justify-content-center">
                                <i class="bi {{ $stat['icon'] }} fs-4"></i>
                            </div>
                            <div class="ps-3">
                                <h6 class="mb-0">{{ number_format($stat['value'], 0, ',', ' ') }}</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Statistiques des paiements</h5>
                    <div style="min-height: 280px;">
                        <canvas id="payments-chart" height="120"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">5 utilisateurs les plus récents</h5>
                    <div class="table-responsive">
                        <table class="table table-hover dashboard-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>État</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentUsers as $user)
                                    <tr>
                                        <td>{{ trim(($user->firstname ?? '').' '.($user->lastname ?? '')) ?: ($user->username ?? '-') }}</td>
                                        <td>{{ $user->email ?? '-' }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm dropdown-toggle user-status-button {{ $user->status === 'activated' ? 'btn-outline-success' : ($user->status === 'blocked' ? 'btn-outline-danger' : 'btn-outline-secondary') }}" data-bs-toggle="dropdown" type="button">
                                                    {{ $statusOptions[$user->status] ?? $user->status ?? '-' }}
                                                </button>
                                                <ul class="dropdown-menu">
                                                    @foreach ($statusOptions as $status => $label)
                                                        <li>
                                                            <button class="dropdown-item dashboard-change-status {{ $status === $user->status ? 'active' : '' }}" data-id="{{ $user->id }}" data-status="{{ $status }}" type="button">
                                                                {{ $label }}
                                                            </button>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="text-center text-muted" colspan="3">{{ __('admin.empty') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">5 vidéos les plus récentes</h5>
                    <div class="table-responsive">
                        <table class="table table-hover dashboard-table">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Propriétaire</th>
                                    <th>Est publiée</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentVideos as $video)
                                    @php
                                        $title = $video->getTranslation('media_title', app()->getLocale(), false) ?: $video->getTranslation('media_title', 'fr', false) ?: 'Vidéo';
                                        $owner = trim(($video->user?->firstname ?? '').' '.($video->user?->lastname ?? '')) ?: '-';
                                    @endphp
                                    <tr>
                                        <td>{{ $title }}</td>
                                        <td>{{ $owner }}</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input dashboard-toggle-shared" data-resource="videos" data-id="{{ $video->id }}" type="checkbox" @checked($video->is_shared)>
                                                </div>
                                                <span class="dashboard-shared-label fw-semibold {{ $video->is_shared ? 'text-success' : 'text-danger' }}">
                                                    {{ $video->is_shared ? 'Oui' : 'Non' }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="text-center text-muted" colspan="3">{{ __('admin.empty') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">5 produits les plus récents</h5>
                    <div class="table-responsive">
                        <table class="table table-hover dashboard-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prix</th>
                                    <th>Est publié</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentProducts as $product)
                                    <tr>
                                        <td>{{ $product->product_name ?? 'Produit' }}</td>
                                        <td>{{ number_format((float) ($product->price ?? 0), 2, ',', ' ') }} {{ $product->currency ?? '' }}</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input dashboard-toggle-shared" data-resource="products" data-id="{{ $product->id }}" type="checkbox" @checked($product->is_shared)>
                                                </div>
                                                <span class="dashboard-shared-label fw-semibold {{ $product->is_shared ? 'text-success' : 'text-danger' }}">
                                                    {{ $product->is_shared ? 'Oui' : 'Non' }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="text-center text-muted" colspan="3">{{ __('admin.empty') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(function () {
        const statusOptions = @json($statusOptions);

        $.ajaxSetup({
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        });

        function alertBox(message, type = 'success') {
            $('#dashboard-alert').html(`<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
        }

        function statusButtonClass(status) {
            return {
                created: 'btn-outline-secondary',
                activated: 'btn-outline-success',
                disabled: 'btn-outline-warning',
                blocked: 'btn-outline-danger',
                deleted: 'btn-outline-dark',
            }[status] || 'btn-outline-secondary';
        }

        function setSharedLabel($input, shared) {
            const $label = $input.closest('td').find('.dashboard-shared-label');
            $input.prop('checked', shared);
            $label
                .text(shared ? 'Oui' : 'Non')
                .toggleClass('text-success', shared)
                .toggleClass('text-danger', !shared);
        }

        new Chart(document.getElementById('payments-chart'), {
            type: 'bar',
            data: {
                labels: @json($paymentChartLabels),
                datasets: [{
                    label: 'Paiements',
                    data: @json($paymentChartValues),
                    backgroundColor: ['#ffc107', '#198754', '#dc3545'],
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {display: false},
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {precision: 0},
                    },
                },
            },
        });

        $(document).on('click', '.dashboard-change-status', function () {
            const $item = $(this);
            $.ajax({
                url: @json(url('users')) + '/' + $item.data('id') + '/status',
                method: 'PATCH',
                data: {status: $item.data('status')},
            }).done(function (response) {
                const status = response.item?.status || $item.data('status');
                const label = response.item?.status_display || statusOptions[status] || status;
                const $dropdown = $item.closest('.dropdown');
                $dropdown.find('.dashboard-change-status').removeClass('active');
                $item.addClass('active');
                $dropdown.find('.user-status-button')
                    .removeClass('btn-outline-secondary btn-outline-success btn-outline-warning btn-outline-danger btn-outline-dark')
                    .addClass(statusButtonClass(status))
                    .text(label);
                alertBox(response.message || @json(__('admin.saved')));
            }).fail(function (xhr) {
                alertBox(xhr.responseJSON?.message || xhr.statusText, 'danger');
            });
        });

        $(document).on('change', '.dashboard-toggle-shared', function () {
            const $input = $(this);
            const previousValue = !$input.prop('checked');
            $.ajax({
                url: @json(url('/')) + '/' + $input.data('resource') + '/' + $input.data('id') + '/shared',
                method: 'PATCH',
            }).done(function (response) {
                const shared = response.item?.is_shared === 1 || response.item?.is_shared === true || response.item?.is_shared === '1';
                setSharedLabel($input, shared);
                alertBox(response.message || @json(__('admin.saved')));
            }).fail(function (xhr) {
                setSharedLabel($input, previousValue);
                alertBox(xhr.responseJSON?.message || xhr.statusText, 'danger');
            });
        });
    });
</script>
@endpush
