<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('key', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique('key');
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_type_id')->constrained('inventory_types')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 120)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 40)->default('in_stock');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['inventory_type_id', 'status']);
        });

        $now = now();
        $defaults = [
            ['name' => 'GPS device', 'key' => 'gps_device', 'sort_order' => 1],
            ['name' => 'SIM', 'key' => 'sim', 'sort_order' => 2],
            ['name' => 'License key', 'key' => 'license_key', 'sort_order' => 3],
            ['name' => 'Fuel sensor', 'key' => 'fuel_sensor', 'sort_order' => 4],
        ];

        foreach ($defaults as $row) {
            DB::table('inventory_types')->insert([
                'name' => $row['name'],
                'key' => $row['key'],
                'sort_order' => $row['sort_order'],
                'is_active' => true,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_types');
    }
};
