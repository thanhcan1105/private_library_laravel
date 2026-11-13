<?php

namespace App\Http\Controllers;

use App\Events\PublicBookSubmissionChanged;
use App\Models\User;
use App\Notifications\PublicBookSubmissionNotification;
use DOMDocument;
use DOMXPath;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BookController extends Controller
{
    public function index()
    {
        $query = DB::table('books')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->leftJoin('public_book_submissions as submissions', 'books.id', '=', 'submissions.book_id')
            ->leftJoin('reading_progress', function ($join) {
                $join->on('books.id', '=', 'reading_progress.book_id')
                    ->where('reading_progress.user_id', '=', Auth::id());
            })
            ->where(function ($visibility) {
                $visibility->where('books.user_id', Auth::id())
                    ->orWhereExists(function ($saved) {
                        $saved->selectRaw('1')->from('saved_books')
                            ->whereColumn('saved_books.book_id', 'books.id')
                            ->where('saved_books.user_id', Auth::id());
                    });
            })
            ->select(
                'books.id',
                'books.title',
                'books.file_size',
                'books.created_at',
                'books.cover_path',
                'books.user_id',
                'books.is_public',
                'authors.name as author_name',
                'reading_progress.percent as progress_percent',
                'submissions.status as submission_status',
                'submissions.review_note as submission_review_note'
            )
            ->selectRaw("(SELECT GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ', ') FROM book_genre bg JOIN genres g ON g.id = bg.genre_id WHERE bg.book_id = books.id) as genre_names");

        $search = trim((string) request('search', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('books.title', 'like', '%'.$search.'%')
                    ->orWhere('authors.name', 'like', '%'.$search.'%');
            });
        }

        if (request()->filled('genre_id')) {
            $query->whereExists(function ($genre) {
                $genre->selectRaw('1')->from('book_genre')
                    ->whereColumn('book_genre.book_id', 'books.id')
                    ->where('book_genre.genre_id', request('genre_id'));
            });
        }

        $books = $query->orderByDesc('books.created_at')->paginate(10)->withQueryString();
        $genres = DB::table('genres')->orderBy('name')->get();
        $libraryCount = DB::table('books')->where('user_id', Auth::id())->count()
            + DB::table('saved_books')
                ->join('books', 'saved_books.book_id', '=', 'books.id')
                ->where('saved_books.user_id', Auth::id())
                ->where('books.user_id', '<>', Auth::id())
                ->count();
        $recentBook = DB::table('reading_progress')
            ->join('books', 'reading_progress.book_id', '=', 'books.id')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->where('reading_progress.user_id', Auth::id())
            ->where(function ($visibility) {
                $visibility->where('books.user_id', Auth::id())
                    ->orWhere('books.is_public', true);
            })
            ->whereNotNull('reading_progress.last_read_at')
            ->orderByDesc('reading_progress.last_read_at')
            ->first([
                'books.id',
                'books.title',
                'books.cover_path',
                'authors.name as author_name',
                'reading_progress.percent as progress_percent',
                'reading_progress.last_read_at',
            ]);
        $bookUploads = DB::table('book_uploads')
            ->where('user_id', Auth::id())
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'title', 'status', 'progress_percent', 'progress_message', 'error_message', 'created_at', 'is_public', 'source_format']);

        return view('books.index', compact('books', 'genres', 'search', 'recentBook', 'libraryCount', 'bookUploads'));
    }

    public function bookUploadStatuses(): JsonResponse
    {
        $uploads = DB::table('book_uploads')
            ->where('user_id', Auth::id())
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'status', 'progress_percent', 'progress_message', 'error_message', 'is_public', 'source_format']);

        return response()->json(['uploads' => $uploads]);
    }

    public function dismissBookUpload(int $upload): JsonResponse
    {
        $uploadRecord = DB::table('book_uploads')
            ->where('id', $upload)
            ->where('user_id', Auth::id())
            ->first(['id', 'status']);

        abort_unless($uploadRecord, 404, 'Không tìm thấy thông báo tải sách.');
        abort_if(in_array($uploadRecord->status, ['pending', 'processing'], true), 409, 'Tác vụ đang chạy, chưa thể xóa thông báo.');
        abort_unless(in_array($uploadRecord->status, ['completed', 'failed', 'cancelled'], true), 409, 'Tác vụ này chưa thể xóa thông báo.');

        DB::table('book_uploads')->where('id', $upload)->where('user_id', Auth::id())->delete();

        return response()->json(['status' => 'dismissed']);
    }

    public function cancelBookUpload(int $upload): JsonResponse
    {
        $uploadRecord = DB::transaction(function () use ($upload): ?object {
            $uploadRecord = DB::table('book_uploads')
                ->where('id', $upload)
                ->where('user_id', Auth::id())
                ->lockForUpdate()
                ->first(['id', 'status', 'source_path']);

            abort_unless($uploadRecord, 404, 'Không tìm thấy tác vụ tải sách.');
            abort_if(! in_array($uploadRecord->status, ['pending', 'processing'], true), 409, 'Tác vụ này đã kết thúc.');

            DB::table('book_uploads')->where('id', $upload)->update([
                'status' => 'cancelled',
                'source_path' => $uploadRecord->status === 'pending' ? null : $uploadRecord->source_path,
                'progress_message' => 'Đã hủy tác vụ tải sách.',
                'error_message' => null,
                'updated_at' => now(),
                'processed_at' => now(),
            ]);

            return $uploadRecord;
        });

        if ($uploadRecord->status === 'pending' && $uploadRecord->source_path) {
            Storage::disk('local')->delete($uploadRecord->source_path);
        }

        return response()->json(['status' => 'cancelled', 'message' => 'Đã hủy tác vụ tải sách.']);
    }

    public function edit(int $id)
    {
        $this->ownedBook($id);
        $book = DB::table('books')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->where('books.id', $id)
            ->select('books.*', 'authors.name as author_name')
            ->first();
        $genres = DB::table('genres')->orderBy('name')->get();
        $selectedGenreIds = DB::table('book_genre')->where('book_id', $id)->pluck('genre_id')->map(fn ($genreId) => (int) $genreId)->all();

        return view('books.edit', compact('book', 'genres', 'selectedGenreIds'));
    }

    public function submitForPublicReview(int $id): RedirectResponse
    {
        $book = $this->ownedBook($id);
        if ((bool) $book->is_public) {
            return redirect()->route('library')->withErrors(['submission' => 'Sách này đã có trong kho công khai.']);
        }

        $submitted = DB::transaction(function () use ($id, $book) {
            $submission = DB::table('public_book_submissions')->where('book_id', $id)->lockForUpdate()->first();
            if ($submission?->status === 'pending') {
                return false;
            }

            $values = [
                'status' => 'pending',
                'reviewed_by' => null,
                'review_note' => null,
                'submitted_at' => now(),
                'reviewed_at' => null,
            ];
            if ($submission) {
                DB::table('public_book_submissions')->where('id', $submission->id)->update($values);
            } else {
                DB::table('public_book_submissions')->insert($values + ['book_id' => $id]);
            }

            User::query()->whereIn('role', ['admin', 'manager'])->get()->each(function (User $user) use ($book, $id): void {
                try {
                    $user->notify(new PublicBookSubmissionNotification('submitted', $book->title, 'admin', $id));
                } catch (BroadcastException $exception) {
                    Log::warning('A public-book notification was saved but could not be broadcast.', [
                        'user_id' => $user->id,
                        'exception' => $exception::class,
                    ]);
                }
            });

            return true;
        });

        if (! $submitted) {
            return redirect()->route('library')->withErrors(['submission' => 'Sách này đang chờ quản trị viên duyệt.']);
        }

        $this->broadcastPublicSubmissionChanged($id, 'submitted', $book->title);

        return redirect()->route('library')->with('status', 'Đã gửi sách cho quản trị viên duyệt. Sách vẫn ở chế độ riêng tư trong lúc chờ.');
    }

    private function broadcastPublicSubmissionChanged(int $bookId, string $eventName, string $title): void
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

    public function update(Request $request, int $id)
    {
        $book = $this->ownedBook($id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'author_name' => ['nullable', 'string', 'max:100'],
            'genre_ids' => ['nullable', 'array'],
            'genre_ids.*' => ['integer', 'distinct', 'exists:genres,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'required' => 'Vui lòng nhập :attribute.',
            'max' => ':Attribute không được vượt quá :max ký tự.',
            'exists' => ':Attribute không hợp lệ.',
        ], [
            'title' => 'tiêu đề',
            'author_name' => 'tên tác giả',
            'genre_ids' => 'thể loại',
            'genre_ids.*.integer' => 'Thể loại không hợp lệ.',
            'genre_ids.*.distinct' => 'Không chọn trùng thể loại.',
            'genre_ids.*.exists' => 'Một thể loại đã chọn không tồn tại.',
            'description' => 'mô tả',
        ]);

        DB::transaction(function () use ($data, $id) {
            $authorName = trim($data['author_name'] ?? '');
            $authorId = $authorName === '' ? null : (DB::table('authors')->where('name', $authorName)->value('id')
                ?? DB::table('authors')->insertGetId(['name' => $authorName]));

            DB::table('books')->where('id', $id)->update([
                'title' => $data['title'],
                'author_id' => $authorId,
                'description' => $data['description'] ?? null,
            ]);

            DB::table('book_genre')->where('book_id', $id)->delete();
            $this->attachGenres($id, $data['genre_ids'] ?? []);
        });

        return redirect()->route('library')->with('status', 'Đã cập nhật thông tin sách.');
    }

    public function destroy(int $id)
    {
        $book = $this->ownedBook($id);

        DB::transaction(function () use ($id) {
            DB::table('books')->where('id', $id)->delete();
        });

        Storage::disk('local')->delete($book->file_path);
        if ($book->cover_path) {
            Storage::disk('local')->delete($book->cover_path);
        }

        return redirect()->route('library')->with('status', 'Đã xóa sách khỏi thư viện.');
    }

    public function create()
    {
        $used = (int) DB::table('books')->where('user_id', Auth::id())->sum('file_size');
        $limit = (int) DB::table('users')->where('id', Auth::id())->value('storage_limit');
        $genres = DB::table('genres')->orderBy('name')->get();

        return view('books.create', compact('used', 'limit', 'genres'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        return $this->storeBook($request, false);
    }

    public function createPublic()
    {
        abort_unless(in_array(Auth::user()?->role, ['admin', 'manager'], true), 403);

        $used = (int) DB::table('books')->where('user_id', Auth::id())->sum('file_size');
        $limit = (int) DB::table('users')->where('id', Auth::id())->value('storage_limit');
        $genres = DB::table('genres')->orderBy('name')->get();

        return view('admin.public-books.create', compact('used', 'limit', 'genres'));
    }

    public function storePublic(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(in_array(Auth::user()?->role, ['admin', 'manager'], true), 403);

        return $this->storeBook($request, true);
    }

    private function storeBook(Request $request, bool $isPublic): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'author_name' => ['nullable', 'string', 'max:100'],
            'genre_ids' => ['nullable', 'array'],
            'genre_ids.*' => ['integer', 'distinct', 'exists:genres,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'extensions:epub,mobi', 'max:51200'],
        ], [
            'required' => 'Vui lòng nhập :attribute.',
            'max.string' => ':Attribute không được vượt quá :max ký tự.',
            'exists' => ':Attribute không hợp lệ.',
            'extensions' => 'Chỉ nhận tệp EPUB hoặc MOBI.',
            'file.max' => 'Tệp không được vượt quá 50 MB.',
            'uploaded' => 'Tải tệp lên thất bại. Có thể tệp vượt giới hạn của máy chủ.',
        ], [
            'title' => 'tiêu đề',
            'author_name' => 'tên tác giả',
            'genre_ids' => 'thể loại',
            'genre_ids.*.integer' => 'Thể loại không hợp lệ.',
            'genre_ids.*.distinct' => 'Không chọn trùng thể loại.',
            'genre_ids.*.exists' => 'Một thể loại đã chọn không tồn tại.',
            'description' => 'mô tả',
            'file' => 'tệp sách',
        ]);

        $file = $request->file('file');
        $sourceFormat = strtolower($file->getClientOriginalExtension());

        return $this->queueBookUpload($request, $file, $data, $isPublic, $sourceFormat);
    }

    private function queueBookUpload(Request $request, UploadedFile $file, array $data, bool $isPublic, string $sourceFormat): RedirectResponse|JsonResponse
    {
        $disk = Storage::disk('local');
        $sourcePath = $disk->putFileAs(
            'tmp/book-uploads/'.Auth::id(),
            $file,
            Str::uuid().'.'.$sourceFormat
        );

        if (! is_string($sourcePath)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Không thể lưu tệp để xử lý. Vui lòng thử lại.',
                    'errors' => ['file' => ['Không thể lưu tệp để xử lý. Vui lòng thử lại.']],
                ], 422);
            }

            return back()->withErrors(['file' => 'Không thể lưu tệp để chuyển đổi. Vui lòng thử lại.'])->withInput();
        }

        try {
            $uploadId = DB::table('book_uploads')->insertGetId([
                'user_id' => Auth::id(),
                'title' => $data['title'],
                'author_name' => trim((string) ($data['author_name'] ?? '')) ?: null,
                'description' => $data['description'] ?? null,
                'genre_ids' => json_encode($data['genre_ids'] ?? [], JSON_THROW_ON_ERROR),
                'is_public' => $isPublic,
                'source_format' => $sourceFormat,
                'source_path' => $sourcePath,
                'status' => 'pending',
                'progress_percent' => 0,
                'progress_message' => 'Đang chờ xử lý…',
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($sourcePath);
            throw $exception;
        }

        $launcher = null;
        $launchExitCode = null;
        try {
            $quoteCommand = static fn (string $value): string => '"'.str_replace('"', '""', $value).'"';
            $errorLog = storage_path('logs/book-upload-'.$uploadId.'.err.log');
            if (PHP_OS_FAMILY === 'Windows') {
                $command = 'start "" /B '.$quoteCommand(PHP_BINARY).' '.$quoteCommand(base_path('artisan'))
                    .' books:process-upload '.(int) $uploadId.' > '.$quoteCommand($errorLog).' 2>&1';
                $launcher = popen($command, 'r');
                if (! is_resource($launcher)) {
                    throw new \RuntimeException('Không mở được lệnh chạy nền cho tác vụ tải sách.');
                }

                $launchExitCode = pclose($launcher);
                $launcher = null;
                if ($launchExitCode !== 0) {
                    throw new \RuntimeException('Lệnh Windows khởi chạy worker trả về mã lỗi '.$launchExitCode.'.');
                }
            } else {
                $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('artisan'))
                    .' books:process-upload '.(int) $uploadId.' > '.escapeshellarg($errorLog).' 2>&1 &';
                $launcher = Process::fromShellCommandline($command, base_path());
                $launcher->setTimeout(10);
                $launcher->run();
                $launchExitCode = $launcher->getExitCode();
                if (! $launcher->isSuccessful()) {
                    throw new \RuntimeException('Không khởi chạy được tiến trình xử lý sách: '.trim($launcher->getErrorOutput().' '.$launcher->getOutput()));
                }
            }
        } catch (Throwable $exception) {
            $disk->delete($sourcePath);
            DB::table('book_uploads')->where('id', $uploadId)->update([
                'source_path' => null,
                'status' => 'failed',
                'progress_message' => 'Không khởi chạy được tiến trình xử lý.',
                'error_message' => 'Không khởi chạy được tiến trình xử lý sách. Hãy thử lại.',
                'updated_at' => now(),
                'processed_at' => now(),
            ]);
            Log::warning('Background book upload process could not be launched.', [
                'upload_id' => $uploadId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'exit_code' => $launchExitCode,
                'output' => $launcher instanceof Process ? trim($launcher->getErrorOutput().' '.$launcher->getOutput()) : null,
                'worker_error_log' => $errorLog ?? null,
            ]);
        }

        $redirectUrl = route($isPublic ? 'admin.public-books.index' : 'library');
        $statusMessage = 'Đã nhận tệp sách. Theo dõi trạng thái xử lý bên dưới.';

        if ($request->expectsJson()) {
            session()->flash('status', $statusMessage);

            return response()->json(['redirect' => $redirectUrl], 202);
        }

        return redirect($redirectUrl)->with('status', $statusMessage);
    }

    /** @param array<int, int|string> $genreIds */
    private function attachGenres(int $bookId, array $genreIds): void
    {
        $genreRows = collect($genreIds)
            ->map(fn ($genreId): int => (int) $genreId)
            ->unique()
            ->map(fn (int $genreId): array => ['book_id' => $bookId, 'genre_id' => $genreId])
            ->all();

        if ($genreRows !== []) {
            DB::table('book_genre')->insert($genreRows);
        }
    }

    public function show(int $id): View
    {
        $book = $this->authorizeBookAccess($id);
        $fileExists = Storage::disk('local')->exists($book->file_path);
        $settings = $this->getReadingSettings(Auth::id());

        $progress = DB::table('reading_progress')
            ->where('user_id', Auth::id())
            ->where('book_id', $id)
            ->first();

        $bookmarks = DB::table('bookmarks')
            ->where('user_id', Auth::id())
            ->where('book_id', $id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'location', 'note', 'created_at']);
        $annotations = DB::table('annotations')
            ->where('user_id', Auth::id())
            ->where('book_id', $id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'location', 'quote', 'note', 'color', 'created_at']);
        $canAnnotate = $this->canAnnotateBook($book);

        return view('books.show', compact('book', 'fileExists', 'settings', 'progress', 'bookmarks', 'annotations', 'canAnnotate'));
    }

    public function storeBookmark(Request $request, int $id): JsonResponse
    {
        $this->authorizeBookAccess($id);
        $data = $request->validate([
            'location' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'required' => 'Không xác định được vị trí cần lưu.',
            'max' => ':Attribute không được vượt quá :max ký tự.',
        ], [
            'location' => 'vị trí dấu trang',
            'note' => 'ghi chú',
        ]);

        $bookmarkId = DB::table('bookmarks')->insertGetId([
            'user_id' => Auth::id(),
            'book_id' => $id,
            'location' => $data['location'],
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'bookmark' => DB::table('bookmarks')->where('id', $bookmarkId)->first(['id', 'location', 'note', 'created_at']),
        ], 201);
    }

    public function destroyBookmark(int $id, int $bookmarkId): JsonResponse
    {
        $this->authorizeBookAccess($id);
        $deleted = DB::table('bookmarks')
            ->where('id', $bookmarkId)
            ->where('user_id', Auth::id())
            ->where('book_id', $id)
            ->delete();

        abort_if($deleted === 0, 404, 'Không tìm thấy dấu trang.');

        return response()->json(['status' => 'success']);
    }

    public function storeAnnotation(Request $request, int $id): JsonResponse
    {
        $book = $this->authorizeBookAccess($id);
        abort_unless($this->canAnnotateBook($book), 403, 'Chủ sách chỉ cho phép bạn đọc.');

        $data = $request->validate([
            'location' => ['required', 'string', 'max:255'],
            'quote' => ['required', 'string', 'max:12000'],
            'note' => ['nullable', 'string', 'max:12000'],
            'color' => ['required', 'string', 'in:yellow,pink,blue,green'],
        ], [
            'location.required' => 'Không xác định được vị trí của đoạn đã chọn.',
            'quote.required' => 'Vui lòng chọn một đoạn văn trong sách.',
            'color.required' => 'Vui lòng chọn màu tô sáng.',
            'max' => ':Attribute không được vượt quá :max ký tự.',
            'color.in' => 'Màu tô sáng không hợp lệ.',
        ], [
            'location' => 'vị trí tô sáng',
            'quote' => 'đoạn trích',
            'note' => 'ghi chú',
            'color' => 'màu tô sáng',
        ]);

        $annotationId = DB::table('annotations')->insertGetId([
            'user_id' => Auth::id(),
            'book_id' => $id,
            'location' => $data['location'],
            'quote' => $data['quote'],
            'note' => $data['note'] ?? null,
            'color' => $data['color'],
        ]);

        return response()->json([
            'annotation' => DB::table('annotations')->where('id', $annotationId)->first([
                'id', 'location', 'quote', 'note', 'color', 'created_at',
            ]),
        ], 201);
    }

    public function destroyAnnotation(int $id, int $annotationId): JsonResponse
    {
        $book = $this->authorizeBookAccess($id);
        abort_unless($this->canAnnotateBook($book), 403, 'Chủ sách chỉ cho phép bạn đọc.');

        $deleted = DB::table('annotations')
            ->where('id', $annotationId)
            ->where('user_id', Auth::id())
            ->where('book_id', $id)
            ->delete();

        abort_if($deleted === 0, 404, 'Không tìm thấy ghi chú.');

        return response()->json(['status' => 'success']);
    }

    public function file(int $id): BinaryFileResponse
    {
        $book = $this->authorizeBookAccess($id);

        if (! Storage::disk('local')->exists($book->file_path)) {
            abort(404, 'Tệp sách không tồn tại trên hệ thống lưu trữ.');
        }

        $fullPath = Storage::disk('local')->path($book->file_path);

        return response()->file($fullPath, [
            'Content-Type' => 'application/epub+zip',
        ]);
    }

    public function cover(int $id): Response
    {
        $book = $this->authorizeBookAccess($id);

        if ($book->cover_path && Storage::disk('local')->exists($book->cover_path)) {
            return response()->file(Storage::disk('local')->path($book->cover_path), [
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        if (! Storage::disk('local')->exists($book->file_path)) {
            abort(404, 'Tệp sách không tồn tại trên hệ thống lưu trữ.');
        }

        $cover = $this->extractEpubCover(Storage::disk('local')->path($book->file_path));
        if ($cover === null) {
            abort(404, 'Sách chưa có ảnh bìa.');
        }

        $mimeType = match ($cover['extension']) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        };

        return response($cover['contents'], 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function saveProgress(Request $request, int $id)
    {
        $this->authorizeBookAccess($id);

        $validated = $request->validate([
            'location' => ['nullable', 'string', 'max:255'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::statement(<<<'SQL'
INSERT INTO reading_progress (user_id, book_id, location, percent)
VALUES (?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    location = VALUES(location),
    percent = GREATEST(reading_progress.percent, VALUES(percent))
SQL, [
            Auth::id(),
            $id,
            $validated['location'],
            round((float) $validated['percent'], 2),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã lưu tiến độ đọc.',
        ]);
    }

    public function recordSession(Request $request, int $id)
    {
        $this->authorizeBookAccess($id);

        $validated = $request->validate([
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date'],
        ]);

        $startedAt = Carbon::parse($validated['started_at'])->setTimezone(config('app.timezone'));
        $endedAt = ! empty($validated['ended_at'])
            ? Carbon::parse($validated['ended_at'])->setTimezone(config('app.timezone'))
            : now();

        if ($endedAt->lt($startedAt)) {
            $endedAt = $startedAt->copy();
        }

        DB::table('reading_sessions')->insert([
            'user_id' => Auth::id(),
            'book_id' => $id,
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
            'ended_at' => $endedAt->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã ghi nhận phiên đọc.',
        ]);
    }

    private function authorizeBookAccess(int $bookId): object
    {
        $userId = Auth::id();

        $book = DB::table('books')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->where('books.id', $bookId)
            ->select('books.*', 'authors.name as author_name')
            ->first();

        if (! $book) {
            abort(404, 'Không tìm thấy sách.');
        }

        $isOwner = ((int) $book->user_id === $userId);

        $canReviewSubmission = in_array(Auth::user()->role, ['admin', 'manager'], true)
            && DB::table('public_book_submissions')->where('book_id', $bookId)->where('status', 'pending')->exists();

        if (! $isOwner && ! (bool) $book->is_public && ! $canReviewSubmission) {
            $share = DB::table('book_shares')
                ->where('book_id', $bookId)
                ->where('recipient_id', $userId)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if (! $share) {
                abort(403, 'Bạn không có quyền truy cập sách này.');
            }
        }

        return $book;
    }

    private function ownedBook(int $bookId): object
    {
        $book = DB::table('books')->where('id', $bookId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $book) {
            abort(404, 'Không tìm thấy sách.');
        }

        return $book;
    }

    private function canAnnotateBook(object $book): bool
    {
        if ((int) $book->user_id === (int) Auth::id()) {
            return true;
        }

        return DB::table('book_shares')
            ->where('book_id', $book->id)
            ->where('recipient_id', Auth::id())
            ->where('permission', 'read_annotate')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function getReadingSettings(int $userId): object
    {
        $settings = DB::table('reading_settings')->where('user_id', $userId)->first();

        if (! $settings) {
            DB::table('reading_settings')->insertOrIgnore([
                'user_id' => $userId,
                'font_size' => 16,
                'font_family' => 'Serif',
                'theme' => 'light',
            ]);

            $settings = DB::table('reading_settings')->where('user_id', $userId)->first()
                ?? (object) [
                    'user_id' => $userId,
                    'font_size' => 16,
                    'font_family' => 'Serif',
                    'theme' => 'light',
                ];
        }

        return $settings;
    }

    /**
     * @return array{contents: string, extension: string}|null
     */
    private function extractEpubCover(string $path): ?array
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            return null;
        }

        try {
            $containerIndex = $archive->locateName('META-INF/container.xml');
            if ($containerIndex === false || ($archive->statIndex($containerIndex)['size'] ?? PHP_INT_MAX) > 1024 * 1024) {
                return null;
            }

            $containerXml = $archive->getFromIndex($containerIndex);
            if ($containerXml === false) {
                return null;
            }

            $container = $this->loadEpubXml($containerXml);
            if (! $container) {
                return null;
            }

            $containerXPath = new DOMXPath($container);
            $opfPath = trim((string) $containerXPath->evaluate('string(//*[local-name()="rootfile"]/@full-path)'));
            if ($opfPath === '' || str_contains($opfPath, '\\') || str_starts_with($opfPath, '/')) {
                return null;
            }

            $packageIndex = $archive->locateName($opfPath);
            if ($packageIndex === false || ($archive->statIndex($packageIndex)['size'] ?? PHP_INT_MAX) > 2 * 1024 * 1024) {
                return null;
            }

            $packageXml = $archive->getFromIndex($packageIndex);
            if ($packageXml === false) {
                return null;
            }

            $package = $this->loadEpubXml($packageXml);
            if (! $package) {
                return null;
            }

            $packageXPath = new DOMXPath($package);
            $coverId = trim((string) $packageXPath->evaluate('string(//*[local-name()="metadata"]/*[local-name()="meta"][@name="cover"]/@content)'));
            $coverItem = null;
            foreach ($packageXPath->query('//*[local-name()="manifest"]/*[local-name()="item"]') ?: [] as $item) {
                $properties = preg_split('/\s+/', trim($item->attributes->getNamedItem('properties')?->nodeValue ?? '')) ?: [];
                $isEpub3Cover = in_array('cover-image', $properties, true);
                $isEpub2Cover = $coverId !== '' && $item->attributes->getNamedItem('id')?->nodeValue === $coverId;
                if ($isEpub3Cover || $isEpub2Cover) {
                    $coverItem = $item;
                    break;
                }
            }

            $href = $coverItem?->attributes->getNamedItem('href')?->nodeValue;
            if (! is_string($href) || $href === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $href)) {
                return null;
            }

            $imagePath = $this->resolveEpubPath($opfPath, $href);
            if ($imagePath === null) {
                return null;
            }

            $imageIndex = $archive->locateName($imagePath);
            if ($imageIndex === false || ($archive->statIndex($imageIndex)['size'] ?? PHP_INT_MAX) > 5 * 1024 * 1024) {
                return null;
            }

            $contents = $archive->getFromIndex($imageIndex);
            if (! is_string($contents)) {
                return null;
            }

            $image = @getimagesizefromstring($contents);
            if (! is_array($image) || $image[0] > 6000 || $image[1] > 6000) {
                return null;
            }

            $extension = match ($image[2] ?? null) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_GIF => 'gif',
                IMAGETYPE_WEBP => 'webp',
                default => null,
            };

            return $extension === null ? null : ['contents' => $contents, 'extension' => $extension];
        } finally {
            $archive->close();
        }
    }

    private function loadEpubXml(string $contents): ?DOMDocument
    {
        $document = new DOMDocument;
        $previousSetting = libxml_use_internal_errors(true);

        try {
            return $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS) ? $document : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }
    }

    private function resolveEpubPath(string $opfPath, string $href): ?string
    {
        $href = rawurldecode(explode('#', explode('?', $href, 2)[0], 2)[0]);
        if ($href === '' || str_contains($href, '\\') || str_starts_with($href, '/')) {
            return null;
        }

        $parts = [];
        foreach (array_merge(explode('/', dirname($opfPath)), explode('/', $href)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    return null;
                }
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return $parts === [] ? null : implode('/', $parts);
    }
}
