<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 100);
            $table->string('default_label', 100);
            $table->string('icon', 50)->nullable();
            $table->string('route', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        $defaultMenus = [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'default_label' => 'Dashboard',
                'icon' => 'dashboard',
                'route' => 'dashboard',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'crm',
                'label' => 'CRM',
                'default_label' => 'CRM',
                'icon' => 'people',
                'route' => 'customers.index',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'call_crm',
                'label' => 'CALL CRM',
                'default_label' => 'CALL CRM',
                'icon' => 'phone',
                'route' => 'call-crm.index',
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'operations',
                'label' => 'TEAM OPS',
                'default_label' => 'TEAM OPS',
                'icon' => 'engineering',
                'route' => 'operations.index',
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'pincodes',
                'label' => 'Pincode Manager',
                'default_label' => 'Pincode Manager',
                'icon' => 'location_on',
                'route' => 'pincodes.index',
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'products',
                'label' => 'Products',
                'default_label' => 'Products',
                'icon' => 'inventory_2',
                'route' => 'products.index',
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'users',
                'label' => 'Users',
                'default_label' => 'Users',
                'icon' => 'manage_accounts',
                'route' => 'users.index',
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'settings',
                'label' => 'Settings',
                'default_label' => 'Settings',
                'icon' => 'settings',
                'route' => 'settings.index',
                'sort_order' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('menu_settings')->insert($defaultMenus);
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_settings');
    }
};
