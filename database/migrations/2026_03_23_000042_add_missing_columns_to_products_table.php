<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'code')) {
                    $table->string('code', 60)->nullable()->index()->after('name');
                }
                if (! Schema::hasColumn('products', 'category')) {
                    $table->string('category', 100)->nullable()->index()->after('price');
                }
                if (! Schema::hasColumn('products', 'unit')) {
                    $table->string('unit', 50)->nullable()->after('category');
                }
                if (! Schema::hasColumn('products', 'is_active')) {
                    $table->boolean('is_active')->default(true)->index()->after('status');
                }
                if (! Schema::hasColumn('products', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('is_active');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $cols = ['code', 'category', 'unit', 'is_active', 'sort_order'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('products', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
