<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('file_size');
        });
        DB::statement('ALTER TABLE books ADD CONSTRAINT ck_books_is_public CHECK (is_public IN (0, 1))');

        Schema::create('saved_books', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->dateTime('saved_at')->useCurrent();
            $table->primary(['user_id', 'book_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('saved_books');
        DB::statement('ALTER TABLE books DROP CHECK ck_books_is_public');
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
