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
        Schema::create('mobi_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_id')->nullable()->constrained('books')->nullOnDelete();
            $table->string('title', 200);
            $table->string('author_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->json('genre_ids')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('source_path', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('error_message', 500)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->index(['user_id', 'status']);
        });
        DB::statement("ALTER TABLE mobi_conversions ADD CONSTRAINT ck_mobi_conversions_status CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE mobi_conversions DROP CHECK ck_mobi_conversions_status');
        Schema::dropIfExists('mobi_conversions');
    }
};
