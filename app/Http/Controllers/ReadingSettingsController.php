<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReadingSettingsController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'font_size'   => ['sometimes', 'required', 'integer', 'between:8,48'],
            'font_family' => ['sometimes', 'required', 'string', 'max:50'],
            'theme'       => ['required', 'string', 'in:light,dark'],
        ], [
            'required'          => 'Vui lòng cung cấp :attribute.',
            'font_size.between' => 'Cỡ chữ phải nằm trong khoảng từ :min đến :max.',
            'font_family.max'   => 'Kiểu chữ không được vượt quá :max ký tự.',
            'theme.in'          => 'Giao diện không hợp lệ (chỉ chấp nhận light hoặc dark).',
        ], [
            'font_size'   => 'cỡ chữ',
            'font_family' => 'kiểu chữ',
            'theme'       => 'giao diện',
        ]);

        $currentSettings = DB::table('reading_settings')->where('user_id', Auth::id())->first();

        DB::table('reading_settings')->updateOrInsert(
            ['user_id' => Auth::id()],
            [
                'font_size'   => (int) ($validated['font_size'] ?? $currentSettings?->font_size ?? 16),
                'font_family' => $validated['font_family'] ?? $currentSettings?->font_family ?? 'Serif',
                'theme'       => $validated['theme'],
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã lưu cài đặt đọc sách.',
        ]);
    }
}
