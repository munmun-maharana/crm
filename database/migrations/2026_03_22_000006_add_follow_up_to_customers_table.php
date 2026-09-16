<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('follow_up')->default(false)->after('last_contact_at');
            $table->timestamp('follow_up_at')->nullable()->after('follow_up');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['follow_up', 'follow_up_at']);
        });
    }
};
