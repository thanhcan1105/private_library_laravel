<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('book_uploads', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->string('progress_message', 150)->nullable()->after('progress_percent');
        });

        DB::statement('ALTER TABLE book_uploads ADD CONSTRAINT ck_book_uploads_progress CHECK (progress_percent BETWEEN 0 AND 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE book_uploads DROP CHECK ck_book_uploads_progress');

        Schema::table('book_uploads', function (Blueprint $table) {
            $table->dropColumn(['progress_percent', 'progress_message']);
        });
    }
};
