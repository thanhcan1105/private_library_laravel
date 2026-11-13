<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthorController extends Controller
{
    public function index(): View
    {
        $authors = DB::table('authors')
            ->leftJoin('books', 'authors.id', '=', 'books.author_id')
            ->groupBy('authors.id', 'authors.name', 'authors.bio')
            ->orderBy('authors.name')
            ->get([
                'authors.id',
                'authors.name',
                'authors.bio',
                DB::raw('COUNT(books.id) as book_count'),
            ]);

        return view('admin.authors.index', compact('authors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:12000'],
        ], $this->messages(), [
            'name' => 'tên tác giả',
            'bio' => 'giới thiệu tác giả',
        ]);

        DB::table('authors')->insert([
            'name' => trim($data['name']),
            'bio' => $data['bio'] ?? null,
        ]);

        return redirect()->route('admin.authors.index')->with('status', 'Đã thêm tác giả.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $author = DB::table('authors')->where('id', $id)->first(['id']);
        abort_if(! $author, 404, 'Không tìm thấy tác giả.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:12000'],
        ], $this->messages(), [
            'name' => 'tên tác giả',
            'bio' => 'giới thiệu tác giả',
        ]);

        DB::table('authors')->where('id', $id)->update([
            'name' => trim($data['name']),
            'bio' => $data['bio'] ?? null,
        ]);

        return redirect()->route('admin.authors.index')->with('status', 'Đã cập nhật tác giả.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $author = DB::table('authors')->where('id', $id)->first(['id']);
        abort_if(! $author, 404, 'Không tìm thấy tác giả.');

        try {
            DB::table('authors')->where('id', $id)->delete();
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1451) {
                return back()->withErrors(['author' => 'Không thể xóa tác giả đang được sách sử dụng.']);
            }

            throw $exception;
        }

        return redirect()->route('admin.authors.index')->with('status', 'Đã xóa tác giả.');
    }

    private function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'max' => ':Attribute không được vượt quá :max ký tự.',
        ];
    }
}
