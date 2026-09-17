<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('pipeline')->default('crm')->index()->after('assigned_to');
            $table->foreignId('source_customer_id')->nullable()->after('pipeline')
                ->constrained('customers')->nullOnDelete();
            $table->unique('source_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['source_customer_id']);
            $table->dropConstrainedForeignId('source_customer_id');
            $table->dropColumn('pipeline');
        });
    }
};
