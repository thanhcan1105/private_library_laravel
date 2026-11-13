@extends('layouts.app')
@section('title', $shelf->name . ' — Kệ sách')
@section('content')
<header class="page-heading user-subpage-heading"><div><span class="eyebrow">KỆ SÁCH CỦA BẠN</span><h1>{{ $shelf->name }}</h1><p>{{ $shelf->description ?: 'Những cuốn sách bạn đã gom vào góc này.' }}</p></div><a href="{{ route('shelves.index') }}" class="library-text-link">Tất cả kệ sách <span aria-hidden="true">→</span></a></header>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4 shelf-add-book-card">
    <div class="card-body">
        <h2 class="h6">Thêm sách từ thư viện</h2>
        @if ($availableBooks->isNotEmpty())
            <form method="POST" action="{{ route('shelves.books.store', $shelf->id) }}" class="row g-2">
                @csrf
                <div class="col-md-9">
                    <label class="visually-hidden" for="book-id">Chọn sách</label>
                    <select id="book-id" name="book_id" class="form-select" required>
                        <option value="">Chọn sách trong thư viện</option>
                        @foreach ($availableBooks as $book)
                            <option value="{{ $book->id }}">{{ $book->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-dark">Thêm vào kệ</button>
                </div>
            </form>
        @else
            <p class="text-muted small mb-0">Tất cả sách trong thư viện đã nằm trong kệ này, hoặc thư viện chưa có sách.</p>
        @endif
    </div>
</div>

@if ($books->isEmpty())
    <div class="library-empty"><div class="library-empty-mark" aria-hidden="true">▤</div><h2>Kệ này chưa có sách</h2><p>Chọn một cuốn trong thư viện phía trên để bắt đầu sắp xếp.</p></div>
@else
    <div class="shelf-book-grid">
        @foreach ($books as $book)
            <article class="shelf-book-card"><a class="shelf-book-cover" href="{{ route('books.show', $book->id) }}" aria-label="Mở {{ $book->title }}">@if ($book->cover_path)<img src="{{ route('books.cover', $book->id) }}" alt="Bìa sách {{ $book->title }}" loading="lazy">@else<span>{{ mb_substr($book->title, 0, 1) }}</span>@endif</a><div><h2><a href="{{ route('books.show', $book->id) }}">{{ $book->title }}</a></h2><p>{{ $book->author_name ?? 'Chưa rõ tác giả' }}</p><form method="POST" action="{{ route('shelves.books.destroy', [$shelf->id, $book->id]) }}">@csrf @method('DELETE')<button class="shelf-remove-link">Bỏ khỏi kệ</button></form></div></article>
        @endforeach
    </div>
@endif
@endsection
