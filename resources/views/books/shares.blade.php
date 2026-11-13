@extends('layouts.app')
@section('title', 'Chia sẻ sách — ' . $book->title)
@section('content')
<div class="mb-3">
    <h1 class="h4 mb-1">Chia sẻ sách</h1>
    <p class="text-muted mb-1">{{ $book->title }}</p>
    <a href="{{ route('library') }}" class="small text-decoration-none">← Quay lại thư viện</a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">Mời người khác đọc sách</h2>
        <form method="POST" action="{{ route('books.shares.store', $book->id) }}" class="row g-3">
            @csrf
            <div class="col-md-5">
                <label for="recipient-email" class="form-label">Email người nhận</label>
                <input id="recipient-email" type="email" name="recipient_email" class="form-control" maxlength="100" value="{{ old('recipient_email') }}" required>
            </div>
            <div class="col-md-3">
                <label for="share-permission" class="form-label">Quyền</label>
                <select id="share-permission" name="permission" class="form-select" required>
                    <option value="read" @selected(old('permission', 'read') === 'read')>Chỉ đọc</option>
                    <option value="read_annotate" @selected(old('permission') === 'read_annotate')>Đọc, tô sáng và ghi chú</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="share-expires-at" class="form-label">Hết hạn (không bắt buộc)</label>
                <input id="share-expires-at" type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
            </div>
            <div class="col-md-1 d-grid align-self-end">
                <button class="btn btn-dark">Chia sẻ</button>
            </div>
        </form>
    </div>
</div>

<h2 class="h5 mb-3">Người đang được chia sẻ</h2>
@forelse ($shares as $share)
    @php
        $isExpired = $share->expires_at && \Illuminate\Support\Carbon::parse($share->expires_at)->isPast();
    @endphp
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="fw-semibold">{{ $share->recipient_name }}</div>
                <div class="small text-muted">{{ $share->recipient_email }}</div>
                <div class="small mt-1">
                    Quyền: {{ $share->permission === 'read_annotate' ? 'Đọc, tô sáng và ghi chú' : 'Chỉ đọc' }}
                    @if ($share->expires_at)
                        · Hết hạn: {{ $share->expires_at }}
                    @else
                        · Không thời hạn
                    @endif
                    @if ($isExpired)
                        <span class="badge bg-secondary ms-1">Đã hết hạn</span>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('books.shares.destroy', [$book->id, $share->id]) }}" data-confirm-message="Thu hồi quyền truy cập của tài khoản này?" data-confirm-title="Thu hồi quyền truy cập?" data-confirm-accept="Thu hồi" data-confirm-variant="danger">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">Thu hồi</button>
            </form>
        </div>
    </div>
@empty
    <div class="alert alert-secondary">Sách này chưa được chia sẻ.</div>
@endforelse
@endsection
