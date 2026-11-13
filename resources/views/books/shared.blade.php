@extends('layouts.app')
@section('title', 'Sách được chia sẻ với tôi')
@section('content')
<header class="page-heading user-subpage-heading"><div><span class="eyebrow">SÁCH TỪ NHỮNG NGƯỜI BẠN</span><h1>Được chia sẻ với bạn</h1><p>Những cuốn sách người khác đã mở cho bạn cùng đọc.</p></div><a href="{{ route('library') }}" class="library-text-link">Về thư viện <span aria-hidden="true">→</span></a></header>

@if ($shares->isEmpty())
    <div class="alert alert-secondary">Chưa có sách nào được chia sẻ với bạn.</div>
@else
    <div class="shared-book-grid">
        @foreach ($shares as $share)
            <article class="shared-book-card">
                <a class="shared-book-cover" href="{{ route('books.show', $share->book_id) }}" aria-label="Mở sách {{ $share->title }}">
                    @if ($share->cover_path)<img src="{{ route('books.cover', $share->book_id) }}" alt="Bìa sách {{ $share->title }}" loading="lazy">@else<span>{{ mb_substr($share->title, 0, 1) }}</span>@endif
                </a>
                <div class="shared-book-copy"><span class="eyebrow">ĐƯỢC CHIA SẺ</span><h2>{{ $share->title }}</h2><p>{{ $share->author_name ?? 'Chưa rõ tác giả' }}</p><div class="shared-book-owner">Từ <strong>{{ $share->owner_name }}</strong></div><span class="badge {{ $share->permission === 'read_annotate' ? 'text-bg-success' : 'text-bg-secondary' }} mt-2">{{ $share->permission === 'read_annotate' ? 'Đọc và ghi chú' : 'Chỉ đọc' }}</span>@if ($share->expires_at)<small class="d-block mt-2 text-muted">Đến {{ \Illuminate\Support\Carbon::parse($share->expires_at)->format('d/m/Y') }}</small>@endif<a href="{{ route('books.show', $share->book_id) }}" class="btn btn-primary btn-sm mt-auto">Mở sách <span aria-hidden="true">→</span></a></div>
            </article>
        @endforeach
    </div>
    <div class="mt-3">{{ $shares->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
