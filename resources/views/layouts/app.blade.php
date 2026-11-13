<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <script>window.privateLibraryNotificationConfig = { userId: @json(Auth::id()), isAdmin: @json(Auth::user()->role === 'admin' || Auth::user()->role === 'manager'), audience: 'user', notificationsUrl: @json(route('notifications.index')), pollUrl: @json(route('notifications.poll')), readUrlTemplate: @json(route('notifications.read', '__notification_id__')), deleteUrlTemplate: @json(route('notifications.destroy', '__notification_id__')), readAllUrl: @json(route('notifications.read-all')) };</script>
    @endauth
    <title>@yield('title', 'Thư viện riêng')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/brand-mark.svg') }}">
    <link href="{{ asset('css/library.css') }}" rel="stylesheet">
    @vite('resources/js/app.js')
    @yield('styles')
</head>
<body class="user-body">
<nav class="navbar navbar-expand-lg app-navbar">
    <div class="container-xl">
        <a class="navbar-brand app-brand" href="{{ Auth::check() ? (in_array(Auth::user()->role, ['admin', 'manager'], true) ? route('admin.dashboard') : route('library')) : url('/') }}">
            <img class="app-brand-mark" src="{{ asset('images/brand-mark.svg') }}" alt="" aria-hidden="true">
            <span>Thư viện <strong>riêng</strong></span>
        </a>
        @auth
            <button class="navbar-toggler app-navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appNavigation" aria-controls="appNavigation" aria-expanded="false" aria-label="Mở điều hướng">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="appNavigation">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item">
                        @php
                            $unreadNotificationCount = app(\App\Services\NotificationFeedService::class)->unreadCount(Auth::user(), 'user');
                        @endphp
                        <a class="nav-link @if (request()->routeIs('notifications.*')) active @endif" href="{{ route('notifications.index') }}">
                            Thông báo
                            <span class="notification-count" data-notification-count @if ($unreadNotificationCount === 0) hidden @endif>{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link @if (request()->routeIs('catalog.*')) active @endif" href="{{ route('catalog.index') }}">Kho sách</a></li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('library', 'books.show', 'books.create', 'books.edit')) active @endif" href="{{ route('library') }}">Thư viện</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('books.shared')) active @endif" href="{{ route('books.shared') }}">Được chia sẻ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('shelves.*')) active @endif" href="{{ route('shelves.index') }}">Kệ sách</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('statistics.index')) active @endif" href="{{ route('statistics.index') }}">Thống kê</a>
                    </li>
                    @if (in_array(Auth::user()->role, ['admin', 'manager'], true))
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}">Quản trị</a></li>
                    @endif
                </ul>
                <div class="app-account ms-lg-3">
                    <a class="app-account-name" href="{{ route('profile.edit') }}" title="Tài khoản của tôi">
                        <span class="app-account-avatar" aria-hidden="true">{{ mb_substr(Auth::user()->full_name, 0, 1) }}</span>
                        <span class="app-account-label">{{ Auth::user()->full_name }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="mb-0">
                        @csrf
                        <button class="btn btn-sm btn-quiet">Đăng xuất</button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</nav>
<main class="container-xl app-main @if (request()->routeIs('books.show')) app-main-reader @endif">
    @yield('content')
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/genre-dropdown.js') }}" defer></script>
<script src="{{ asset('js/file-upload-size.js') }}" defer></script>
@yield('scripts')
@include('components.confirm-dialog')
</body>
</html>
