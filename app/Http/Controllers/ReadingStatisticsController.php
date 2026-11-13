<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReadingStatisticsController extends Controller
{
    public function index(): View
    {
        $now = now();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $monthStart = $now->copy()->startOfMonth();
        $weekSeconds = $this->readingSecondsBetween($weekStart, $now);
        $monthSeconds = $this->readingSecondsBetween($monthStart, $now);
        $weekDuration = $this->formatDuration($weekSeconds);
        $monthDuration = $this->formatDuration($monthSeconds);
        $booksStarted = DB::table('reading_progress')
            ->where('user_id', Auth::id())
            ->count();
        $finishedBooks = DB::table('reading_progress')
            ->where('user_id', Auth::id())
            ->where('percent', '>=', 95)
            ->count();
        $history = DB::table('reading_sessions')
            ->join('books', 'reading_sessions.book_id', '=', 'books.id')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->where('reading_sessions.user_id', Auth::id())
            ->orderByDesc('reading_sessions.started_at')
            ->orderByDesc('reading_sessions.id')
            ->paginate(15, [
                'reading_sessions.id',
                'reading_sessions.book_id',
                'reading_sessions.started_at',
                'reading_sessions.ended_at',
                'books.title',
                'authors.name as author_name',
                DB::raw('TIMESTAMPDIFF(SECOND, reading_sessions.started_at, COALESCE(reading_sessions.ended_at, reading_sessions.started_at)) as duration_seconds'),
            ]);
        $history->getCollection()->transform(function (object $session) {
            $session->duration_label = $this->formatDuration((int) $session->duration_seconds);

            return $session;
        });

        return view('statistics.index', compact('now', 'weekStart', 'monthStart', 'weekSeconds', 'monthSeconds', 'weekDuration', 'monthDuration', 'booksStarted', 'finishedBooks', 'history'));
    }

    private function readingSecondsBetween(Carbon $start, Carbon $end): int
    {
        return (int) DB::table('reading_sessions')
            ->where('user_id', Auth::id())
            ->whereBetween('started_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, started_at))), 0) as total_seconds')
            ->value('total_seconds');
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours === 0) {
            return $minutes === 0 && $seconds > 0 ? 'Dưới 1 phút' : $minutes.' phút';
        }

        return $minutes === 0 ? $hours.' giờ' : $hours.' giờ '.$minutes.' phút';
    }
}
