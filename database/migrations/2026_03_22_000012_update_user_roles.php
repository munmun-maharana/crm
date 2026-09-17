<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'agent')->update(['role' => 'front_office']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'front_office')->update(['role' => 'agent']);
    }
};
