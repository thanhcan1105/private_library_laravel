@extends('layouts.admin')
@section('title', 'Kho sách công khai')
@section('content')
<header class="admin-page-heading"><div><span class="eyebrow">NỘI DUNG DÙNG CHUNG</span><h1>Kho sách công khai</h1><p>Sách ở đây hiển thị cho mọi người dùng đăng nhập; admin và manager có thể thêm hoặc gỡ sách.</p></div><a href="{{ route('admin.public-books.create') }}" class="btn btn-primary">＋ Thêm sách</a></header>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@include('books.partials.book-uploads', ['uploads' => $bookUploads])
@if ($books->isEmpty())<div class="admin-empty-state"><span aria-hidden="true">▤</span><h3>Kho sách chưa có sách nào</h3><p>Thêm EPUB để người dùng có thể khám phá và lưu vào thư viện.</p><a href="{{ route('admin.public-books.create') }}" class="btn btn-primary">Thêm sách đầu tiên</a></div>
@else
<section class="admin-dashboard-panel"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Sách</th><th>Thể loại</th><th>Người thêm</th><th>Dung lượng</th><th>Ngày thêm</th><th></th></tr></thead><tbody>
@foreach ($books as $book)<tr><td><div class="fw-semibold">{{ $book->title }}</div><small class="text-muted">{{ $book->author_name ?? 'Chưa rõ tác giả' }}</small></td><td>{{ $book->genre_names ?: 'Chưa phân loại' }}</td><td>{{ $book->uploader_name }}</td><td>{{ number_format($book->file_size / 1048576, 1) }} MB</td><td>{{ \Illuminate\Support\Carbon::parse($book->created_at)->format('d/m/Y') }}</td><td><form method="POST" action="{{ route('admin.public-books.unpublish', $book->id) }}" data-confirm-message="Gỡ sách khỏi kho công khai? Người dùng đã lưu sẽ bị bỏ lưu; bản gốc vẫn giữ trong thư viện người tải lên." data-confirm-title="Gỡ sách khỏi kho?" data-confirm-accept="Gỡ khỏi kho" data-confirm-variant="danger">@csrf @method('PATCH')<button class="btn btn-outline-danger btn-sm">Gỡ khỏi kho</button></form></td></tr>@endforeach
</tbody></table></div></section><div class="mt-3">{{ $books->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
