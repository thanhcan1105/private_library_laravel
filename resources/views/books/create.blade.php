@extends('layouts.app')
@section('title', 'Tải sách lên')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Tải sách lên</h1>
                <p class="text-muted">
                    Đã dùng {{ number_format($used / 1048576, 2) }} MB / {{ number_format($limit / 1048576, 2) }} MB.
                    Nhận EPUB hoặc MOBI. Tệp được xử lý ở chế độ nền; trạng thái hiện trong thư viện. Tối đa 50 MB.
                </p>

                <form method="POST" action="{{ route('books.store') }}" enctype="multipart/form-data" novalidate data-async-book-upload>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="title">Tiêu đề</label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}"
                               class="form-control @error('title') is-invalid @enderror">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="author_name">Tác giả (không bắt buộc)</label>
                        <input type="text" id="author_name" name="author_name" value="{{ old('author_name') }}"
                               class="form-control @error('author_name') is-invalid @enderror">
                        @error('author_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <span class="form-label d-block" id="genre-ids-label">Thể loại (có thể chọn nhiều)</span>
                        <div class="genre-dropdown" data-genre-dropdown>
                            <details>
                                <summary class="genre-dropdown-toggle"><span data-genre-summary>Chọn thể loại</span><span aria-hidden="true">▾</span></summary>
                                <div class="genre-picker" role="group" aria-labelledby="genre-ids-label">
                                    @foreach ($genres as $genre)
                                        <label class="genre-picker-option">
                                            <input class="form-check-input" type="checkbox" name="genre_ids[]" value="{{ $genre->id }}" data-genre-name="{{ $genre->name }}" @checked(in_array($genre->id, (array) old('genre_ids', [])))>
                                            <span>{{ $genre->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                            <div class="genre-selected-list" data-genre-selected aria-live="polite"></div>
                        </div>
                        <div class="form-text">Các thể loại đã chọn sẽ hiện bên dưới.</div>
                        @error('genre_ids')<div class="text-danger small">{{ $message }}</div>@enderror
                        @foreach ($errors->get('genre_ids.*') as $genreErrors)
                            @foreach ($genreErrors as $message)<div class="text-danger small">{{ $message }}</div>@endforeach
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="description">Mô tả (không bắt buộc)</label>
                        <textarea id="description" name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="file">Tệp sách (.epub hoặc .mobi)</label>
                        <input type="file" id="file" name="file" accept=".epub,.mobi"
                               class="form-control @error('file') is-invalid @enderror" data-file-size data-file-size-target="file-size-info">
                        <div class="form-text" id="file-size-info" aria-live="polite">Chưa chọn tệp.</div>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3" data-upload-progress hidden aria-live="polite">
                        <div class="progress" role="progressbar" aria-label="Tiến độ tải tệp" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                            <div class="progress-bar" data-upload-progress-bar style="width: 0%"></div>
                        </div>
                        <div class="form-text mt-1" data-upload-progress-message></div>
                    </div>
                    <div class="alert alert-danger py-2" data-upload-errors role="alert" hidden></div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-dark">Tải lên</button>
                        <a href="{{ route('library') }}" class="btn btn-outline-secondary">Hủy</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
