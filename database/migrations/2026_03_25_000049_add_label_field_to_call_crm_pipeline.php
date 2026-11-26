<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('crm_fields')
            ->where('pipeline', 'call_crm')
            ->where('key', 'label')
            ->exists();

        if (! $exists) {
            DB::table('crm_fields')->insert([
                'pipeline' => 'call_crm',
                'key' => 'label',
                'maps_from' => 'label',
                'label' => 'Label',
                'type' => 'select',
                'options' => json_encode(['Open', 'Engaged']),
                'required' => false,
                'active' => true,
                'in_form' => false,
                'in_table' => true,
                'in_excel' => true,
                'in_update' => false,
                'auto_select' => false,
                'is_system' => false,
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('crm_fields')
            ->where('pipeline', 'call_crm')
            ->where('key', 'label')
            ->delete();
    }
};
