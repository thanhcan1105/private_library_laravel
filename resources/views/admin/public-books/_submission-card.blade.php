<article class="review-submission-card" data-review-book-id="{{ $submission->book_id }}">
    <div class="review-book-cover">
        <img src="{{ route('books.cover', $submission->book_id) }}" alt="Bìa {{ $submission->title }}" loading="lazy" onerror="this.hidden = true; this.nextElementSibling.hidden = false">
        <span class="review-book-cover-fallback" aria-hidden="true" hidden>{{ mb_substr($submission->title, 0, 1) }}</span>
    </div>

    <div class="review-submission-content">
        <div class="review-submission-meta">
            <span class="review-pending-badge"><span aria-hidden="true"></span> Chờ duyệt</span>
            <span>Gửi bởi <strong>{{ $submission->uploader_name }}</strong></span>
            <time datetime="{{ \Illuminate\Support\Carbon::parse($submission->submitted_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($submission->submitted_at)->format('d/m/Y · H:i') }}</time>
        </div>

        <div class="review-book-details">
            <h2>{{ $submission->title }}</h2>
            <p class="review-book-author">{{ $submission->author_name ?? 'Chưa rõ tác giả' }}</p>
            @if ($submission->description)
                <p class="review-book-description">{{ $submission->description }}</p>
            @endif
            <div class="review-book-tags">
                <span>{{ $submission->genre_names ?: 'Chưa phân loại' }}</span>
                <span>{{ number_format($submission->file_size / 1048576, 1) }} MB</span>
            </div>
        </div>

        <div class="review-submission-actions">
            <a class="btn btn-outline-secondary" href="{{ route('books.show', $submission->book_id) }}" target="_blank" rel="noopener">Xem trước sách <span aria-hidden="true">↗</span></a>
            <form method="POST" action="{{ route('admin.public-submissions.approve', $submission->book_id) }}" data-confirm-message="Duyệt sách và đưa vào kho công khai?" data-confirm-title="Duyệt sách?" data-confirm-accept="Duyệt và công khai">
                @csrf
                <button class="btn btn-primary" type="submit"><span aria-hidden="true">✓</span> Duyệt và công khai</button>
            </form>
            <form method="POST" action="{{ route('admin.public-submissions.reject', $submission->book_id) }}" class="review-reject-form" data-confirm-message="Từ chối đề xuất này?" data-confirm-title="Từ chối đề xuất?" data-confirm-accept="Từ chối" data-confirm-variant="danger">
                @csrf
                <label class="visually-hidden" for="review-note-{{ $submission->book_id }}">Lý do từ chối</label>
                <input id="review-note-{{ $submission->book_id }}" name="review_note" class="form-control" maxlength="2000" data-review-note-book="{{ $submission->book_id }}" placeholder="Lý do từ chối (không bắt buộc)">
                <button class="btn btn-outline-danger" type="submit">Từ chối</button>
            </form>
        </div>
    </div>
</article>
