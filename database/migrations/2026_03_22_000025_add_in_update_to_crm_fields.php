<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_fields', 'in_update')) {
                $table->boolean('in_update')->default(false)->after('in_excel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_fields', function (Blueprint $table) {
            if (Schema::hasColumn('crm_fields', 'in_update')) {
                $table->dropColumn('in_update');
            }
        });
    }
};

