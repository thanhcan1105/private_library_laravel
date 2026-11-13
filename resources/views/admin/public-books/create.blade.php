@extends('layouts.admin')
@section('title', 'Thêm sách công khai')
@section('content')
<header class="admin-page-heading"><div><span class="eyebrow">KHO DÙNG CHUNG</span><h1>Thêm sách công khai</h1><p>Sách sẽ hiển thị cho mọi tài khoản đăng nhập. Tệp được xử lý ở chế độ nền; trạng thái hiện trong kho sách.</p></div><a href="{{ route('admin.public-books.index') }}" class="btn btn-outline-primary">Quay lại kho</a></header>
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="admin-dashboard-panel"><div class="admin-panel-heading"><div><span class="eyebrow">TỆP EPub</span><h2>Tải sách lên kho</h2><p>Dung lượng người tải lên: {{ number_format($used / 1048576, 2) }} / {{ number_format($limit / 1048576, 2) }} MB.</p></div></div>
    <form method="POST" action="{{ route('admin.public-books.store') }}" enctype="multipart/form-data" class="row g-3" data-async-book-upload>@csrf
        <div class="col-md-6"><label class="form-label" for="title">Tiêu đề</label><input id="title" name="title" value="{{ old('title') }}" class="form-control" required maxlength="200">@error('title')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label" for="author_name">Tác giả</label><input id="author_name" name="author_name" value="{{ old('author_name') }}" class="form-control" maxlength="100">@error('author_name')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-12">
            <span class="form-label d-block" id="genre-ids-label">Thể loại (có thể chọn nhiều)</span>
            <div class="genre-dropdown" data-genre-dropdown>
                <details>
                    <summary class="genre-dropdown-toggle"><span data-genre-summary>Chọn thể loại</span><span aria-hidden="true">▾</span></summary>
                    <div class="genre-picker" role="group" aria-labelledby="genre-ids-label">
                        @foreach ($genres as $genre)
                            <label class="genre-picker-option"><input class="form-check-input" type="checkbox" name="genre_ids[]" value="{{ $genre->id }}" data-genre-name="{{ $genre->name }}" @checked(in_array($genre->id, (array) old('genre_ids', [])))><span>{{ $genre->name }}</span></label>
                        @endforeach
                    </div>
                </details>
                <div class="genre-selected-list" data-genre-selected aria-live="polite"></div>
            </div>
            <div class="form-text">Các thể loại đã chọn sẽ hiện bên dưới.</div>
            @error('genre_ids')<div class="text-danger small">{{ $message }}</div>@enderror
            @foreach ($errors->get('genre_ids.*') as $genreErrors) @foreach ($genreErrors as $message)<div class="text-danger small">{{ $message }}</div>@endforeach @endforeach
        </div>
        <div class="col-12"><label class="form-label" for="description">Mô tả</label><textarea id="description" name="description" rows="3" maxlength="2000" class="form-control">{{ old('description') }}</textarea>@error('description')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-12"><label class="form-label" for="file">Tệp sách (.epub hoặc .mobi, tối đa 50 MB)</label><input id="file" name="file" type="file" accept=".epub,.mobi" class="form-control" required data-file-size data-file-size-target="file-size-info"><div class="form-text" id="file-size-info" aria-live="polite">Chưa chọn tệp.</div>@error('file')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-12" data-upload-progress hidden aria-live="polite"><div class="progress" role="progressbar" aria-label="Tiến độ tải tệp" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar" data-upload-progress-bar style="width: 0%"></div></div><div class="form-text mt-1" data-upload-progress-message></div></div>
        <div class="col-12"><div class="alert alert-danger py-2 mb-0" data-upload-errors role="alert" hidden></div></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Thêm vào kho công khai</button><a href="{{ route('admin.public-books.index') }}" class="btn btn-outline-secondary">Hủy</a></div>
    </form>
</section>
@endsection
