<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.dashboard')) - {{ __('admin.app_name') }}</title>

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('template/assets/img/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/img/favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/img/favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('assets/img/favicon/site.webmanifest') }}">

    <link href="{{ asset('template/assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('template/assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
    <link href="{{ asset('template/assets/vendor/boxicons/css/boxicons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('template/assets/vendor/remixicon/remixicon.css') }}" rel="stylesheet">
    <link href="{{ asset('template/assets/css/style.css') }}" rel="stylesheet">
    <style>
        .logo img { max-height: 44px; }
        .data-table td { vertical-align: middle; }
        .translatable-tabs .nav-link { padding: .35rem .65rem; }
        .table-responsive { min-height: 280px; }
    </style>
</head>
<body>
<header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('dashboard') }}" class="logo d-flex align-items-center">
            <img src="{{ asset('assets/img/brand.png') }}" alt="{{ __('admin.app_name') }}">
            {{-- <span class="d-none d-lg-block">{{ __('admin.app_name') }}</span> --}}
        </a>
        <i class="bi bi-list toggle-sidebar-btn"></i>
    </div>

    <nav class="header-nav ms-auto">
        <ul class="d-flex align-items-center">
            <li class="nav-item dropdown pe-3">
                <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-4"></i>
                    <span class="d-none d-md-block dropdown-toggle ps-2">{{ auth()->user()->firstname ?? auth()->user()->name ?? 'Admin' }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                    <li><a class="dropdown-item d-flex align-items-center" href="{{ route('account') }}"><i class="bi bi-person"></i><span>{{ __('admin.account') }}</span></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item d-flex align-items-center" type="submit"><i class="bi bi-box-arrow-right"></i><span>{{ __('Log Out') }}</span></button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
</header>

<aside id="sidebar" class="sidebar">
    @php
        $links = [
            ['dashboard', route('dashboard'), 'bi-grid', 'dashboard'],
            ['categories', route('categories.index'), 'bi-tags', 'categories'],
            ['roles', route('roles.index'), 'bi-person-badge', 'roles'],
            ['reasons', route('reasons.index'), 'bi-flag', 'reasons'],
            ['pricings', route('pricings.index'), 'bi-cash-coin', 'pricings'],
            ['abouts', route('abouts.index'), 'bi-info-circle', 'abouts'],
            ['videos', route('videos.index'), 'bi-play-btn', 'videos'],
            ['users', route('users.index'), 'bi-people', 'users'],
            ['messages', route('messages.index'), 'bi-envelope', 'messages'],
            ['notifications', route('notifications.index'), 'bi-bell', 'notifications'],
            ['account', route('account'), 'bi-person', 'account'],
        ];
    @endphp
    <ul class="sidebar-nav" id="sidebar-nav">
        @foreach ($links as [$key, $url, $icon, $label])
            <li class="nav-item">
                <a class="nav-link {{ request()->is($key) || request()->is($key.'/*') || (request()->routeIs('dashboard') && $key === 'dashboard') ? '' : 'collapsed' }}" href="{{ $url }}">
                    <i class="bi {{ $icon }}"></i><span>{{ __('admin.'.$label) }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</aside>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>@yield('title', __('admin.dashboard'))</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('admin.dashboard') }}</a></li>
                <li class="breadcrumb-item active">@yield('title', __('admin.dashboard'))</li>
            </ol>
        </nav>
    </div>

    @yield('content')
</main>

<footer id="footer" class="footer">
    <div class="copyright">&copy; {{ date('Y') }} <strong><span>{{ __('admin.app_name') }}</span></strong></div>
</footer>

<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="{{ asset('template/assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('template/assets/js/main.js') }}"></script>
@stack('scripts')
</body>
</html>
