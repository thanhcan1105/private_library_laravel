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
        Schema::create('book_genre', function (Blueprint $table): void {
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained('genres');
            $table->primary(['book_id', 'genre_id']);
            $table->index('genre_id');
        });

        DB::table('book_genre')->insertUsing(
            ['book_id', 'genre_id'],
            DB::table('books')->whereNotNull('genre_id')->select('id', 'genre_id')
        );

        Schema::table('books', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('genre_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasMultipleGenres = DB::table('book_genre')
            ->select('book_id')
            ->groupBy('book_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleGenres) {
            throw new RuntimeException('Cannot roll back while a book has multiple genres.');
        }

        Schema::table('books', function (Blueprint $table): void {
            $table->foreignId('genre_id')->nullable()->constrained('genres');
        });

        DB::statement('UPDATE books b JOIN (SELECT book_id, MIN(genre_id) AS genre_id FROM book_genre GROUP BY book_id) bg ON bg.book_id = b.id SET b.genre_id = bg.genre_id');
        Schema::dropIfExists('book_genre');
    }
};
