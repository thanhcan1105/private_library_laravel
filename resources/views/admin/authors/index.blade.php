@extends('layouts.admin')
@section('title', 'Quản lý tác giả')
@section('content')
<h1 class="h3 mb-3">Quản lý tác giả</h1>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">Thêm tác giả</h2>
        <form method="POST" action="{{ route('admin.authors.store') }}" class="row g-2">
            @csrf
            <div class="col-md-4"><label for="author-name" class="visually-hidden">Tên tác giả</label><input id="author-name" name="name" class="form-control" maxlength="100" value="{{ old('name') }}" placeholder="Tên tác giả" required></div>
            <div class="col-md-6"><label for="author-bio" class="visually-hidden">Giới thiệu</label><input id="author-bio" name="bio" class="form-control" maxlength="12000" value="{{ old('bio') }}" placeholder="Giới thiệu (không bắt buộc)"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-dark">Thêm</button></div>
        </form>
    </div>
</div>

@forelse ($authors as $author)
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body">
            <form id="author-update-{{ $author->id }}" method="POST" action="{{ route('admin.authors.update', $author->id) }}" class="row g-2">
                @csrf
                @method('PUT')
                <div class="col-md-4"><label for="author-name-{{ $author->id }}" class="visually-hidden">Tên tác giả</label><input id="author-name-{{ $author->id }}" name="name" class="form-control form-control-sm" maxlength="100" value="{{ $author->name }}" required></div>
                <div class="col-md-6"><label for="author-bio-{{ $author->id }}" class="visually-hidden">Giới thiệu</label><input id="author-bio-{{ $author->id }}" name="bio" class="form-control form-control-sm" maxlength="12000" value="{{ $author->bio }}" placeholder="Giới thiệu"></div>
            </form>
            <div class="d-flex justify-content-end align-items-center gap-2 mt-2">
                <button type="submit" form="author-update-{{ $author->id }}" class="btn btn-outline-primary btn-sm">Lưu</button>
                <form method="POST" action="{{ route('admin.authors.destroy', $author->id) }}" class="m-0" data-confirm-message="Xóa tác giả này? Sách liên quan vẫn được giữ trong thư viện nhưng sẽ không còn thông tin tác giả." data-confirm-title="Xóa tác giả?" data-confirm-accept="Xóa tác giả" data-confirm-variant="danger">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm">Xóa</button>
                </form>
            </div>
        </div>
    </div>
@empty
    <div class="alert alert-secondary">Chưa có tác giả nào.</div>
@endforelse
@endsection
