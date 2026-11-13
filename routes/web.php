<?php

use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GenreController;
use App\Http\Controllers\Admin\PublicBookController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookShareController;
use App\Http\Controllers\NewPasswordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetLinkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\ReadingSettingsController;
use App\Http\Controllers\ReadingStatisticsController;
use App\Http\Controllers\ShelfController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['web', 'auth']]);
require base_path('routes/channels.php');

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->route(in_array(Auth::user()->role, ['admin', 'manager'], true) ? 'admin.dashboard' : 'library');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/statistics', [ReadingStatisticsController::class, 'index'])->name('statistics.index');

    Route::get('/library', [BookController::class, 'index'])->name('library');
    Route::get('/catalog', [PublicCatalogController::class, 'index'])->name('catalog.index');
    Route::post('/catalog/{book}/save', [PublicCatalogController::class, 'save'])->whereNumber('book')->name('catalog.saved.store');
    Route::delete('/catalog/{book}/save', [PublicCatalogController::class, 'remove'])->whereNumber('book')->name('catalog.saved.destroy');
    Route::get('/shared', [BookShareController::class, 'sharedWithMe'])->name('books.shared');
    Route::get('/books/{book}/shares', [BookShareController::class, 'manage'])->whereNumber('book')->name('books.shares.manage');
    Route::post('/books/{book}/shares', [BookShareController::class, 'store'])->whereNumber('book')->name('books.shares.store');
    Route::delete('/books/{book}/shares/{share}', [BookShareController::class, 'destroy'])
        ->whereNumber('book')->whereNumber('share')->name('books.shares.destroy');
    Route::get('/shelves', [ShelfController::class, 'index'])->name('shelves.index');
    Route::post('/shelves', [ShelfController::class, 'store'])->name('shelves.store');
    Route::get('/shelves/{shelf}', [ShelfController::class, 'show'])->whereNumber('shelf')->name('shelves.show');
    Route::put('/shelves/{shelf}', [ShelfController::class, 'update'])->whereNumber('shelf')->name('shelves.update');
    Route::delete('/shelves/{shelf}', [ShelfController::class, 'destroy'])->whereNumber('shelf')->name('shelves.destroy');
    Route::post('/shelves/{shelf}/books', [ShelfController::class, 'addBook'])->whereNumber('shelf')->name('shelves.books.store');
    Route::delete('/shelves/{shelf}/books/{book}', [ShelfController::class, 'removeBook'])
        ->whereNumber('shelf')->whereNumber('book')->name('shelves.books.destroy');
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/book-uploads/status', [BookController::class, 'bookUploadStatuses'])->name('book-uploads.status');
    Route::delete('/book-uploads/{upload}', [BookController::class, 'dismissBookUpload'])->whereNumber('upload')->name('book-uploads.destroy');
    Route::post('/book-uploads/{upload}/cancel', [BookController::class, 'cancelBookUpload'])->whereNumber('upload')->name('book-uploads.cancel');
    Route::post('/books/{book}/public-submission', [BookController::class, 'submitForPublicReview'])->whereNumber('book')->name('books.public-submission.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit')->whereNumber('book');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update')->whereNumber('book');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy')->whereNumber('book');

    Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show')->whereNumber('book');
    Route::get('/books/{book}/cover', [BookController::class, 'cover'])->name('books.cover')->whereNumber('book');
    Route::get('/books/{book}/file.epub', [BookController::class, 'file'])->name('books.file')->whereNumber('book');
    Route::post('/books/{book}/bookmarks', [BookController::class, 'storeBookmark'])->name('books.bookmarks.store')->whereNumber('book');
    Route::delete('/books/{book}/bookmarks/{bookmark}', [BookController::class, 'destroyBookmark'])
        ->whereNumber('book')->whereNumber('bookmark')->name('books.bookmarks.destroy');
    Route::post('/books/{book}/annotations', [BookController::class, 'storeAnnotation'])->name('books.annotations.store')->whereNumber('book');
    Route::delete('/books/{book}/annotations/{annotation}', [BookController::class, 'destroyAnnotation'])
        ->whereNumber('book')->whereNumber('annotation')->name('books.annotations.destroy');
    Route::match(['PUT', 'POST'], '/books/{book}/progress', [BookController::class, 'saveProgress'])->name('books.progress')->whereNumber('book');
    Route::post('/books/{book}/sessions', [BookController::class, 'recordSession'])->name('books.sessions')->whereNumber('book');

    Route::match(['PUT', 'POST'], '/reading-settings', [ReadingSettingsController::class, 'update'])->name('reading-settings.update');
});

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/notifications', [NotificationController::class, 'indexAdmin'])->name('notifications.index');
        Route::get('/notifications/poll', [NotificationController::class, 'pollAdmin'])->name('notifications.poll');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsReadAdmin'])->name('notifications.read');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroyAdmin'])->name('notifications.destroy');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsReadAdmin'])->name('notifications.read-all');
        Route::put('/users/{user}/storage-limit', [UserController::class, 'updateStorageLimit'])
            ->whereNumber('user')->name('users.storage-limit');
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])
            ->whereNumber('user')->name('users.status');
        Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
            ->whereNumber('user')->name('users.role');

        Route::get('/public-books', [PublicBookController::class, 'index'])->name('public-books.index');
        Route::get('/public-submissions', [PublicBookController::class, 'submissions'])->name('public-submissions.index');
        Route::get('/public-submissions/poll', [PublicBookController::class, 'pollSubmissions'])->name('public-submissions.poll');
        Route::get('/public-submissions/{book}/card', [PublicBookController::class, 'submissionCard'])->whereNumber('book')->name('public-submissions.card');
        Route::post('/public-submissions/{book}/approve', [PublicBookController::class, 'approveSubmission'])->whereNumber('book')->name('public-submissions.approve');
        Route::post('/public-submissions/{book}/reject', [PublicBookController::class, 'rejectSubmission'])->whereNumber('book')->name('public-submissions.reject');
        Route::get('/public-books/create', [BookController::class, 'createPublic'])->name('public-books.create');
        Route::post('/public-books', [BookController::class, 'storePublic'])->name('public-books.store');
        Route::patch('/public-books/{book}/unpublish', [PublicBookController::class, 'unpublish'])
            ->whereNumber('book')->name('public-books.unpublish');

        Route::get('/genres', [GenreController::class, 'index'])->name('genres.index');
        Route::post('/genres', [GenreController::class, 'store'])->name('genres.store');
        Route::put('/genres/{genre}', [GenreController::class, 'update'])->whereNumber('genre')->name('genres.update');
        Route::delete('/genres/{genre}', [GenreController::class, 'destroy'])->whereNumber('genre')->name('genres.destroy');

        Route::get('/authors', [AuthorController::class, 'index'])->name('authors.index');
        Route::post('/authors', [AuthorController::class, 'store'])->name('authors.store');
        Route::put('/authors/{author}', [AuthorController::class, 'update'])->whereNumber('author')->name('authors.update');
        Route::delete('/authors/{author}', [AuthorController::class, 'destroy'])->whereNumber('author')->name('authors.destroy');

        Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');
    });
