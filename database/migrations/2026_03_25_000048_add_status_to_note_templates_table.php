<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('note_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('note_templates', 'status')) {
                $table->string('status', 60)->nullable()->after('body');
            }
        });
    }

    public function down(): void
    {
        Schema::table('note_templates', function (Blueprint $table) {
            if (Schema::hasColumn('note_templates', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
