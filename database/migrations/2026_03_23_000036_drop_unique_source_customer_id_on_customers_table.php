<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('source_customer_id', 'customers_source_customer_id_index');
            $table->dropUnique('customers_source_customer_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('source_customer_id', 'customers_source_customer_id_unique');
            $table->dropIndex('customers_source_customer_id_index');
        });
    }
};
