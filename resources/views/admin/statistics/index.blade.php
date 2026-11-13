@extends('layouts.admin')
@section('title', 'Thống kê hệ thống')
@section('content')
<header class="admin-page-heading"><div><span class="eyebrow">DỮ LIỆU HOẠT ĐỘNG</span><h1>Thống kê hệ thống</h1><p>Mỗi lượt đọc tương ứng với một phiên đọc sách đã được ghi nhận.</p></div></header>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 admin-stat-card">
            <div class="card-body">
                <h2 class="h5">Theo ngày · 30 ngày gần nhất</h2>
                @forelse ($dailyReads as $item)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ \Illuminate\Support\Carbon::parse($item->period)->format('d/m/Y') }}</span>
                        <strong>{{ number_format($item->read_count) }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">Chưa có lượt đọc.</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 admin-stat-card">
            <div class="card-body">
                <h2 class="h5">Theo tháng · 12 tháng gần nhất</h2>
                @forelse ($monthlyReads as $item)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $item->period }}</span>
                        <strong>{{ number_format($item->read_count) }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">Chưa có lượt đọc.</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 admin-stat-card">
            <div class="card-body">
                <h2 class="h5">Theo năm · 5 năm gần nhất</h2>
                @forelse ($yearlyReads as $item)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $item->period }}</span>
                        <strong>{{ number_format($item->read_count) }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">Chưa có lượt đọc.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
