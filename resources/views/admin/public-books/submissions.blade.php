@extends('layouts.admin')
@section('title', 'Duyệt đề xuất sách — Quản trị')
@section('content')
<header class="admin-page-heading review-page-heading">
    <div>
        <span class="eyebrow">KHO SÁCH CÔNG KHAI</span>
        <h1>Duyệt đề xuất sách</h1>
        <p>Xem nội dung người dùng gửi, rồi quyết định có đưa vào kho công khai hay không.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.public-books.index') }}">Quay lại kho sách</a>
</header>

@if (session('status'))
    <div class="review-feedback review-feedback-success" role="status" aria-live="polite">
        <span class="review-feedback-icon" aria-hidden="true">✓</span>
        <div><strong>Đã cập nhật đề xuất</strong><p>{{ session('status') }}</p></div>
        <button type="button" data-dismiss-review-feedback aria-label="Đóng thông báo">×</button>
    </div>
@endif

@if ($errors->any())
    <div class="review-feedback review-feedback-error" role="alert">
        <span class="review-feedback-icon" aria-hidden="true">!</span>
        <div><strong>Chưa thể xử lý đề xuất</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        <button type="button" data-dismiss-review-feedback aria-label="Đóng thông báo">×</button>
    </div>
@endif

<div data-review-queue data-review-page="{{ $submissions->currentPage() }}" aria-live="polite">
    <div class="review-queue-summary">
        <span class="review-queue-icon" aria-hidden="true">✓</span>
        <div>
            <strong><span data-review-count>{{ $submissions->total() }}</span> đề xuất đang chờ</strong>
            <p>Duyệt sách để mọi người có thể tìm và đọc trong kho công khai.</p>
        </div>
    </div>

    <section class="review-submission-list" aria-label="Danh sách đề xuất đang chờ" data-review-submission-list @if ($submissions->isEmpty()) hidden @endif>
        @foreach ($submissions as $submission)
            @include('admin.public-books._submission-card', ['submission' => $submission])
        @endforeach
    </section>
    <div class="admin-empty-state review-empty-state" data-review-empty @if ($submissions->isNotEmpty()) hidden @endif>
        <span aria-hidden="true">✓</span>
        <h3>Không có đề xuất đang chờ</h3>
        <p>Đề xuất từ thư viện người dùng sẽ xuất hiện ở đây.</p>
        <a class="btn btn-outline-secondary mt-3" href="{{ route('admin.public-books.index') }}">Quay lại kho sách</a>
    </div>

{{ $submissions->links('pagination::bootstrap-5') }}
</div>
<script>
    document.querySelectorAll('[data-dismiss-review-feedback]').forEach((button) => {
        button.addEventListener('click', () => button.closest('.review-feedback')?.remove());
    });
</script>
@endsection
