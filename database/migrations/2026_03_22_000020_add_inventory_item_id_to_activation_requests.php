<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activation_requests', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')
                ->nullable()
                ->after('inventory_type_id')
                ->constrained('inventory_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activation_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
        });
    }
};
