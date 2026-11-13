@extends('layouts.app')
@section('title', 'Thống kê đọc sách')
@section('content')
<header class="page-heading"><div><span class="eyebrow">NHỊP ĐỌC CỦA BẠN</span><h1>Thống kê đọc sách</h1><p>Nhìn lại những khoảng thời gian bạn dành cho sách.</p></div></header>
<div class="reading-summary-banner"><span aria-hidden="true">✦</span><div><strong>Một trang mỗi ngày cũng là một hành trình.</strong><p>Thời gian được tính từ các phiên đọc đã ghi nhận.</p></div></div>

<div class="row g-3 mb-4 reading-stats-grid">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 reading-stat-card stat-week">
            <div class="card-body">
                <div class="text-muted small">Tuần này · từ {{ $weekStart->format('d/m') }}</div>
                <div class="display-6 fw-semibold">{{ $weekDuration }}</div>
                <div class="small text-muted">Thời gian đọc</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 reading-stat-card stat-month">
            <div class="card-body">
                <div class="text-muted small">Tháng này · {{ $monthStart->format('m/Y') }}</div>
                <div class="display-6 fw-semibold">{{ $monthDuration }}</div>
                <div class="small text-muted">Thời gian đọc</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 reading-stat-card stat-started">
            <div class="card-body">
                <div class="text-muted small">Sách đã bắt đầu</div>
                <div class="display-6 fw-semibold">{{ $booksStarted }}</div>
                <div class="small text-muted">Sách có lưu tiến độ đọc</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 reading-stat-card stat-finished">
            <div class="card-body">
                <div class="text-muted small">Sách đã đọc xong</div>
                <div class="display-6 fw-semibold">{{ $finishedBooks }}</div>
                <div class="small text-muted">Đạt ít nhất 95% tiến độ</div>
            </div>
        </div>
    </div>
</div>

<div class="library-toolbar-heading"><div><span class="eyebrow">DẤU CHÂN TRONG SÁCH</span><h2>Lịch sử đọc gần đây</h2></div></div>

@if ($history->isEmpty())
    <div class="alert alert-secondary">Chưa có phiên đọc nào được ghi nhận.</div>
@else
    <div class="reading-history-list">
        @foreach ($history as $session)
            <article class="reading-history-item">
                <span class="reading-history-dot" aria-hidden="true"></span><div class="reading-history-copy">
                    <a href="{{ route('books.show', $session->book_id) }}">{{ $session->title }}</a>
                    <div>
                        {{ $session->author_name ?? 'Chưa rõ tác giả' }}
                        · Bắt đầu {{ \Illuminate\Support\Carbon::parse($session->started_at)->format('d/m/Y H:i') }}
                    </div>
                </div>
                <span class="reading-duration">{{ $session->duration_label }}</span>
            </article>
        @endforeach
    </div>
    <div class="mt-3">{{ $history->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
