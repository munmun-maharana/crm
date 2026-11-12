<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('crm_fields')->where('pipeline', 'call_crm')->exists()) {
            $now = now();
            $defaults = [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_table' => true, 'in_excel' => true, 'in_update' => false, 'is_system' => true, 'sort_order' => 1],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'required' => false, 'in_table' => true, 'in_excel' => true, 'in_update' => false, 'is_system' => false, 'sort_order' => 2],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'in_table' => true, 'in_excel' => true, 'in_update' => false, 'is_system' => false, 'sort_order' => 3],
                ['key' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false, 'in_table' => false, 'in_excel' => true, 'in_update' => false, 'is_system' => false, 'sort_order' => 4],
                ['key' => 'city', 'label' => 'City', 'type' => 'text', 'required' => false, 'in_table' => true, 'in_excel' => true, 'in_update' => false, 'is_system' => false, 'sort_order' => 5],
                ['key' => 'source', 'label' => 'Source', 'type' => 'text', 'required' => false, 'in_table' => true, 'in_excel' => true, 'in_update' => false, 'is_system' => false, 'sort_order' => 6],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'in_table' => true, 'in_excel' => true, 'in_update' => true, 'is_system' => true, 'sort_order' => 7, 'options' => json_encode(['new', 'interested', 'contacted', 'converted', 'lost'])],
                ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'in_table' => false, 'in_excel' => true, 'in_update' => false, 'is_system' => false, 'sort_order' => 8],
            ];

            foreach ($defaults as $field) {
                DB::table('crm_fields')->insert([
                    'pipeline' => 'call_crm',
                    'key' => $field['key'],
                    'maps_from' => null,
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'] ?? null,
                    'required' => $field['required'],
                    'active' => true,
                    'in_form' => true,
                    'in_table' => $field['in_table'],
                    'in_excel' => $field['in_excel'],
                    'in_update' => $field['in_update'],
                    'auto_select' => false,
                    'is_system' => $field['is_system'],
                    'sort_order' => $field['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('crm_fields')->where('pipeline', 'call_crm')->delete();
    }
};

