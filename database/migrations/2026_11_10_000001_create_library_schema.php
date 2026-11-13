<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->string('full_name', 100);
            $table->string('role', 20)->default('user');
            $table->unsignedBigInteger('storage_limit')->default(1073741824);
            $table->tinyInteger('status')->default(1);
            $table->dateTime('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('user','admin'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_storage_limit CHECK (storage_limit > 0)");

        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('bio')->nullable();
        });

        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('author_id')->nullable()->constrained('authors');
            $table->foreignId('genre_id')->nullable()->constrained('genres');
            $table->string('title', 200)->index();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('file_path');
            $table->string('format', 10)->default('epub');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('page_count')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE books ADD CONSTRAINT ck_books_file_size CHECK (file_size > 0)");
        DB::statement("ALTER TABLE books ADD CONSTRAINT ck_books_format CHECK (format = 'epub')");

        Schema::create('shelves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description')->nullable();
        });

        Schema::create('book_shelf', function (Blueprint $table) {
            $table->foreignId('shelf_id')->constrained('shelves')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->dateTime('added_at')->useCurrent();
            $table->primary(['shelf_id', 'book_id']);
        });

        Schema::create('reading_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->integer('font_size')->default(16);
            $table->string('font_family', 50)->default('Serif');
            $table->string('theme', 10)->default('light');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE reading_settings ADD CONSTRAINT ck_reading_settings_font_size CHECK (font_size BETWEEN 8 AND 48)");
        DB::statement("ALTER TABLE reading_settings ADD CONSTRAINT ck_reading_settings_theme CHECK (theme IN ('light','dark'))");

        Schema::create('reading_progress', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->string('location')->nullable();
            $table->decimal('percent', 5, 2)->default(0);
            $table->dateTime('last_read_at')->nullable();
            $table->primary(['user_id', 'book_id']);
        });
        DB::statement("ALTER TABLE reading_progress ADD CONSTRAINT ck_reading_progress_percent CHECK (percent BETWEEN 0 AND 100)");

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->string('location');
            $table->string('note')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->string('location');
            $table->text('quote');
            $table->text('note')->nullable();
            $table->string('color', 20)->default('yellow');
            $table->dateTime('created_at')->useCurrent();
        });

        Schema::create('reading_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
        });
        DB::statement("ALTER TABLE reading_sessions ADD CONSTRAINT ck_reading_sessions_time CHECK (ended_at IS NULL OR ended_at >= started_at)");

        Schema::create('book_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('recipient_id')->constrained('users');
            $table->string('permission', 20)->default('read');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->unique(['book_id', 'recipient_id']);
        });
        DB::statement("ALTER TABLE book_shares ADD CONSTRAINT ck_book_shares_different CHECK (owner_id <> recipient_id)");
        DB::statement("ALTER TABLE book_shares ADD CONSTRAINT ck_book_shares_permission CHECK (permission IN ('read','read_annotate'))");
        DB::statement("ALTER TABLE book_shares ADD CONSTRAINT ck_book_shares_expiry CHECK (expires_at IS NULL OR expires_at > created_at)");
    }

    public function down(): void
    {
        Schema::dropIfExists('book_shares');
        Schema::dropIfExists('reading_sessions');
        Schema::dropIfExists('annotations');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('reading_progress');
        Schema::dropIfExists('reading_settings');
        Schema::dropIfExists('book_shelf');
        Schema::dropIfExists('shelves');
        Schema::dropIfExists('books');
        Schema::dropIfExists('genres');
        Schema::dropIfExists('authors');
        Schema::dropIfExists('users');
    }
};