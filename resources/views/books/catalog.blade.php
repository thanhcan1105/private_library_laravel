@extends('layouts.app')
@section('title', 'Kho sách công khai')
@section('content')
<header class="user-subpage-heading">
        <div><span class="eyebrow">KHÁM PHÁ VÀ ĐỌC</span><h1>Kho sách công khai</h1><p>Sách do quản trị viên và quản lý tuyển chọn. Lưu sách để đưa vào thư viện riêng của bạn.</p></div>
</header>

@if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

<section class="library-filter-panel" aria-label="Tìm sách công khai">
    <form method="GET" action="{{ route('catalog.index') }}" class="library-filter">
        <div class="library-search"><span class="library-search-icon" aria-hidden="true">⌕</span><label class="visually-hidden" for="catalog-search">Tìm tiêu đề hoặc tác giả</label><input id="catalog-search" name="search" value="{{ $search }}" class="form-control" placeholder="Tìm từ đầu tiêu đề hoặc tên tác giả"></div>
        <select name="genre_id" class="form-select" aria-label="Lọc theo thể loại"><option value="">Tất cả thể loại</option>@foreach ($genres as $genre)<option value="{{ $genre->id }}" @selected((string) request('genre_id') === (string) $genre->id)>{{ $genre->name }}</option>@endforeach</select>
        <button class="btn btn-primary">Tìm sách</button>
        @if ($search !== '' || request()->filled('genre_id'))<a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary">Xóa lọc</a>@endif
    </form>
</section>

@if ($books->isEmpty())
    <div class="library-empty"><div class="library-empty-mark" aria-hidden="true">▤</div><h2>{{ $search !== '' || request()->filled('genre_id') ? 'Chưa tìm thấy sách phù hợp' : 'Kho sách đang được cập nhật' }}</h2><p>{{ $search !== '' || request()->filled('genre_id') ? 'Thử đổi từ khóa hoặc thể loại.' : 'Sách được admin và manager thêm vào sẽ xuất hiện ở đây.' }}</p></div>
@else
    <section class="book-grid" aria-label="Sách công khai">
        @foreach ($books as $book)
            @php($coverTone = (($loop->iteration - 1) % 4) + 1)
            <article class="book-card">
                <a class="book-cover book-cover-tone-{{ $coverTone }}" href="{{ route('books.show', $book->id) }}" aria-label="Đọc {{ $book->title }}">
                    <img src="{{ route('books.cover', $book->id) }}" alt="Bìa sách {{ $book->title }}" loading="lazy" onerror="this.hidden = true; this.parentElement.classList.add('cover-failed'); this.nextElementSibling.hidden = false">
                    <span class="book-cover-fallback" hidden><span class="book-cover-fallback-mark" aria-hidden="true">{{ mb_substr($book->title, 0, 1) }}</span><span class="book-cover-kicker">KHO CÔNG KHAI</span><span class="book-cover-title">{{ $book->title }}</span><span class="book-cover-author">{{ $book->author_name ?? 'Chưa rõ tác giả' }}</span></span>
                </a>
                <div class="book-card-content"><div class="book-card-topline"><span class="book-genre">{{ $book->genre_names ?: 'Chưa phân loại' }}</span><span class="badge text-bg-info">Công khai</span></div>
                    <h2 class="book-card-title"><a href="{{ route('books.show', $book->id) }}">{{ $book->title }}</a></h2>
                    <div class="book-card-author">{{ $book->author_name ?? 'Chưa rõ tác giả' }}</div>
                    <div class="book-card-actions">
                        <a href="{{ route('books.show', $book->id) }}" class="btn btn-primary btn-sm">Đọc sách <span aria-hidden="true">→</span></a>
                        @if ($book->is_saved)
                            @if ($book->is_owned)<button class="btn btn-outline-secondary btn-sm" disabled>Đã có trong thư viện</button>
                            @else<form method="POST" action="{{ route('catalog.saved.destroy', $book->id) }}">@csrf @method('DELETE')<button class="btn btn-outline-secondary btn-sm">Đã lưu · Bỏ lưu</button></form>@endif
                        @else
                            <form method="POST" action="{{ route('catalog.saved.store', $book->id) }}">@csrf<button class="btn btn-outline-primary btn-sm">＋ Lưu sách</button></form>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </section>
    <div class="library-pagination-label">Đang hiển thị {{ $books->count() }} cuốn trên trang này</div>
    <div class="mt-2">{{ $books->links('pagination.cursor-bootstrap') }}</div>
@endif
@endsection
