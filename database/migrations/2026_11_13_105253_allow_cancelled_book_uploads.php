<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE book_uploads DROP CHECK ck_book_uploads_status');
        DB::statement("ALTER TABLE book_uploads ADD CONSTRAINT ck_book_uploads_status CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE book_uploads SET status = 'failed', error_message = 'Tác vụ đã bị hủy.', progress_message = 'Tác vụ đã bị hủy.' WHERE status = 'cancelled'");
        DB::statement('ALTER TABLE book_uploads DROP CHECK ck_book_uploads_status');
        DB::statement("ALTER TABLE book_uploads ADD CONSTRAINT ck_book_uploads_status CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
    }
};
