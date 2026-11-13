@extends('layouts.app')
@section('title', 'Tài khoản của tôi')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h1 class="h4 mb-1">Tài khoản của tôi</h1>
                        <p class="text-muted mb-0">Cập nhật tên, email hoặc mật khẩu đăng nhập.</p>
                    </div>
                    <a href="{{ route('library') }}" class="btn btn-outline-secondary btn-sm">Quay lại thư viện</a>
                </div>

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="full-name" class="form-label">Họ và tên</label>
                        <input id="full-name" name="full_name" class="form-control" maxlength="100" value="{{ old('full_name', $user->full_name) }}" required autocomplete="name">
                    </div>

                    <div class="mb-4">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" type="email" name="email" class="form-control" maxlength="100" value="{{ old('email', $user->email) }}" required autocomplete="email">
                    </div>

                    <hr>
                    <h2 class="h6 mt-4">Đổi mật khẩu (không bắt buộc)</h2>
                    <p class="text-muted small">Để giữ nguyên mật khẩu, hãy để trống cả ba ô bên dưới.</p>

                    <div class="mb-3">
                        <label for="current-password" class="form-label">Mật khẩu hiện tại</label>
                        <input id="current-password" type="password" name="current_password" class="form-control" autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label for="new-password" class="form-label">Mật khẩu mới</label>
                        <input id="new-password" type="password" name="password" class="form-control" minlength="6" autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label for="password-confirmation" class="form-label">Nhập lại mật khẩu mới</label>
                        <input id="password-confirmation" type="password" name="password_confirmation" class="form-control" minlength="6" autocomplete="new-password">
                    </div>

                    <button class="btn btn-dark">Lưu thay đổi</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
