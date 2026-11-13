@extends('layouts.admin')
@section('title', 'Tổng quan quản trị')
@section('content')
<header class="admin-page-heading">
    <div><span class="eyebrow">BẢNG ĐIỀU KHIỂN</span><h1>Chào {{ Auth::user()->full_name }}</h1><p>Tình hình thư viện trên toàn hệ thống.</p></div>
    <a href="{{ route('admin.statistics.index') }}" class="btn btn-outline-primary">Xem báo cáo <span aria-hidden="true">↗</span></a>
</header>

<section class="admin-metric-grid" aria-label="Chỉ số hệ thống">
    <article class="admin-metric-card metric-users"><span class="metric-icon" aria-hidden="true">♙</span><span class="metric-label">TÀI KHOẢN</span><strong>{{ number_format($userCount) }}</strong><small>Tài khoản đã đăng ký</small></article>
    <article class="admin-metric-card metric-books"><span class="metric-icon" aria-hidden="true">▤</span><span class="metric-label">SÁCH TRONG KHO</span><strong>{{ number_format($bookCount) }}</strong><small>EPUB được lưu trữ</small></article>
    <article class="admin-metric-card metric-storage"><span class="metric-icon" aria-hidden="true">◉</span><span class="metric-label">DUNG LƯỢNG ĐÃ DÙNG</span><strong>{{ number_format($storageUsed / 1073741824, 2) }} <small>GB</small></strong><small>{{ number_format($storageUsed) }} byte</small></article>
</section>

<section class="admin-dashboard-panel">
    <div class="admin-panel-heading"><div><span class="eyebrow">MỨC ĐỘ QUAN TÂM</span><h2>Sách được đọc nhiều</h2><p>10 đầu sách có nhiều lượt đọc nhất.</p></div><a href="{{ route('admin.statistics.index') }}" class="text-link">Tất cả thống kê <span aria-hidden="true">→</span></a></div>
    @if ($topBooks->isEmpty())
        <div class="admin-empty-state"><span aria-hidden="true">▤</span><h3>Chưa có lượt đọc</h3><p>Dữ liệu sách được đọc nhiều sẽ xuất hiện khi có phiên đọc được ghi nhận.</p></div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-ranking-table">
                <thead><tr><th>Sách</th><th>Tác giả</th><th class="text-end">Lượt đọc</th></tr></thead>
                <tbody>
                    @foreach ($topBooks as $book)
                        <tr>
                            <td><span class="rank-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><strong>{{ $book->title }}</strong></td>
                            <td>{{ $book->author_name ?? '—' }}</td>
                            <td class="text-end"><span class="read-count-pill">{{ number_format($book->read_count) }} lượt</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
