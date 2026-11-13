@extends('layouts.admin')
@section('title', 'Quản lý thể loại')
@section('content')
<h1 class="h3 mb-3">Quản lý thể loại</h1>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">Thêm thể loại</h2>
        <form method="POST" action="{{ route('admin.genres.store') }}" class="row g-2">
            @csrf
            <div class="col-md-4"><label for="genre-name" class="visually-hidden">Tên thể loại</label><input id="genre-name" name="name" class="form-control" maxlength="100" value="{{ old('name') }}" placeholder="Tên thể loại" required></div>
            <div class="col-md-6"><label for="genre-description" class="visually-hidden">Mô tả</label><input id="genre-description" name="description" class="form-control" maxlength="255" value="{{ old('description') }}" placeholder="Mô tả (không bắt buộc)"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-dark">Thêm</button></div>
        </form>
    </div>
</div>

@forelse ($genres as $genre)
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <strong>{{ $genre->name }}</strong>
                <span class="badge bg-light text-dark border">{{ $genre->book_count }} sách</span>
            </div>
            <form method="POST" action="{{ route('admin.genres.update', $genre->id) }}" class="row g-2">
                @csrf
                @method('PUT')
                <div class="col-md-4"><label for="genre-name-{{ $genre->id }}" class="visually-hidden">Tên thể loại</label><input id="genre-name-{{ $genre->id }}" name="name" class="form-control form-control-sm" maxlength="100" value="{{ $genre->name }}" required></div>
                <div class="col-md-6"><label for="genre-description-{{ $genre->id }}" class="visually-hidden">Mô tả</label><input id="genre-description-{{ $genre->id }}" name="description" class="form-control form-control-sm" maxlength="255" value="{{ $genre->description }}" placeholder="Mô tả"></div>
                <div class="col-auto"><button class="btn btn-outline-primary btn-sm">Lưu</button></div>
            </form>
            <form method="POST" action="{{ route('admin.genres.destroy', $genre->id) }}" class="text-end mt-2" data-confirm-message="Xóa thể loại này? Sách đang sử dụng thể loại sẽ được giữ nguyên và cần đổi thể loại trước." data-confirm-title="Xóa thể loại?" data-confirm-accept="Xóa thể loại" data-confirm-variant="danger">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">Xóa</button>
            </form>
        </div>
    </div>
@empty
    <div class="alert alert-secondary">Chưa có thể loại nào.</div>
@endforelse
@endsection
