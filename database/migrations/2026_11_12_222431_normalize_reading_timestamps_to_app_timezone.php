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
        DB::table('reading_sessions')->update([
            'started_at' => DB::raw('DATE_ADD(started_at, INTERVAL 7 HOUR)'),
            'ended_at' => DB::raw('IF(ended_at IS NULL, NULL, DATE_ADD(ended_at, INTERVAL 7 HOUR))'),
        ]);

        DB::table('reading_progress')->whereNotNull('last_read_at')->update([
            'last_read_at' => DB::raw('DATE_ADD(last_read_at, INTERVAL 7 HOUR)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('reading_sessions')->update([
            'started_at' => DB::raw('DATE_SUB(started_at, INTERVAL 7 HOUR)'),
            'ended_at' => DB::raw('IF(ended_at IS NULL, NULL, DATE_SUB(ended_at, INTERVAL 7 HOUR))'),
        ]);

        DB::table('reading_progress')->whereNotNull('last_read_at')->update([
            'last_read_at' => DB::raw('DATE_SUB(last_read_at, INTERVAL 7 HOUR)'),
        ]);
    }
};
