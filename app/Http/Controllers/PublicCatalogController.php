<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PublicCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $searchInput = $request->query('search');
        $search = is_string($searchInput) ? mb_substr(trim($searchInput), 0, 100) : '';
        $genreIdInput = $request->query('genre_id');
        $genreId = filter_var($genreIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $query = DB::table('books')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->leftJoin('saved_books', function ($join) {
                $join->on('saved_books.book_id', '=', 'books.id')
                    ->where('saved_books.user_id', '=', Auth::id());
            })
            ->where('books.is_public', true)
            ->select(
                'books.id',
                'books.user_id',
                'books.title',
                'books.cover_path',
                'books.file_size',
                'books.created_at',
                'authors.name as author_name',
                'saved_books.saved_at'
            )
            ->selectRaw("(SELECT GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ', ') FROM book_genre bg JOIN genres g ON g.id = bg.genre_id WHERE bg.book_id = books.id) as genre_names");

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $searchPrefix = addcslashes($search, '\\%_').'%';
                $builder->where('books.title', 'like', $searchPrefix)
                    ->orWhere('authors.name', 'like', $searchPrefix);
            });
        }

        if ($genreId !== null) {
            $query->whereExists(function ($genre) use ($genreId) {
                $genre->selectRaw('1')->from('book_genre')
                    ->whereColumn('book_genre.book_id', 'books.id')
                    ->where('book_genre.genre_id', $genreId);
            });
        }

        $books = $query->orderByDesc('books.created_at')->orderByDesc('books.id')->cursorPaginate(12)->withQueryString()->through(function ($book) {
            $book->is_saved = $book->saved_at !== null || (int) $book->user_id === (int) Auth::id();
            $book->is_owned = (int) $book->user_id === (int) Auth::id();

            return $book;
        });
        $genres = DB::table('genres')->orderBy('name')->get();

        return view('books.catalog', compact('books', 'genres', 'search'));
    }

    public function save(int $bookId): RedirectResponse
    {
        DB::transaction(function () use ($bookId) {
            $book = DB::table('books')->where('id', $bookId)->where('is_public', true)
                ->lockForUpdate()->first(['id']);
            abort_if(! $book, 404, 'Không tìm thấy sách công khai.');

            DB::table('saved_books')->insertOrIgnore([
                'user_id' => Auth::id(),
                'book_id' => $bookId,
            ]);
        });

        return back()->with('status', 'Đã lưu sách vào thư viện của bạn.');
    }

    public function remove(int $bookId): RedirectResponse
    {
        DB::table('saved_books')
            ->where('user_id', Auth::id())
            ->where('book_id', $bookId)
            ->delete();

        return back()->with('status', 'Đã bỏ sách khỏi thư viện của bạn.');
    }
}
