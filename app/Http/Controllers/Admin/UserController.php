<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(): View
    {
        $users = DB::table('users')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20, [
                'id',
                'username',
                'full_name',
                'email',
                'role',
                'status',
                'storage_limit',
                'created_at',
                DB::raw('(SELECT COALESCE(SUM(file_size), 0) FROM books WHERE books.user_id = users.id) as storage_used'),
            ]);

        return view('admin.users.index', compact('users'));
    }

    public function updateStorageLimit(Request $request, int $id): RedirectResponse
    {
        $this->manageableUser($id);

        $data = $request->validate([
            'storage_limit' => ['required', 'integer', 'min:1'],
        ], [
            'required' => 'Vui lòng nhập :attribute.',
            'integer' => ':Attribute phải là số nguyên.',
            'min' => ':Attribute phải lớn hơn 0.',
        ], [
            'storage_limit' => 'giới hạn lưu trữ (byte)',
        ]);

        DB::table('users')->where('id', $id)->update([
            'storage_limit' => $data['storage_limit'],
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Đã cập nhật giới hạn lưu trữ.');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:0,1'],
        ], [
            'required' => 'Vui lòng chọn trạng thái tài khoản.',
            'in' => 'Trạng thái tài khoản không hợp lệ.',
        ]);

        $user = $this->manageableUser($id);
        $newStatus = (int) $data['status'];

        if ($newStatus === 0 && (int) $user->id === (int) Auth::id()) {
            return back()->withErrors(['status' => 'Bạn không thể khóa tài khoản đang sử dụng.']);
        }

        if ($newStatus === 0 && $user->role === 'admin' && (int) $user->status === 1) {
            $activeAdminCount = DB::table('users')->where('role', 'admin')->where('status', 1)->count();
            if ($activeAdminCount <= 1) {
                return back()->withErrors(['status' => 'Không thể khóa quản trị viên đang hoạt động cuối cùng.']);
            }
        }

        DB::table('users')->where('id', $id)->update(['status' => $newStatus]);

        return redirect()->route('admin.users.index')->with(
            'status',
            $newStatus === 1 ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản. Các lượt chia sẻ do tài khoản này tạo đã bị thu hồi.'
        );
    }

    public function updateRole(Request $request, int $id): RedirectResponse
    {
        abort_unless((int) Auth::id() === 1, 403);

        $data = $request->validate([
            'role' => ['required', 'in:user,manager,admin'],
        ], [
            'required' => 'Vui lòng chọn vai trò.',
            'in' => 'Vai trò không hợp lệ.',
        ]);

        $user = DB::table('users')->where('id', $id)->first(['id', 'role', 'status']);
        abort_if(! $user, 404, 'Không tìm thấy tài khoản.');
        if ((int) $user->id === 1) {
            return back()->withErrors(['role' => 'Không thể thay đổi vai trò của quản trị viên chính.']);
        }

        if ($user->role === 'admin' && $data['role'] !== 'admin' && (int) $user->status === 1) {
            $activeAdminCount = DB::table('users')->where('role', 'admin')->where('status', 1)->count();
            if ($activeAdminCount <= 1) {
                return back()->withErrors(['role' => 'Không thể hạ quyền của quản trị viên đang hoạt động cuối cùng.']);
            }
        }

        DB::table('users')->where('id', $id)->update(['role' => $data['role']]);

        return redirect()->route('admin.users.index')->with(
            'status',
            match ($data['role']) {
                'admin' => 'Đã cấp toàn quyền quản trị admin cho tài khoản.',
                'manager' => 'Đã cấp quyền quản lý trang admin.',
                default => 'Đã gỡ quyền quản lý trang admin.',
            }
        );
    }

    private function manageableUser(int $id): object
    {
        $user = DB::table('users')->where('id', $id)->first(['id', 'role', 'status']);
        abort_if(! $user, 404, 'Không tìm thấy tài khoản.');

        if (Auth::user()?->role === 'manager' && $user->role === 'admin') {
            abort(403);
        }

        return $user;
    }
}
