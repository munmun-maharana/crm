<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_work_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('user_role', 64)->index();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable()->index();
            $table->string('status', 20)->default('active')->index(); // active, on_break, ended
            $table->dateTime('break_started_at')->nullable();
            $table->unsignedInteger('total_break_seconds')->default(0);
            $table->unsignedInteger('total_waste_seconds')->default(0);
            $table->unsignedInteger('total_seconds')->default(0);
            $table->unsignedInteger('desk_seconds')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_work_sessions');
    }
};
