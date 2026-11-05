<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->date('slot_date')->index();
            $table->string('start_time', 10);
            $table->string('end_time', 10);
            $table->string('label', 80);
            $table->string('status', 20)->default('available')->index(); // available, booked, cancelled
            $table->string('customer_name', 160)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('booked_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['slot_date', 'start_time', 'end_time'], 'booking_slots_date_time_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_slots');
    }
};

