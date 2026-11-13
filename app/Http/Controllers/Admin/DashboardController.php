<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $userCount = DB::table('users')->count();
        $bookCount = DB::table('books')->count();
        $storageUsed = (int) DB::table('books')->sum('file_size');
        $topBooks = DB::table('reading_sessions')
            ->join('books', 'reading_sessions.book_id', '=', 'books.id')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->groupBy('books.id', 'books.title', 'authors.name')
            ->orderByDesc('read_count')
            ->limit(10)
            ->get([
                'books.id',
                'books.title',
                'authors.name as author_name',
                DB::raw('COUNT(reading_sessions.id) as read_count'),
            ]);

        return view('admin.dashboard', compact('userCount', 'bookCount', 'storageUsed', 'topBooks'));
    }
}
