<!DOCTYPE html>
@php
    $appTheme = DB::table('reading_settings')->where('user_id', Auth::id())->value('theme') ?? 'light';
@endphp
<html lang="vi" data-theme="{{ $appTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.privateLibraryThemeConfig = { updateUrl: @json(route('reading-settings.update')) };</script>
    <script>window.privateLibraryNotificationConfig = { userId: @json(Auth::id()), isAdmin: true, audience: 'admin', notificationsUrl: @json(route('admin.notifications.index')), pollUrl: @json(route('admin.notifications.poll')), readUrlTemplate: @json(route('admin.notifications.read', '__notification_id__')), deleteUrlTemplate: @json(route('admin.notifications.destroy', '__notification_id__')), readAllUrl: @json(route('admin.notifications.read-all')), reviewPollUrl: @json(route('admin.public-submissions.poll')), reviewCardUrlTemplate: @json(route('admin.public-submissions.card', '__book_id__')) };</script>
    <title>@yield('title', 'Quản trị — Thư viện riêng')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/brand-mark.svg') }}">
    <link href="{{ asset('css/library.css') }}" rel="stylesheet">
    @vite('resources/js/app.js')
    @yield('styles')
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Điều hướng quản trị">
        <a class="admin-sidebar-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('images/brand-mark.svg') }}" alt=""><span>Thư viện <strong>riêng</strong><small>QUẢN TRỊ</small></span></a>
        <div class="admin-sidebar-caption">KHÔNG GIAN QUẢN TRỊ</div>
        <a class="admin-side-link @if (request()->routeIs('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}"><span aria-hidden="true">◫</span>Tổng quan</a>
        <div class="admin-sidebar-caption admin-sidebar-section">QUẢN LÝ</div>
        <a class="admin-side-link @if (request()->routeIs('admin.users.*')) active @endif" href="{{ route('admin.users.index') }}"><span aria-hidden="true">♙</span>Người dùng</a>
        <a class="admin-side-link @if (request()->routeIs('admin.public-books.*')) active @endif" href="{{ route('admin.public-books.index') }}"><span aria-hidden="true">▤</span>Kho sách công khai</a>
        <a class="admin-side-link @if (request()->routeIs('admin.public-submissions.*')) active @endif" href="{{ route('admin.public-submissions.index') }}"><span aria-hidden="true">✓</span>Duyệt đề xuất</a>
        @php
            $unreadNotificationCount = app(\App\Services\NotificationFeedService::class)->unreadCount(Auth::user(), 'admin');
        @endphp
        <a class="admin-side-link @if (request()->routeIs('admin.notifications.*')) active @endif" href="{{ route('admin.notifications.index') }}"><span aria-hidden="true">♧</span>Thông báo <span class="notification-count" data-notification-count @if ($unreadNotificationCount === 0) hidden @endif>{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span></a>
        <a class="admin-side-link @if (request()->routeIs('admin.genres.*')) active @endif" href="{{ route('admin.genres.index') }}"><span aria-hidden="true">▤</span>Thể loại</a>
        <a class="admin-side-link @if (request()->routeIs('admin.authors.*')) active @endif" href="{{ route('admin.authors.index') }}"><span aria-hidden="true">✎</span>Tác giả</a>
        <a class="admin-side-link @if (request()->routeIs('admin.statistics.*')) active @endif" href="{{ route('admin.statistics.index') }}"><span aria-hidden="true">▥</span>Thống kê</a>
        <div class="admin-sidebar-bottom">
            <div class="admin-profile-chip"><span class="app-account-avatar" aria-hidden="true">{{ mb_substr(Auth::user()->full_name, 0, 1) }}</span><span><strong>{{ Auth::user()->full_name }}</strong><small>{{ Auth::user()->role === 'admin' ? 'Quản trị viên chính' : 'Quản lý' }}</small></span></div>
            <a class="admin-side-link" href="{{ route('library') }}"><span aria-hidden="true">↗</span>Khu người dùng</a>
            <form method="POST" action="{{ route('logout') }}" class="mb-0">
                @csrf
                <button class="admin-side-link admin-logout"><span aria-hidden="true">⇥</span>Đăng xuất</button>
            </form>
        </div>
    </aside>
    <button type="button" class="btn btn-light theme-toggle-btn theme-toggle-icon admin-theme-toggle" data-theme-toggle role="switch" aria-label="Chuyển sang giao diện tối" title="Chuyển sang giao diện tối" aria-checked="{{ $appTheme === 'dark' ? 'true' : 'false' }}"><span data-theme-icon aria-hidden="true">{{ $appTheme === 'dark' ? '☀' : '☾' }}</span></button>
    <main class="app-main admin-main">@yield('content')</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/genre-dropdown.js') }}" defer></script>
<script src="{{ asset('js/file-upload-size.js') }}" defer></script>
@yield('scripts')
@include('components.confirm-dialog')
</body>
</html>
