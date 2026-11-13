<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('mobi_conversions', 'book_uploads');
        DB::statement('ALTER TABLE book_uploads DROP CHECK ck_mobi_conversions_status');

        Schema::table('book_uploads', function (Blueprint $table): void {
            $table->string('source_format', 10)->default('mobi')->after('is_public');
            $table->dropForeign('mobi_conversions_user_id_foreign');
            $table->dropForeign('mobi_conversions_book_id_foreign');
            $table->dropIndex('mobi_conversions_user_id_status_index');
            $table->foreign('user_id', 'book_uploads_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('book_id', 'book_uploads_book_id_foreign')->references('id')->on('books')->nullOnDelete();
            $table->index(['user_id', 'status'], 'book_uploads_user_id_status_index');
        });

        DB::statement("ALTER TABLE book_uploads ADD CONSTRAINT ck_book_uploads_status CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
        DB::statement("ALTER TABLE book_uploads ADD CONSTRAINT ck_book_uploads_source_format CHECK (source_format IN ('epub', 'mobi'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE book_uploads DROP CHECK ck_book_uploads_source_format');
        DB::statement('ALTER TABLE book_uploads DROP CHECK ck_book_uploads_status');

        Schema::table('book_uploads', function (Blueprint $table): void {
            $table->dropForeign('book_uploads_user_id_foreign');
            $table->dropForeign('book_uploads_book_id_foreign');
            $table->dropIndex('book_uploads_user_id_status_index');
            $table->dropColumn('source_format');
        });

        Schema::rename('book_uploads', 'mobi_conversions');

        Schema::table('mobi_conversions', function (Blueprint $table): void {
            $table->foreign('user_id', 'mobi_conversions_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('book_id', 'mobi_conversions_book_id_foreign')->references('id')->on('books')->nullOnDelete();
            $table->index(['user_id', 'status'], 'mobi_conversions_user_id_status_index');
        });

        DB::statement("ALTER TABLE mobi_conversions ADD CONSTRAINT ck_mobi_conversions_status CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
    }
};
