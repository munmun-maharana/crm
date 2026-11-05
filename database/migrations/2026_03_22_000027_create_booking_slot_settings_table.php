<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_slot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('status', 60)->default('confirmed');
            $table->string('title', 160)->default('Slot Booked');
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_slot_settings');
    }
};

