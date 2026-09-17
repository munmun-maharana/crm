<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'meta')) {
                $table->json('meta')->nullable()->after('role');
            }
        });

        if (! DB::table('crm_fields')->where('pipeline', 'users')->exists()) {
            $now = now();
            $defaults = [
                ['key' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true, 'in_form' => true, 'in_table' => true, 'is_system' => true, 'sort_order' => 1],
                ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true, 'in_form' => true, 'in_table' => true, 'is_system' => true, 'sort_order' => 2],
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'in_form' => true, 'in_table' => true, 'is_system' => true, 'sort_order' => 3],
                ['key' => 'password', 'label' => 'Password', 'type' => 'text', 'required' => true, 'in_form' => true, 'in_table' => false, 'is_system' => true, 'sort_order' => 4],
                ['key' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true, 'in_form' => true, 'in_table' => true, 'is_system' => true, 'sort_order' => 5, 'options' => json_encode(['front_office', 'coordinator', 'operation', 'admin'])],
            ];

            foreach ($defaults as $field) {
                DB::table('crm_fields')->insert([
                    'pipeline' => 'users',
                    'key' => $field['key'],
                    'maps_from' => null,
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'] ?? null,
                    'required' => $field['required'],
                    'active' => true,
                    'in_form' => $field['in_form'],
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
        DB::table('crm_fields')->where('pipeline', 'users')->delete();

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};
