<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow draft activation cards before devices are selected.
        DB::statement('ALTER TABLE activation_requests MODIFY inventory_type_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::table('activation_requests')->whereNull('inventory_type_id')->delete();
        DB::statement('ALTER TABLE activation_requests MODIFY inventory_type_id BIGINT UNSIGNED NOT NULL');
    }
};
