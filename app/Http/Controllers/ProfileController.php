<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ], [
            'required' => 'Vui lòng nhập :attribute.',
            'required_with' => 'Vui lòng nhập mật khẩu hiện tại để đổi mật khẩu.',
            'email' => 'Email không hợp lệ.',
            'max' => ':Attribute không được vượt quá :max ký tự.',
            'unique' => 'Email này đã được tài khoản khác sử dụng.',
            'password.min' => 'Mật khẩu mới phải có ít nhất :min ký tự.',
            'confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ], [
            'full_name' => 'họ và tên',
            'email' => 'email',
            'current_password' => 'mật khẩu hiện tại',
            'password' => 'mật khẩu mới',
        ]);

        if (! empty($data['password']) && ! Hash::check($data['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.'])
                ->withInput($request->only('full_name', 'email'));
        }

        $updates = [
            'full_name' => trim($data['full_name']),
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        DB::table('users')->where('id', $user->id)->update($updates);

        if (! empty($data['password'])) {
            $request->session()->regenerate();
        }

        return redirect()->route('profile.edit')->with('status', 'Đã cập nhật hồ sơ.');
    }
}
