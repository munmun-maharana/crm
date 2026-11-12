<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pincodes')) {
            Schema::create('pincodes', function (Blueprint $table) {
                $table->id();
                $table->string('pincode', 30)->index();
                $table->string('area')->nullable();
                $table->string('city')->nullable()->index();
                $table->string('state')->nullable();
                $table->string('zone')->nullable();
                $table->string('status', 60)->default('Serviceable')->index();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! DB::table('crm_fields')->where('pipeline', 'pincodes')->exists()) {
            $now = now();
            $defaults = [
                [
                    'key' => 'pincode',
                    'label' => 'Pincode',
                    'type' => 'text',
                    'required' => true,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'area',
                    'label' => 'Area / Locality',
                    'type' => 'text',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 2,
                ],
                [
                    'key' => 'city',
                    'label' => 'City / District',
                    'type' => 'text',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 3,
                ],
                [
                    'key' => 'state',
                    'label' => 'State',
                    'type' => 'text',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 4,
                ],
                [
                    'key' => 'zone',
                    'label' => 'Zone / Sector',
                    'type' => 'text',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 5,
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => json_encode(['Serviceable', 'Non-Serviceable', 'Hold', 'Coming Soon']),
                    'required' => true,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => true,
                    'is_system' => true,
                    'sort_order' => 6,
                ],
                [
                    'key' => 'assigned_to',
                    'label' => 'Assigned Executive',
                    'type' => 'select',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => false,
                    'auto_select' => false,
                    'is_system' => true,
                    'sort_order' => 7,
                ],
                [
                    'key' => 'notes',
                    'label' => 'Notes / Remarks',
                    'type' => 'textarea',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => false,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 8,
                ],
            ];

            foreach ($defaults as $field) {
                DB::table('crm_fields')->insert([
                    'pipeline' => 'pincodes',
                    'key' => $field['key'],
                    'maps_from' => null,
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'] ?? null,
                    'required' => $field['required'],
                    'active' => true,
                    'in_form' => $field['in_form'],
                    'in_table' => $field['in_table'],
                    'in_excel' => $field['in_excel'],
                    'in_update' => false,
                    'auto_select' => $field['auto_select'],
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
        DB::table('crm_fields')->where('pipeline', 'pincodes')->delete();
        Schema::dropIfExists('pincodes');
    }
};

