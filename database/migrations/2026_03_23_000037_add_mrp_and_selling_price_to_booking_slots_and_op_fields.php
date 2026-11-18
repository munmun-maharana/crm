<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_slots', 'mrp')) {
                $table->decimal('mrp', 10, 2)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('booking_slots', 'selling_price')) {
                $table->decimal('selling_price', 10, 2)->nullable()->after('mrp');
            }
        });

        // Ensure Team Ops ('operations') has MRP and Business Selling Price fields
        if (Schema::hasTable('crm_fields')) {
            $now = now();
            $maxSort = (int) (DB::table('crm_fields')->where('pipeline', 'operations')->max('sort_order') ?? 0);

            if (! DB::table('crm_fields')->where('pipeline', 'operations')->where('key', 'mrp')->exists()) {
                DB::table('crm_fields')->insert([
                    'pipeline' => 'operations',
                    'maps_from' => 'mrp',
                    'key' => 'mrp',
                    'label' => 'MRP (₹)',
                    'type' => 'number',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => false,
                    'in_update' => false,
                    'auto_select' => false,
                    'is_system' => false,
                    'active' => true,
                    'sort_order' => ++$maxSort,
                    'options' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (! DB::table('crm_fields')->where('pipeline', 'operations')->where('key', 'selling_price')->exists()) {
                DB::table('crm_fields')->insert([
                    'pipeline' => 'operations',
                    'maps_from' => 'selling_price',
                    'key' => 'selling_price',
                    'label' => 'Business Selling Price (₹)',
                    'type' => 'number',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => false,
                    'in_update' => false,
                    'auto_select' => false,
                    'is_system' => false,
                    'active' => true,
                    'sort_order' => ++$maxSort,
                    'options' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('booking_slots', function (Blueprint $table) {
            if (Schema::hasColumn('booking_slots', 'selling_price')) {
                $table->dropColumn('selling_price');
            }
            if (Schema::hasColumn('booking_slots', 'mrp')) {
                $table->dropColumn('mrp');
            }
        });

        if (Schema::hasTable('crm_fields')) {
            DB::table('crm_fields')
                ->where('pipeline', 'operations')
                ->whereIn('key', ['mrp', 'selling_price'])
                ->delete();
        }
    }
};
