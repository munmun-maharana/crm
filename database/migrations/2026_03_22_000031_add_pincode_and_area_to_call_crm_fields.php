<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('crm_fields')->where('pipeline', 'call_crm')->where('key', 'pincode')->exists()) {
            DB::table('crm_fields')->insert([
                'pipeline' => 'call_crm',
                'key' => 'pincode',
                'maps_from' => null,
                'label' => 'Pincode',
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
                'sort_order' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('crm_fields')->where('pipeline', 'call_crm')->where('key', 'area')->exists()) {
            DB::table('crm_fields')->insert([
                'pipeline' => 'call_crm',
                'key' => 'area',
                'maps_from' => null,
                'label' => 'Area',
                'type' => 'text',
                'options' => null,
                'required' => false,
                'active' => true,
                'in_form' => true,
                'in_table' => true,
                'in_excel' => true,
                'in_update' => false,
                'auto_select' => false,
                'is_system' => false,
                'sort_order' => 7,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('crm_fields')->where('pipeline', 'call_crm')->whereIn('key', ['pincode', 'area'])->delete();
    }
};

