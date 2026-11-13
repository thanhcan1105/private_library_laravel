<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GenreController extends Controller
{
    public function index(): View
    {
        $genres = DB::table('genres')
            ->leftJoin('book_genre', 'genres.id', '=', 'book_genre.genre_id')
            ->groupBy('genres.id', 'genres.name', 'genres.description')
            ->orderBy('genres.name')
            ->get([
                'genres.id',
                'genres.name',
                'genres.description',
                DB::raw('COUNT(book_genre.book_id) as book_count'),
            ]);

        return view('admin.genres.index', compact('genres'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:genres,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ], $this->messages(), [
            'name' => 'tên thể loại',
            'description' => 'mô tả',
        ]);

        try {
            DB::table('genres')->insert([
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
            ]);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return back()->withErrors(['name' => 'Thể loại này đã tồn tại.'])->withInput();
            }

            throw $exception;
        }

        return redirect()->route('admin.genres.index')->with('status', 'Đã thêm thể loại.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $genre = DB::table('genres')->where('id', $id)->first(['id']);
        abort_if(! $genre, 404, 'Không tìm thấy thể loại.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('genres', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:255'],
        ], $this->messages(), [
            'name' => 'tên thể loại',
            'description' => 'mô tả',
        ]);

        try {
            DB::table('genres')->where('id', $id)->update([
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
            ]);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return back()->withErrors(['name' => 'Thể loại này đã tồn tại.'])->withInput();
            }

            throw $exception;
        }

        return redirect()->route('admin.genres.index')->with('status', 'Đã cập nhật thể loại.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $genre = DB::table('genres')->where('id', $id)->first(['id']);
        abort_if(! $genre, 404, 'Không tìm thấy thể loại.');

        try {
            DB::table('genres')->where('id', $id)->delete();
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1451) {
                return back()->withErrors(['genre' => 'Không thể xóa thể loại đang được sách sử dụng.']);
            }

            throw $exception;
        }

        return redirect()->route('admin.genres.index')->with('status', 'Đã xóa thể loại.');
    }

    private function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'max' => ':Attribute không được vượt quá :max ký tự.',
            'unique' => 'Tên thể loại này đã tồn tại.',
        ];
    }
}
