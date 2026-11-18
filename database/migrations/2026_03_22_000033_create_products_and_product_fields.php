<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name')->index();
                $table->string('code', 60)->nullable()->index();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('category', 100)->nullable()->index();
                $table->string('unit', 50)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 60)->default('Active')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_product')) {
            Schema::create('customer_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->integer('quantity')->default(1);
                $table->decimal('price', 10, 2)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['customer_id', 'product_id']);
            });
        }

        if (! DB::table('crm_fields')->where('pipeline', 'products')->exists()) {
            $now = now();
            $defaults = [
                [
                    'key' => 'name',
                    'label' => 'Product Name',
                    'type' => 'text',
                    'required' => true,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => true,
                    'sort_order' => 1,
                    'options' => null,
                ],
                [
                    'key' => 'code',
                    'label' => 'Product Code / SKU',
                    'type' => 'text',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 2,
                    'options' => null,
                ],
                [
                    'key' => 'price',
                    'label' => 'Price (₹)',
                    'type' => 'number',
                    'required' => true,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 3,
                    'options' => null,
                ],
                [
                    'key' => 'category',
                    'label' => 'Category',
                    'type' => 'select',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 4,
                    'options' => json_encode(['GPS Tracker', 'Dashcam', 'Fuel Sensor', 'Speed Governor', 'Accessories', 'Subscription', 'Service']),
                ],
                [
                    'key' => 'unit',
                    'label' => 'Unit',
                    'type' => 'select',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 5,
                    'options' => json_encode(['pcs', 'unit', 'box', 'set', 'year', 'month']),
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'required' => true,
                    'in_form' => true,
                    'in_table' => true,
                    'in_excel' => true,
                    'auto_select' => true,
                    'is_system' => false,
                    'sort_order' => 6,
                    'options' => json_encode(['Active', 'Inactive']),
                ],
                [
                    'key' => 'description',
                    'label' => 'Description',
                    'type' => 'textarea',
                    'required' => false,
                    'in_form' => true,
                    'in_table' => false,
                    'in_excel' => true,
                    'auto_select' => false,
                    'is_system' => false,
                    'sort_order' => 7,
                    'options' => null,
                ],
            ];

            foreach ($defaults as $row) {
                DB::table('crm_fields')->insert(array_merge($row, [
                    'pipeline' => 'products',
                    'maps_from' => null,
                    'active' => true,
                    'in_update' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product');
        Schema::dropIfExists('products');
        DB::table('crm_fields')->where('pipeline', 'products')->delete();
    }
};
