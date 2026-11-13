<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShelfController extends Controller
{
    public function index(): View
    {
        $shelves = DB::table('shelves')
            ->leftJoin('book_shelf', 'shelves.id', '=', 'book_shelf.shelf_id')
            ->where('shelves.user_id', Auth::id())
            ->groupBy('shelves.id', 'shelves.name', 'shelves.description')
            ->orderBy('shelves.name')
            ->get([
                'shelves.id',
                'shelves.name',
                'shelves.description',
                DB::raw('COUNT(book_shelf.book_id) as book_count'),
            ]);

        return view('shelves.index', compact('shelves'));
    }

    public function show(int $id): View
    {
        $shelf = $this->ownedShelf($id);
        $books = DB::table('book_shelf')
            ->join('books', 'book_shelf.book_id', '=', 'books.id')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->where('book_shelf.shelf_id', $shelf->id)
            ->where('books.user_id', Auth::id())
            ->orderBy('books.title')
            ->get(['books.id', 'books.title', 'books.cover_path', 'authors.name as author_name', 'book_shelf.added_at']);
        $availableBooks = DB::table('books')
            ->where('books.user_id', Auth::id())
            ->whereNotIn('books.id', function ($query) use ($shelf) {
                $query->select('book_id')->from('book_shelf')->where('shelf_id', $shelf->id);
            })
            ->orderBy('books.title')
            ->get(['books.id', 'books.title']);

        return view('shelves.show', compact('shelf', 'books', 'availableBooks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'required' => 'Vui lòng nhập :attribute.',
            'max' => ':Attribute không được vượt quá :max ký tự.',
        ], [
            'name' => 'tên kệ',
            'description' => 'mô tả',
        ]);

        $id = DB::table('shelves')->insertGetId([
            'user_id' => Auth::id(),
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('shelves.show', $id)->with('status', 'Đã tạo kệ sách.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $shelf = $this->ownedShelf($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'required' => 'Vui lòng nhập :attribute.',
            'max' => ':Attribute không được vượt quá :max ký tự.',
        ], [
            'name' => 'tên kệ',
            'description' => 'mô tả',
        ]);

        DB::table('shelves')->where('id', $shelf->id)->update([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('shelves.index')->with('status', 'Đã cập nhật kệ sách.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $shelf = $this->ownedShelf($id);
        DB::table('shelves')->where('id', $shelf->id)->delete();

        return redirect()->route('shelves.index')->with('status', 'Đã xóa kệ sách. Các sách trong thư viện vẫn được giữ lại.');
    }

    public function addBook(Request $request, int $id): RedirectResponse
    {
        $shelf = $this->ownedShelf($id);
        $data = $request->validate([
            'book_id' => ['required', 'integer'],
        ], [
            'required' => 'Vui lòng chọn sách.',
            'integer' => 'Sách được chọn không hợp lệ.',
        ]);
        $bookId = (int) $data['book_id'];
        $this->ownedBook($bookId);

        if (DB::table('book_shelf')->where('shelf_id', $shelf->id)->where('book_id', $bookId)->exists()) {
            return back()->withErrors(['book_id' => 'Sách này đã có trong kệ.']);
        }

        try {
            DB::table('book_shelf')->insert([
                'shelf_id' => $shelf->id,
                'book_id' => $bookId,
            ]);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return back()->withErrors(['book_id' => 'Sách này đã có trong kệ.']);
            }

            throw $exception;
        }

        return redirect()->route('shelves.show', $shelf->id)->with('status', 'Đã thêm sách vào kệ.');
    }

    public function removeBook(int $shelfId, int $bookId): RedirectResponse
    {
        $shelf = $this->ownedShelf($shelfId);
        $this->ownedBook($bookId);

        DB::table('book_shelf')
            ->where('shelf_id', $shelf->id)
            ->where('book_id', $bookId)
            ->delete();

        return redirect()->route('shelves.show', $shelf->id)->with('status', 'Đã bỏ sách khỏi kệ.');
    }

    private function ownedShelf(int $shelfId): object
    {
        $shelf = DB::table('shelves')
            ->where('id', $shelfId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $shelf) {
            abort(404, 'Không tìm thấy kệ sách.');
        }

        return $shelf;
    }

    private function ownedBook(int $bookId): object
    {
        $book = DB::table('books')
            ->where('id', $bookId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $book) {
            abort(404, 'Không tìm thấy sách.');
        }

        return $book;
    }
}
