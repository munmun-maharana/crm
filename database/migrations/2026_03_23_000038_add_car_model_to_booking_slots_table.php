<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_slots', 'car_model')) {
                $table->string('car_model', 160)->nullable()->after('customer_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_slots', function (Blueprint $table) {
            if (Schema::hasColumn('booking_slots', 'car_model')) {
                $table->dropColumn('car_model');
            }
        });
    }
};
