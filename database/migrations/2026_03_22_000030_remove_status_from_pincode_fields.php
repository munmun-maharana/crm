<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_fields')
            ->where('pipeline', 'pincodes')
            ->where('key', 'status')
            ->delete();
    }

    public function down(): void
    {
        // No-op
    }
};

