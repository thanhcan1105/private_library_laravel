<?php

namespace App\Console\Commands;

use App\Services\MobiConversionService;
use DomainException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('books:process-upload {uploadId}')]
#[Description('Validate an uploaded book and process it in the background')]
class ProcessBookUploadCommand extends Command
{
    public function handle(MobiConversionService $mobiConversionService): int
    {
        $uploadId = (int) $this->argument('uploadId');
        $upload = DB::transaction(function () use ($uploadId): ?object {
            $upload = DB::table('book_uploads')->where('id', $uploadId)->lockForUpdate()->first();
            if (! $upload || $upload->status !== 'pending') {
                return null;
            }

            DB::table('book_uploads')->where('id', $uploadId)->update([
                'status' => 'processing',
                'progress_percent' => 5,
                'progress_message' => 'Đang chuẩn bị kiểm tra tệp…',
                'updated_at' => now(),
            ]);

            return $upload;
        });

        if (! $upload) {
            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        $epubPath = null;
        $bookPath = null;

        try {
            $this->updateProgress($uploadId, 12, 'Đang kiểm tra tệp đã tải lên…');
            if (! $upload->source_path || ! $disk->exists($upload->source_path)) {
                throw new RuntimeException('Uploaded book file is missing.');
            }

            $sourcePath = $disk->path($upload->source_path);
            if ($upload->source_format === 'mobi') {
                $this->updateProgress($uploadId, 20, 'Đang chuyển MOBI sang EPUB…');
                $epubPath = $mobiConversionService->convert($sourcePath, $uploadId);
                if ($epubPath === null) {
                    throw new RuntimeException('MOBI conversion failed.');
                }
                $this->updateProgress($uploadId, 68, 'Đã chuyển đổi, đang kiểm tra EPUB…');
            } elseif ($upload->source_format === 'epub') {
                $this->updateProgress($uploadId, 35, 'Đang kiểm tra cấu trúc EPUB…');
                if ($mobiConversionService->isValidEpub($sourcePath)) {
                    $epubPath = $sourcePath;
                } else {
                    throw new RuntimeException('EPUB validation failed.');
                }
            } else {
                throw new RuntimeException('EPUB validation failed.');
            }

            if ($this->finishCancelledUpload($uploadId, $upload->source_path, $bookPath, $disk)) {
                return self::SUCCESS;
            }

            $this->updateProgress($uploadId, 76, 'Tệp hợp lệ, đang lưu sách…');
            $fileSize = filesize($epubPath);
            if (! is_int($fileSize) || $fileSize < 1 || $fileSize > 50 * 1024 * 1024) {
                throw new RuntimeException('Processed EPUB size is invalid.');
            }

            $bookFileName = 'upload-'.$uploadId.'.epub';
            if ($upload->source_format === 'epub') {
                $bookPath = 'books/'.$upload->user_id.'/'.$bookFileName;
                $disk->makeDirectory('books/'.$upload->user_id);
                if (! $disk->move($upload->source_path, $bookPath)) {
                    throw new RuntimeException('Uploaded EPUB could not be moved into the library.');
                }
            } else {
                $bookPath = $disk->putFileAs('books/'.$upload->user_id, new File($epubPath), $bookFileName);
                if (! is_string($bookPath)) {
                    throw new RuntimeException('Converted EPUB could not be stored.');
                }
            }

            $this->updateProgress($uploadId, 90, 'Đang thêm sách vào thư viện…');
            $bookId = DB::transaction(function () use ($upload, $uploadId, $bookPath, $fileSize): ?int {
                $currentUpload = DB::table('book_uploads')->where('id', $uploadId)->lockForUpdate()->first(['status']);
                if (! $currentUpload || $currentUpload->status !== 'processing') {
                    return null;
                }

                $owner = DB::table('users')
                    ->where('id', $upload->user_id)
                    ->lockForUpdate()
                    ->first(['storage_limit']);
                if (! $owner) {
                    throw new RuntimeException('The book owner no longer exists.');
                }

                $usedStorage = (int) DB::table('books')->where('user_id', $upload->user_id)->sum('file_size');
                if ($usedStorage + $fileSize > (int) $owner->storage_limit) {
                    throw new DomainException('Storage limit exceeded.');
                }

                $authorId = null;
                $authorName = trim((string) $upload->author_name);
                if ($authorName !== '') {
                    $authorId = DB::table('authors')->where('name', $authorName)->value('id')
                        ?? DB::table('authors')->insertGetId(['name' => $authorName]);
                }

                $genreIds = json_decode((string) $upload->genre_ids, true) ?: [];
                $bookId = DB::table('books')->insertGetId([
                    'user_id' => $upload->user_id,
                    'author_id' => $authorId,
                    'title' => $upload->title,
                    'description' => $upload->description,
                    'cover_path' => null,
                    'file_path' => $bookPath,
                    'format' => 'epub',
                    'file_size' => $fileSize,
                    'is_public' => $upload->is_public,
                ]);

                $genreRows = collect($genreIds)->map(fn ($genreId): array => [
                    'book_id' => $bookId,
                    'genre_id' => (int) $genreId,
                ])->all();
                if ($genreRows !== []) {
                    DB::table('book_genre')->insert($genreRows);
                }

                DB::table('book_uploads')->where('id', $uploadId)->update([
                    'book_id' => $bookId,
                    'source_path' => null,
                    'status' => 'completed',
                    'progress_percent' => 100,
                    'progress_message' => 'Hoàn tất.',
                    'error_message' => null,
                    'updated_at' => now(),
                    'processed_at' => now(),
                ]);

                return (int) $bookId;
            });

            if ($bookId === null) {
                if ($upload->source_path) {
                    $disk->delete($upload->source_path);
                }
                if ($bookPath !== null) {
                    $disk->delete($bookPath);
                }
                $this->finishCancelledUpload($uploadId, $upload->source_path, $bookPath, $disk);

                return self::SUCCESS;
            }

            if ($upload->source_path && $upload->source_format === 'mobi') {
                $disk->delete($upload->source_path);
            }

            $this->info('Book upload completed.');
        } catch (Throwable $exception) {
            if ($this->finishCancelledUpload($uploadId, $upload->source_path, $bookPath, $disk)) {
                return self::SUCCESS;
            }

            if ($bookPath !== null) {
                $disk->delete($bookPath);
            }

            $quotaExceeded = $exception instanceof DomainException
                || ($exception instanceof QueryException && (int) ($exception->errorInfo[1] ?? 0) === 1644);
            $errorMessage = $quotaExceeded
                ? 'Dung lượng lưu trữ của tài khoản không đủ cho sách này.'
                : ($upload->source_format === 'epub'
                    ? 'Tệp EPUB không hợp lệ hoặc không thể hoàn tất xử lý.'
                    : 'Không chuyển được MOBI sang EPUB. Hãy kiểm tra tệp rồi thử tải lại.');

            DB::table('book_uploads')->where('id', $uploadId)->where('status', 'processing')->update([
                'source_path' => null,
                'status' => 'failed',
                'progress_message' => $errorMessage,
                'error_message' => $errorMessage,
                'updated_at' => now(),
                'processed_at' => now(),
            ]);

            if ($upload->source_path) {
                $disk->delete($upload->source_path);
            }

            Log::warning('Background book upload failed.', [
                'upload_id' => $uploadId,
                'format' => $upload->source_format,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->error($errorMessage);
        } finally {
            if ($upload->source_format === 'mobi') {
                if ($epubPath !== null && is_file($epubPath)) {
                    @unlink($epubPath);
                }
                $disk->deleteDirectory('tmp/conversions/'.$uploadId);
            }
        }

        return self::SUCCESS;
    }

    private function updateProgress(int $uploadId, int $percent, string $message): void
    {
        DB::table('book_uploads')->where('id', $uploadId)->where('status', 'processing')->update([
            'progress_percent' => $percent,
            'progress_message' => $message,
            'updated_at' => now(),
        ]);
    }

    private function finishCancelledUpload(int $uploadId, ?string $sourcePath, ?string $bookPath, Filesystem $disk): bool
    {
        $cancelled = DB::table('book_uploads')->where('id', $uploadId)->where('status', 'cancelled')->exists();
        if (! $cancelled) {
            return false;
        }

        if ($sourcePath) {
            $disk->delete($sourcePath);
        }
        if ($bookPath) {
            $disk->delete($bookPath);
        }

        DB::table('book_uploads')->where('id', $uploadId)->where('status', 'cancelled')->update([
            'source_path' => null,
            'updated_at' => now(),
        ]);

        return true;
    }
}
