@extends('layouts.app')
@section('title', 'Thư viện của tôi')
@section('content')
<header class="user-library-heading">
    <div><span class="eyebrow">THƯ VIỆN CÁ NHÂN</span><h1>Chào {{ Auth::user()->full_name }} <span aria-hidden="true">✦</span></h1><p>Sách của bạn, được sắp xếp để dễ tìm và đọc tiếp.</p></div>
    <a href="{{ route('books.create') }}" class="btn btn-primary library-upload-cta"><span aria-hidden="true">＋</span> Thêm sách mới</a>
</header>

<div class="library-catalog-layout">
    <aside class="library-catalog-sidebar" aria-label="Danh mục thư viện">
        <section class="catalog-sidebar-panel">
            <h2>Danh mục sách</h2>
            <a class="catalog-category-link @if (!request()->filled('genre_id')) active @endif" href="{{ route('library', ['search' => $search]) }}"><span aria-hidden="true">▦</span>Tất cả sách<span class="catalog-category-count">{{ $libraryCount }}</span></a>
            @foreach ($genres as $genre)
                <a class="catalog-category-link @if ((string) request('genre_id') === (string) $genre->id) active @endif" href="{{ route('library', ['genre_id' => $genre->id, 'search' => $search]) }}"><span aria-hidden="true">›</span>{{ $genre->name }}</a>
            @endforeach
        </section>
        <section class="catalog-sidebar-panel catalog-shortcuts">
            <h2>Không gian của bạn</h2>
            <a href="{{ route('shelves.index') }}" class="catalog-shortcut-link"><span aria-hidden="true">▤</span>Kệ sách</a>
            <a href="{{ route('books.shared') }}" class="catalog-shortcut-link"><span aria-hidden="true">↗</span>Được chia sẻ</a>
            <a href="{{ route('statistics.index') }}" class="catalog-shortcut-link"><span aria-hidden="true">◷</span>Thống kê đọc</a>
        </section>
        <div class="catalog-sidebar-note"><span aria-hidden="true">✦</span><p>Sách bạn tự tải lên luôn được giữ riêng tư.</p></div>
    </aside>

    <div class="library-catalog-content">
        @if ($recentBook)
    <section class="continue-reading-card" aria-label="Tiếp tục đọc">
        <div class="continue-reading-cover">
            <img src="{{ route('books.cover', $recentBook->id) }}" alt="Bìa sách {{ $recentBook->title }}" loading="lazy" onerror="this.hidden = true; this.nextElementSibling.hidden = false">
            <span hidden>{{ mb_substr($recentBook->title, 0, 1) }}</span>
        </div>
        <div class="continue-reading-copy">
            <span class="eyebrow">ĐỌC GẦN ĐÂY</span>
            <h2>{{ $recentBook->title }}</h2>
            <p>{{ $recentBook->author_name ?? 'Chưa rõ tác giả' }} <span aria-hidden="true">·</span> Đọc lần cuối {{ \Illuminate\Support\Carbon::parse($recentBook->last_read_at)->locale('vi')->diffForHumans() }}</p>
            <div class="continue-progress-row"><div class="progress" role="progressbar" aria-label="Tiến độ đọc {{ $recentBook->title }}" aria-valuenow="{{ (float) $recentBook->progress_percent }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ min(100, (float) $recentBook->progress_percent) }}%"></div></div><span>{{ number_format((float) $recentBook->progress_percent, 1) }}%</span></div>
            <a href="{{ route('books.show', $recentBook->id) }}" class="btn btn-primary">Đọc tiếp <span aria-hidden="true">→</span></a>
        </div>
        <div class="continue-reading-ornament" aria-hidden="true">ĐỌC<br>CHẬM<br>THÔI</div>
    </section>
@else
    <section class="reading-welcome-card">
        <span class="eyebrow">BẮT ĐẦU TỪ MỘT TRANG SÁCH</span>
        <h2>Chọn một cuốn,<br>rồi để mình đi xa hơn.</h2>
        <p>Thư viện sẽ ghi nhớ vị trí của bạn để lần sau có thể đọc tiếp dễ dàng.</p>
        <span class="welcome-illustration" aria-hidden="true">❧</span>
    </section>
@endif

<div class="library-toolbar-heading"><div><span class="eyebrow">BỘ SƯU TẬP RIÊNG</span><h2>{{ request('genre_id') ? ($genres->firstWhere('id', (int) request('genre_id'))->name ?? 'Sách của bạn') : 'Sách của bạn' }} <span>{{ $books->total() }}</span></h2></div><span class="catalog-sort-label">Mới thêm gần đây</span></div>

@if (session('status'))
    <div class="alert alert-success mb-4" role="status">{{ session('status') }}</div>
@endif
@if ($errors->has('submission'))
    <div class="alert alert-danger mb-4" role="alert">{{ $errors->first('submission') }}</div>
@endif
@include('books.partials.book-uploads', ['uploads' => $bookUploads])

<section class="library-filter-panel" aria-label="Tìm kiếm và lọc sách">
    <form id="library-filter-form" method="GET" action="{{ route('library') }}" class="library-filter">
        <div class="library-search">
            <span class="library-search-icon" aria-hidden="true">⌕</span>
            <label class="visually-hidden" for="search">Tìm tiêu đề hoặc tác giả</label>
            <input id="search" name="search" value="{{ $search }}" class="form-control" placeholder="Tìm theo tiêu đề hoặc tác giả">
            @if (request()->filled('genre_id'))<input type="hidden" name="genre_id" value="{{ request('genre_id') }}">@endif
        </div>
        <button class="btn btn-primary">Tìm sách</button>
        <button id="clear-library-filters" type="button" class="btn btn-outline-secondary">Xóa lọc</button>
    </form>
</section>

@if ($books->isEmpty())
    <div class="library-empty">
        <div class="library-empty-mark" aria-hidden="true">▤</div>
        @if ($search !== '' || request()->filled('genre_id'))
            <h2>Chưa tìm thấy sách phù hợp</h2>
            <p>Thử đổi từ khóa hoặc xóa bộ lọc để xem lại toàn bộ thư viện.</p>
            <button id="clear-empty-library-filters" type="button" class="btn btn-outline-primary">Xóa bộ lọc</button>
        @else
            <h2>Thư viện của bạn đang chờ cuốn sách đầu tiên</h2>
            <p>Tải EPUB của bạn lên hoặc chọn sách từ kho công khai để thêm vào thư viện.</p>
            <a href="{{ route('books.create') }}" class="btn btn-primary">Tải sách lên</a>
            <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary ms-2">Khám phá kho sách</a>
        @endif
    </div>
@else
    <section class="book-grid" aria-label="Sách trong thư viện">
        @foreach ($books as $book)
            @php
                $percent = $book->progress_percent !== null ? (float) $book->progress_percent : null;
                $coverTone = (($loop->iteration - 1) % 4) + 1;
            @endphp
            <article class="book-card" data-book-card data-book-id="{{ $book->id }}">
                <a class="book-cover book-cover-tone-{{ $coverTone }}" href="{{ route('books.show', $book->id) }}" aria-label="Đọc {{ $book->title }}">
                    <img src="{{ route('books.cover', $book->id) }}" alt="Bìa sách {{ $book->title }}" loading="lazy" onerror="this.hidden = true; this.parentElement.classList.add('cover-failed'); this.nextElementSibling.hidden = false">
                    <span class="book-cover-fallback" hidden><span class="book-cover-fallback-mark" aria-hidden="true">{{ mb_substr($book->title, 0, 1) }}</span><span class="book-cover-kicker">Thư viện riêng</span><span class="book-cover-title">{{ $book->title }}</span><span class="book-cover-author">{{ $book->author_name ?? 'Chưa rõ tác giả' }}</span></span>
                </a>
                <div class="book-card-content">
                    <div class="book-card-topline">
                        <span class="book-genre">{{ $book->genre_names ?: 'Chưa phân loại' }}</span>
                        @if ($book->is_public && (int) $book->user_id !== (int) Auth::id())<span class="badge text-bg-info">Sách công khai</span>@endif
                        @if ((int) $book->user_id === (int) Auth::id())
                            @if ($book->submission_status === 'pending')
                                <span class="badge text-bg-warning" data-submission-status data-submission-state="pending">Đang chờ duyệt</span>
                            @elseif ($book->submission_status === 'rejected')
                                <span class="badge text-bg-danger" data-submission-status data-submission-state="rejected">Chưa được duyệt</span>
                            @elseif ($book->is_public)
                                <span class="badge text-bg-info" data-submission-status data-submission-state="approved">Sách công khai</span>
                            @else
                                <span class="badge" data-submission-status data-submission-state="" hidden></span>
                            @endif
                        @endif
                        @if ($percent !== null && $percent >= 95)
                            <span class="badge text-bg-success">Đã đọc xong</span>
                        @endif
                    </div>
                    <h2 class="book-card-title"><a href="{{ route('books.show', $book->id) }}">{{ $book->title }}</a></h2>
                    <div class="book-card-author">{{ $book->author_name ?? 'Chưa rõ tác giả' }}</div>

                    <div class="book-progress-label">
                        <span>Tiến độ đọc</span>
                        @if ($percent !== null)
                            <strong>{{ number_format($percent, 1) }}%</strong>
                        @else
                            <span>Chưa bắt đầu</span>
                        @endif
                    </div>
                    <div class="progress book-progress-bar" role="progressbar" aria-label="Tiến độ đọc {{ $book->title }}" aria-valuenow="{{ $percent ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ min(100, $percent ?? 0) }}%"></div>
                    </div>

                    <div class="book-card-meta">
                        <span>{{ number_format($book->file_size / 1048576, 1) }} MB</span>
                        <span>{{ \Illuminate\Support\Carbon::parse($book->created_at)->format('d/m/Y') }}</span>
                    </div>

                    <div class="book-card-actions">
                        <a href="{{ route('books.show', $book->id) }}" class="btn btn-primary btn-sm">{{ $percent !== null && $percent > 0 ? 'Đọc tiếp' : 'Mở sách' }} <span aria-hidden="true">→</span></a>
                        @if ($book->is_public && (int) $book->user_id !== (int) Auth::id())
                            <form method="POST" action="{{ route('catalog.saved.destroy', $book->id) }}">@csrf @method('DELETE')<button class="btn btn-outline-secondary btn-sm">Bỏ lưu</button></form>
                        @else
                        <details class="book-card-options">
                            <summary aria-label="Thao tác với {{ $book->title }}">•••</summary>
                            <div class="book-options-menu">
                                @if ((int) $book->user_id === (int) Auth::id())
                                    <form method="POST" action="{{ route('books.public-submission.store', $book->id) }}" data-public-submission-form data-confirm-message="Gửi sách này cho quản trị viên duyệt lên kho công khai?" data-confirm-title="Gửi đề xuất?" data-confirm-accept="Gửi đề xuất" @if ($book->submission_status === 'pending' || $book->is_public) hidden @endif>@csrf<button data-submission-button>{{ $book->submission_status === 'rejected' ? 'Gửi lại để duyệt' : 'Đề xuất lên kho công khai' }}</button></form>
                                    <p class="small text-danger px-2 mb-1" data-submission-review-note @if ($book->submission_status !== 'rejected' || ! $book->submission_review_note) hidden @endif>Lý do: {{ $book->submission_review_note }}</p>
                                @endif
                                <a href="{{ route('books.shares.manage', $book->id) }}">Chia sẻ sách</a><a href="{{ route('books.edit', $book->id) }}">Sửa thông tin</a><form method="POST" action="{{ route('books.destroy', $book->id) }}" data-confirm-message="Bạn có chắc muốn xóa sách này?" data-confirm-title="Xóa sách?" data-confirm-accept="Xóa khỏi thư viện" data-confirm-variant="danger">@csrf @method('DELETE')<button>Xóa khỏi thư viện</button></form>
                            </div>
                        </details>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </section>
    <div class="library-pagination-label">Đang xem {{ $books->firstItem() }}–{{ $books->lastItem() }} trong {{ $books->total() }} cuốn</div>
    <div class="mt-2">{{ $books->links('pagination::bootstrap-5') }}</div>
@endif
    </div>
</div>

<script>
    function clearLibraryFilters() {
        const filterForm = document.getElementById("library-filter-form");
        if (!filterForm) return;

        filterForm.reset();
        window.location.assign(filterForm.action);
    }

    document.getElementById("clear-library-filters")?.addEventListener("click", clearLibraryFilters);
    document.getElementById("clear-empty-library-filters")?.addEventListener("click", clearLibraryFilters);
</script>
@endsection
