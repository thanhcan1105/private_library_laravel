@extends($layout)

@section('title', 'Thông báo — Thư viện riêng')

@section('content')
<section class="notification-page" data-notification-feed data-feed-page="{{ $notifications->currentPage() }}" data-latest-timestamp="{{ $notifications->first()?->created_at?->toIso8601String() ?? now()->toIso8601String() }}">
    <header class="notification-header">
        <div>
            <div class="page-eyebrow">{{ $audience === 'admin' ? 'KHÔNG GIAN QUẢN TRỊ' : 'THƯ VIỆN CỦA BẠN' }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $description }}</p>
        </div>
        @if ($unreadCount > 0)
            <form method="POST" action="{{ route($readAllRoute) }}">
                @csrf
                <button class="btn btn-outline-secondary" type="submit">Đánh dấu tất cả đã đọc</button>
            </form>
        @endif
    </header>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="notification-list" data-notification-list>
        @forelse ($notifications as $notification)
            <article class="notification-card @if ($notification->read_at === null) is-unread @endif" data-notification-id="{{ $notification->id }}" data-created-at="{{ $notification->created_at->toIso8601String() }}" data-read-url="{{ route($readRoute, $notification->id) }}" @if ($notification->read_at === null) tabindex="0" @endif>
                <span class="notification-indicator" aria-hidden="true"></span>
                <div class="notification-copy">
                    <h2>{{ $notification->data['title'] ?? 'Cập nhật mới' }}</h2>
                    <p>{{ $notification->data['message'] ?? '' }}</p>
                    @if (($notification->data['event'] ?? null) === 'rejected' && filled($notification->data['review_note'] ?? null))
                        <div class="notification-review-note"><strong>Lý do từ chối:</strong> {{ $notification->data['review_note'] }}</div>
                    @endif
                    <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time>
                </div>
                <div class="notification-actions">
                    @if ($notification->read_at === null)
                        <form method="POST" action="{{ route($readRoute, $notification->id) }}" data-notification-read-form>
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success">Mở thông báo</button>
                        </form>
                    @else
                        <span class="notification-read-label">Đã đọc</span>
                    @endif
                    <form method="POST" action="{{ route($deleteRoute, $notification->id) }}" data-confirm-message="Xóa thông báo này?" data-confirm-title="Xóa thông báo?" data-confirm-accept="Xóa thông báo" data-confirm-variant="danger">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="notification-empty">
                <span aria-hidden="true">♧</span>
                <h2>Chưa có thông báo</h2>
                <p>Khi có cập nhật về sách, thông báo sẽ xuất hiện ở đây.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
</section>

@endsection
