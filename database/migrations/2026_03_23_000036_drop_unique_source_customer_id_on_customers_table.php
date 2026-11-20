<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique('customers_source_customer_id_unique');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('source_customer_id', 'customers_source_customer_id_index');
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('source_customer_id', 'customers_source_customer_id_unique');
            $table->dropIndex('customers_source_customer_id_index');
        });
    }
};
