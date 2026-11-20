<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('crm_fields')
            ->where('pipeline', 'booking')
            ->where('key', 'mrp')
            ->exists();

        if (! $exists) {
            DB::table('crm_fields')->insert([
                'pipeline' => 'booking',
                'key' => 'mrp',
                'maps_from' => 'mrp',
                'label' => 'MRP (₹)',
                'type' => 'number',
                'options' => null,
                'required' => false,
                'active' => true,
                'in_form' => true,
                'in_table' => true,
                'in_excel' => true,
                'in_update' => false,
                'auto_select' => false,
                'is_system' => false,
                'sort_order' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('crm_fields')
            ->where('pipeline', 'booking')
            ->where('key', 'mrp')
            ->delete();
    }
};
