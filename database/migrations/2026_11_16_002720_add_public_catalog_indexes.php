<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->index(['is_public', 'created_at', 'id'], 'books_public_created_id_index');
        });

        Schema::table('authors', function (Blueprint $table): void {
            $table->index('name', 'authors_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table): void {
            $table->dropIndex('authors_name_index');
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->dropIndex('books_public_created_id_index');
        });
    }
};
