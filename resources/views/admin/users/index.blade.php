@extends('layouts.admin')
@section('title', 'Quản lý người dùng')
@section('content')
<div class="mb-3">
    <h1 class="h3 mb-1">Quản lý người dùng</h1>
    <p class="text-muted mb-0">Quản lý tài khoản, quyền truy cập trang admin và hạn mức lưu trữ.</p>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="table-responsive">
    <table class="table table-hover align-middle bg-white border shadow-sm">
        <thead class="table-light">
            <tr><th>Tài khoản</th><th>Vai trò</th><th>Trạng thái</th><th style="min-width: 260px;">Lưu trữ (byte)</th><th>Ngày tạo</th><th>Hành động</th></tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $user->full_name }}</div>
                        <div class="small text-muted">{{ $user->username }} · {{ $user->email }}</div>
                    </td>
                    <td style="min-width: 240px;">
                        @php
                            $roleLabel = match ($user->role) {
                                'admin' => (int) $user->id === 1 ? 'Quản trị viên chính' : 'Quản trị viên',
                                'manager' => 'Quản lý',
                                default => 'Người dùng',
                            };
                        @endphp
                        <span class="badge {{ $user->role === 'admin' ? 'bg-dark' : ($user->role === 'manager' ? 'bg-primary' : 'bg-secondary') }}">{{ $roleLabel }}</span>
                        @if ((int) Auth::id() === 1 && (int) $user->id !== 1)
                            <form method="POST" action="{{ route('admin.users.role', $user->id) }}" class="d-flex gap-2 mt-2">
                                @csrf
                                @method('PATCH')
                                <select name="role" class="form-select form-select-sm" aria-label="Vai trò của {{ $user->username }}">
                                    <option value="user" @selected($user->role === 'user')>Người dùng</option>
                                    <option value="manager" @selected($user->role === 'manager')>Quản lý admin</option>
                                    <option value="admin" @selected($user->role === 'admin')>Quản trị viên</option>
                                </select>
                                <button class="btn btn-outline-primary btn-sm">Lưu</button>
                            </form>
                        @endif
                    </td>
                    <td><span class="badge {{ (int) $user->status === 1 ? 'bg-success' : 'bg-danger' }}">{{ (int) $user->status === 1 ? 'Hoạt động' : 'Đã khóa' }}</span></td>
                    <td>
                        <div class="small text-muted mb-1">Đã dùng {{ number_format($user->storage_used) }} / {{ number_format($user->storage_limit) }}</div>
                        @if (Auth::user()->role === 'admin' || $user->role !== 'admin')
                        <form method="POST" action="{{ route('admin.users.storage-limit', $user->id) }}" class="d-flex gap-2">
                            @csrf
                            @method('PUT')
                            <input type="number" name="storage_limit" class="form-control form-control-sm" min="1" step="1" value="{{ $user->storage_limit }}" aria-label="Giới hạn lưu trữ của {{ $user->username }}" required>
                            <button class="btn btn-outline-primary btn-sm">Lưu</button>
                        </form>
                        @else
                            <span class="small text-muted">Chỉ admin chính</span>
                        @endif
                    </td>
                    <td class="small">{{ $user->created_at }}</td>
                    <td>
                        @if (Auth::user()->role === 'manager' && $user->role === 'admin')
                            <span class="small text-muted">Chỉ admin chính</span>
                        @elseif ((int) $user->status === 1)
                            <form method="POST" action="{{ route('admin.users.status', $user->id) }}" data-confirm-message="Khóa tài khoản này? Các lượt chia sẻ do tài khoản tạo sẽ bị thu hồi." data-confirm-title="Khóa tài khoản?" data-confirm-accept="Khóa tài khoản" data-confirm-variant="danger">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="0">
                                <button class="btn btn-outline-danger btn-sm" @disabled((int) $user->id === (int) Auth::id()) @if ((int) $user->id === (int) Auth::id()) title="Không thể khóa tài khoản đang sử dụng" @endif>Khóa</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.users.status', $user->id) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="1">
                                <button class="btn btn-outline-success btn-sm">Mở khóa</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection
