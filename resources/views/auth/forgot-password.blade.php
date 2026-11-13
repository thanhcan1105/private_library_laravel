@extends('layouts.app')
@section('title', 'Quên mật khẩu')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Quên mật khẩu</h1>
                <p class="text-muted">Nhập địa chỉ email đã đăng ký. Nếu địa chỉ tồn tại, hệ thống sẽ tạo liên kết đặt lại mật khẩu.</p>
                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif
                <form method="POST" action="{{ route('password.email') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-dark w-100">Gửi liên kết đặt lại</button>
                </form>
                <p class="mt-3 mb-0 text-center"><a href="{{ route('login') }}">Quay lại đăng nhập</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
