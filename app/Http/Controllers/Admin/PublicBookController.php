<?php

namespace App\Http\Controllers\Admin;

use App\Events\NotificationUnreadCountUpdated;
use App\Events\PublicBookSubmissionChanged;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PublicBookSubmissionNotification;
use App\Services\NotificationFeedService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicBookController extends Controller
{
    public function index(): View
    {
        $books = DB::table('books')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->join('users as uploaders', 'books.user_id', '=', 'uploaders.id')
            ->where('books.is_public', true)
            ->orderByDesc('books.created_at')
            ->select(
                'books.id', 'books.title', 'books.cover_path', 'books.file_size', 'books.created_at',
                'authors.name as author_name',
                'uploaders.username as uploader_name',
            )
            ->selectRaw("(SELECT GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ', ') FROM book_genre bg JOIN genres g ON g.id = bg.genre_id WHERE bg.book_id = books.id) as genre_names")
            ->paginate(20);

        $bookUploads = DB::table('book_uploads')
            ->where('user_id', Auth::id())
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'title', 'status', 'progress_percent', 'progress_message', 'error_message', 'created_at', 'is_public', 'source_format']);

        return view('admin.public-books.index', compact('books', 'bookUploads'));
    }

    public function unpublish(int $id): RedirectResponse
    {
        DB::transaction(function () use ($id) {
            $book = DB::table('books')->where('id', $id)->where('is_public', true)
                ->lockForUpdate()->first(['id']);
            abort_if(! $book, 404, 'Không tìm thấy sách trong kho công khai.');

            DB::table('saved_books')->where('book_id', $id)->delete();
            DB::table('books')->where('id', $id)->update(['is_public' => false]);
        });

        return redirect()->route('admin.public-books.index')->with('status', 'Đã gỡ sách khỏi kho công khai. Bản sách gốc vẫn nằm trong thư viện của người tải lên.');
    }

    public function submissions(): View
    {
        $submissions = $this->pendingSubmissionsQuery()->paginate(20);

        return view('admin.public-books.submissions', compact('submissions'));
    }

    public function pollSubmissions(): JsonResponse
    {
        $pendingQuery = DB::table('public_book_submissions')->where('status', 'pending');
        $bookIds = DB::table('public_book_submissions')
            ->where('status', 'pending')
            ->orderByDesc('submitted_at')
            ->limit(20)
            ->pluck('book_id')
            ->map(fn ($bookId): int => (int) $bookId);

        return response()->json(['book_ids' => $bookIds, 'count' => $pendingQuery->count()]);
    }

    public function submissionCard(int $id): JsonResponse
    {
        $submission = $this->pendingSubmissionsQuery()->where('books.id', $id)->first();
        abort_if(! $submission, 404);

        return response()->json([
            'book_id' => $id,
            'html' => view('admin.public-books._submission-card', compact('submission'))->render(),
        ]);
    }

    public function approveSubmission(int $id, NotificationFeedService $notificationFeed): RedirectResponse
    {
        [$book, $notificationMarkedRead] = DB::transaction(function () use ($id, $notificationFeed) {
            $submission = DB::table('public_book_submissions')->where('book_id', $id)->where('status', 'pending')->lockForUpdate()->first();
            abort_if(! $submission, 404, 'Không tìm thấy đề xuất đang chờ duyệt.');
            $book = DB::table('books')->where('id', $id)->lockForUpdate()->first(['user_id', 'title']);
            abort_if(! $book, 404, 'Không tìm thấy sách.');
            DB::table('books')->where('id', $id)->update(['is_public' => true]);
            DB::table('public_book_submissions')->where('id', $submission->id)->update([
                'status' => 'approved', 'reviewed_by' => Auth::id(), 'review_note' => null, 'reviewed_at' => now(),
            ]);
            $uploader = User::query()->findOrFail($book->user_id);
            try {
                $uploader->notify(new PublicBookSubmissionNotification('approved', $book->title, 'user', $id));
            } catch (BroadcastException $exception) {
                Log::warning('An approval notification was saved but could not be broadcast.', [
                    'user_id' => $uploader->id,
                    'exception' => $exception::class,
                ]);
            }

            return [$book, $notificationFeed->markSubmissionAsRead(Auth::user(), $id)];
        });

        if ($notificationMarkedRead) {
            $this->broadcastAdminUnreadCount($notificationFeed);
        }
        $this->broadcastReviewQueueChange($id, 'approved', $book->title);

        return redirect()->route('admin.public-submissions.index')->with('status', 'Đã duyệt sách và đưa vào kho công khai.');
    }

    public function rejectSubmission(Request $request, int $id, NotificationFeedService $notificationFeed): RedirectResponse
    {
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']], ['max' => 'Lý do không được vượt quá :max ký tự.']);
        [$book, $notificationMarkedRead] = DB::transaction(function () use ($id, $data, $notificationFeed) {
            $submission = DB::table('public_book_submissions')->where('book_id', $id)->where('status', 'pending')->lockForUpdate()->first();
            abort_if(! $submission, 404, 'Không tìm thấy đề xuất đang chờ duyệt.');
            $book = DB::table('books')->where('id', $id)->first(['user_id', 'title']);
            abort_if(! $book, 404, 'Không tìm thấy sách.');
            DB::table('public_book_submissions')->where('id', $submission->id)->update([
                'status' => 'rejected', 'reviewed_by' => Auth::id(), 'review_note' => $data['review_note'] ?? null, 'reviewed_at' => now(),
            ]);
            $uploader = User::query()->findOrFail($book->user_id);
            try {
                $uploader->notify(new PublicBookSubmissionNotification('rejected', $book->title, 'user', $id, $data['review_note'] ?? null));
            } catch (BroadcastException $exception) {
                Log::warning('A rejection notification was saved but could not be broadcast.', [
                    'user_id' => $uploader->id,
                    'exception' => $exception::class,
                ]);
            }

            return [$book, $notificationFeed->markSubmissionAsRead(Auth::user(), $id)];
        });

        if ($notificationMarkedRead) {
            $this->broadcastAdminUnreadCount($notificationFeed);
        }
        $this->broadcastReviewQueueChange($id, 'rejected', $book->title);

        return redirect()->route('admin.public-submissions.index')->with('status', 'Đã từ chối đề xuất. Sách vẫn ở thư viện riêng của người tải lên.');
    }

    private function broadcastReviewQueueChange(int $bookId, string $eventName, string $title): void
    {
        try {
            event(new PublicBookSubmissionChanged($bookId, $eventName, $title));
        } catch (BroadcastException $exception) {
            Log::warning('The public-book review queue update could not be broadcast.', [
                'book_id' => $bookId,
                'exception' => $exception::class,
            ]);
        }
    }

    private function pendingSubmissionsQuery(): Builder
    {
        return DB::table('public_book_submissions as submissions')
            ->join('books', 'submissions.book_id', '=', 'books.id')
            ->join('users as uploaders', 'books.user_id', '=', 'uploaders.id')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->where('submissions.status', 'pending')
            ->orderByDesc('submissions.submitted_at')
            ->select('submissions.*', 'books.title', 'books.description', 'books.cover_path', 'books.file_size', 'books.id as book_id', 'authors.name as author_name', 'uploaders.username as uploader_name')
            ->selectRaw("(SELECT GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ', ') FROM book_genre bg JOIN genres g ON g.id = bg.genre_id WHERE bg.book_id = books.id) as genre_names");
    }

    private function broadcastAdminUnreadCount(NotificationFeedService $notificationFeed): void
    {
        $user = Auth::user();

        try {
            event(new NotificationUnreadCountUpdated(
                (int) $user->id,
                'admin',
                $notificationFeed->unreadCount($user, 'admin'),
            ));
        } catch (BroadcastException $exception) {
            Log::warning('The admin unread notification count could not be broadcast after reviewing a submission.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
