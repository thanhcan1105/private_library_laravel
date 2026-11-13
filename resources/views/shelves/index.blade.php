@extends('layouts.app')
@section('title', 'Kệ sách của tôi')
@section('content')
<header class="page-heading user-subpage-heading"><div><span class="eyebrow">SẮP XẾP THEO CÁCH CỦA BẠN</span><h1>Kệ sách</h1><p>Tạo những góc nhỏ cho các cuốn sách bạn muốn tìm lại nhanh.</p></div><a href="{{ route('library') }}" class="library-text-link">Về thư viện <span aria-hidden="true">→</span></a></header>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4 shelf-create-card">
    <div class="card-body">
        <div class="shelf-create-intro"><span aria-hidden="true">＋</span><div><h2>Tạo kệ mới</h2><p>Ví dụ: Đọc trong kỳ nghỉ, Tiểu thuyết yêu thích…</p></div></div>
        <form method="POST" action="{{ route('shelves.store') }}" class="row g-2">
            @csrf
            <div class="col-md-4">
                <label class="visually-hidden" for="new-shelf-name">Tên kệ</label>
                <input id="new-shelf-name" name="name" class="form-control" maxlength="100" value="{{ old('name') }}" placeholder="Tên kệ" required>
            </div>
            <div class="col-md-6">
                <label class="visually-hidden" for="new-shelf-description">Mô tả</label>
                <input id="new-shelf-description" name="description" class="form-control" maxlength="255" value="{{ old('description') }}" placeholder="Mô tả (không bắt buộc)">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary">Tạo kệ</button>
            </div>
        </form>
    </div>
</div>

<div class="shelf-grid">
@forelse ($shelves as $shelf)
    <article class="shelf-card">
        <a href="{{ route('shelves.show', $shelf->id) }}" class="shelf-art" aria-label="Mở kệ {{ $shelf->name }}"><span class="shelf-art-book shelf-art-book-one"></span><span class="shelf-art-book shelf-art-book-two"></span><span class="shelf-art-book shelf-art-book-three"></span><span class="shelf-art-label">{{ $shelf->book_count }} {{ (int) $shelf->book_count === 1 ? 'CUỐN' : 'CUỐN SÁCH' }}</span></a>
        <div class="shelf-card-content"><div><h2><a href="{{ route('shelves.show', $shelf->id) }}">{{ $shelf->name }}</a></h2><p>{{ $shelf->description ?: 'Một góc nhỏ trong thư viện của bạn.' }}</p></div><details class="shelf-manage"><summary aria-label="Quản lý kệ {{ $shelf->name }}">•••</summary><div class="shelf-manage-panel">
            <form method="POST" action="{{ route('shelves.update', $shelf->id) }}" class="shelf-edit-form">@csrf @method('PUT')<label for="shelf-name-{{ $shelf->id }}">Tên kệ</label><input id="shelf-name-{{ $shelf->id }}" name="name" class="form-control form-control-sm" maxlength="100" value="{{ $shelf->name }}" required><label for="shelf-description-{{ $shelf->id }}">Mô tả</label><input id="shelf-description-{{ $shelf->id }}" name="description" class="form-control form-control-sm" maxlength="255" value="{{ $shelf->description }}"><button class="btn btn-primary btn-sm mt-2">Lưu thay đổi</button></form>
            <form method="POST" action="{{ route('shelves.destroy', $shelf->id) }}" class="mt-2" data-confirm-message="Xóa kệ này? Sách trong thư viện sẽ không bị xóa." data-confirm-title="Xóa kệ sách?" data-confirm-accept="Xóa kệ" data-confirm-variant="danger">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm w-100">Xóa kệ</button></form>
        </div></details></div><a class="shelf-open-link" href="{{ route('shelves.show', $shelf->id) }}">Mở kệ <span aria-hidden="true">→</span></a>
    </article>
@empty
    <div class="alert alert-secondary">Bạn chưa tạo kệ sách nào.</div>
@endforelse
</div>
@endsection
