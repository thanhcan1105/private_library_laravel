<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BookUploadRecoveryService
{
    public function failStaleUploadsFor(int $userId): void
    {
        $cutoff = now()->subMinutes(20);
        $staleUploads = DB::table('book_uploads')
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'processing'])
            ->whereRaw('COALESCE(updated_at, created_at) <= ?', [$cutoff])
            ->orderBy('id')
            ->limit(20)
            ->get(['id']);

        foreach ($staleUploads as $staleUpload) {
            $recovery = DB::transaction(function () use ($staleUpload, $cutoff): ?array {
                $upload = DB::table('book_uploads')
                    ->where('id', $staleUpload->id)
                    ->lockForUpdate()
                    ->first(['id', 'user_id', 'status', 'source_path', 'updated_at', 'created_at']);

                if (! $upload
                    || ! in_array($upload->status, ['pending', 'processing'], true)
                    || Carbon::parse($upload->updated_at ?? $upload->created_at)->greaterThan($cutoff)) {
                    return null;
                }

                DB::table('book_uploads')->where('id', $upload->id)->update([
                    'status' => 'failed',
                    'progress_message' => 'Tác vụ xử lý quá thời gian chờ. Vui lòng tải tệp lên lại.',
                    'error_message' => 'Tác vụ xử lý quá thời gian chờ. Vui lòng tải tệp lên lại.',
                    'updated_at' => now(),
                    'processed_at' => now(),
                ]);

                return [
                    'source_path' => $upload->source_path,
                    'user_id' => (int) $upload->user_id,
                ];
            });

            if ($recovery === null) {
                continue;
            }

            $disk = Storage::disk('local');
            $sourcePath = $recovery['source_path'];
            if ($sourcePath) {
                try {
                    $deleted = $disk->delete($sourcePath) || ! $disk->exists($sourcePath);
                    if ($deleted) {
                        DB::table('book_uploads')
                            ->where('id', $staleUpload->id)
                            ->where('status', 'failed')
                            ->update(['source_path' => null]);
                    }
                } catch (\Throwable $exception) {
                    Log::warning('A stale book upload source file could not be removed.', [
                        'upload_id' => $staleUpload->id,
                        'exception' => $exception::class,
                    ]);
                }
            }

            try {
                $disk->delete('books/'.$recovery['user_id'].'/upload-'.$staleUpload->id.'.epub');
                $disk->deleteDirectory('tmp/conversions/'.$staleUpload->id);
            } catch (\Throwable $exception) {
                Log::warning('A stale book upload conversion directory could not be removed.', [
                    'upload_id' => $staleUpload->id,
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
