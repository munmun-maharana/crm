<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activation_request_id')->constrained('activation_requests')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('inventory_type_id')->constrained('inventory_types')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['activation_request_id', 'inventory_item_id'], 'act_req_item_unique');
        });

        $rows = DB::table('activation_requests')
            ->whereNotNull('inventory_item_id')
            ->get(['id', 'inventory_item_id', 'inventory_type_id', 'created_at', 'updated_at']);

        foreach ($rows as $row) {
            DB::table('activation_request_items')->insert([
                'activation_request_id' => $row->id,
                'inventory_item_id' => $row->inventory_item_id,
                'inventory_type_id' => $row->inventory_type_id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_request_items');
    }
};
