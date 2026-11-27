<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('note_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('note_templates', 'show_in_crm')) {
                $table->boolean('show_in_crm')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('note_templates', 'show_in_call_crm')) {
                $table->boolean('show_in_call_crm')->default(false)->after('show_in_crm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('note_templates', function (Blueprint $table) {
            if (Schema::hasColumn('note_templates', 'show_in_call_crm')) {
                $table->dropColumn('show_in_call_crm');
            }
            if (Schema::hasColumn('note_templates', 'show_in_crm')) {
                $table->dropColumn('show_in_crm');
            }
        });
    }
};
