<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReadingSettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'font_size' => ['sometimes', 'required', 'integer', 'between:8,48'],
            'font_family' => ['sometimes', 'required', 'string', 'max:50'],
            'theme' => ['sometimes', 'required', 'string', 'in:light,dark'],
        ], [
            'required' => 'Vui lòng cung cấp :attribute.',
            'font_size.between' => 'Cỡ chữ phải nằm trong khoảng từ :min đến :max.',
            'font_family.max' => 'Kiểu chữ không được vượt quá :max ký tự.',
            'theme.in' => 'Giao diện không hợp lệ (chỉ chấp nhận light hoặc dark).',
        ], [
            'font_size' => 'cỡ chữ',
            'font_family' => 'kiểu chữ',
            'theme' => 'giao diện',
        ]);

        if ($validated === []) {
            return response()->json([
                'message' => 'Không có cài đặt nào để lưu.',
            ], 422);
        }

        $userId = (int) Auth::id();
        DB::transaction(function () use ($userId, $validated): void {
            DB::table('reading_settings')->insertOrIgnore(['user_id' => $userId]);
            DB::table('reading_settings')->where('user_id', $userId)->update($validated);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Đã lưu cài đặt đọc sách.',
        ]);
    }
}
