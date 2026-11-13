<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'username'  => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_.]+$/', 'unique:users,username'],
            'email'     => ['required', 'email', 'max:100', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:6', 'confirmed'],
        ], $this->messages(), $this->attributes());

        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            DB::table('reading_settings')->insert(['user_id' => $user->id]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('library');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'identifier' => ['required', 'string'],
            'password'   => ['required', 'string'],
        ], $this->messages(), $this->attributes());

        $throttleKey = $this->loginThrottleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $errors = (new ViewErrorBag())->put('default', new MessageBag([
                'identifier' => "Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau {$seconds} giây.",
            ]));

            return response()->view('auth.login', [
                'errors' => $errors,
                'old' => ['identifier' => $request->input('identifier')],
            ], 429);
        }

        $field = filter_var($request->identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $request->identifier)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['identifier' => 'Thông tin đăng nhập không đúng.'])->onlyInput('identifier');
        }

        if ($user->status == 0) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['identifier' => 'Tài khoản này đã bị khóa.'])->onlyInput('identifier');
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user);
        $request->session()->regenerate();

        $defaultRoute = in_array($user->role, ['admin', 'manager'], true) ? 'admin.dashboard' : 'library';

        return redirect()->intended(route($defaultRoute));
    }

    private function loginThrottleKey(Request $request): string
    {
        return 'login:'.hash('sha256', mb_strtolower(trim($request->string('identifier')->toString())).'|'.$request->ip());
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function messages(): array
    {
        return [
            'required'   => 'Vui lòng nhập :attribute.',
            'min.string' => ':Attribute phải có ít nhất :min ký tự.',
            'max.string' => ':Attribute không được vượt quá :max ký tự.',
            'email'      => 'Email không hợp lệ.',
            'unique'     => ':Attribute này đã có người dùng.',
            'confirmed'  => 'Mật khẩu nhập lại không khớp.',
            'regex'      => 'Tên đăng nhập chỉ gồm chữ không dấu, số, dấu gạch dưới và dấu chấm.',
        ];
    }

    private function attributes(): array
    {
        return [
            'full_name'  => 'họ và tên',
            'username'   => 'tên đăng nhập',
            'email'      => 'email',
            'password'   => 'mật khẩu',
            'identifier' => 'tên đăng nhập hoặc email',
        ];
    }
}
