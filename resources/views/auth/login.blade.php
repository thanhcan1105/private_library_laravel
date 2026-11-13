@extends('layouts.app')
@section('title', 'Đăng nhập')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-4">Đăng nhập</h1>
                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif
                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="identifier">Tên đăng nhập hoặc email</label>
                        <input type="text" id="identifier" name="identifier" value="{{ old('identifier') }}"
                               class="form-control @error('identifier') is-invalid @enderror">
                        @error('identifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu</label>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password') is-invalid @enderror">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-dark w-100">Đăng nhập</button>
                </form>
                <p class="mt-3 mb-0 text-center"><a href="{{ route('password.request') }}">Quên mật khẩu?</a></p>
                <p class="mt-2 mb-0 text-center">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
