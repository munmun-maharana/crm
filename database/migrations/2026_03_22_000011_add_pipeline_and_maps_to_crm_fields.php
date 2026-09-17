<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_fields', function (Blueprint $table) {
            $table->string('pipeline')->default('crm')->after('id');
            $table->string('maps_from')->nullable()->after('key');
        });

        Schema::table('crm_fields', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['pipeline', 'key']);
            $table->index('pipeline');
        });

        if (
            DB::table('crm_fields')->where('pipeline', 'crm')->exists()
            && ! DB::table('crm_fields')->where('pipeline', 'operations')->exists()
        ) {
            $now = now();
            $defaults = [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'in_table' => true, 'is_system' => true, 'sort_order' => 1, 'maps_from' => 'name'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'required' => false, 'in_table' => true, 'is_system' => false, 'sort_order' => 2, 'maps_from' => 'phone'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'in_table' => true, 'is_system' => false, 'sort_order' => 3, 'maps_from' => 'email'],
                ['key' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false, 'in_table' => false, 'is_system' => false, 'sort_order' => 4, 'maps_from' => 'company'],
                ['key' => 'city', 'label' => 'City', 'type' => 'text', 'required' => false, 'in_table' => true, 'is_system' => false, 'sort_order' => 5, 'maps_from' => 'city'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'in_table' => true, 'is_system' => true, 'sort_order' => 6, 'maps_from' => 'status', 'options' => json_encode(['new', 'interested', 'contacted', 'converted', 'lost', 'request'])],
                ['key' => 'assigned_to', 'label' => 'Assign agent', 'type' => 'select', 'required' => false, 'in_table' => false, 'is_system' => true, 'sort_order' => 7, 'maps_from' => 'assigned_to'],
                ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'in_table' => false, 'is_system' => false, 'sort_order' => 8, 'maps_from' => 'notes'],
            ];

            foreach ($defaults as $field) {
                DB::table('crm_fields')->insert([
                    'pipeline' => 'operations',
                    'key' => $field['key'],
                    'maps_from' => $field['maps_from'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'] ?? null,
                    'required' => $field['required'],
                    'active' => true,
                    'in_form' => true,
                    'in_table' => $field['in_table'],
                    'in_excel' => false,
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
        DB::table('crm_fields')->where('pipeline', 'operations')->delete();

        Schema::table('crm_fields', function (Blueprint $table) {
            $table->dropUnique(['pipeline', 'key']);
            $table->dropIndex(['pipeline']);
        });

        Schema::table('crm_fields', function (Blueprint $table) {
            $table->dropColumn(['pipeline', 'maps_from']);
            $table->unique('key');
        });
    }
};
