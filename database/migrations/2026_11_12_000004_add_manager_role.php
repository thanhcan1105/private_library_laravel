<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CHECK ck_users_role');
        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('user','manager','admin'))");
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'manager')->update(['role' => 'user']);
        DB::statement('ALTER TABLE users DROP CHECK ck_users_role');
        DB::statement("ALTER TABLE users ADD CONSTRAINT ck_users_role CHECK (role IN ('user','admin'))");
    }
};
