<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_activity_logs')) {
            Schema::create('user_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('user_role', 60)->index();
                $table->string('session_id', 100)->nullable()->index();
                $table->string('event_type', 30)->index(); // page_view, click, select, submit, api
                $table->string('page_path', 255)->index();
                $table->string('page_title', 160)->nullable();
                $table->string('target_label', 255)->nullable();
                $table->string('target_value', 255)->nullable();
                $table->string('method', 12)->nullable();
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['event_type', 'created_at']);
            });
        }

        if (Schema::hasTable('menu_settings')) {
            $exists = DB::table('menu_settings')->where('key', 'analytics')->exists();
            if (! $exists) {
                $settingsOrder = DB::table('menu_settings')->where('key', 'settings')->value('sort_order');
                $sortOrder = $settingsOrder ? (int) $settingsOrder : ((int) DB::table('menu_settings')->max('sort_order') + 1);
                if ($settingsOrder) {
                    DB::table('menu_settings')->where('sort_order', '>=', $sortOrder)->increment('sort_order');
                }

                DB::table('menu_settings')->insert([
                    'key' => 'analytics',
                    'label' => 'Analytics',
                    'default_label' => 'Analytics',
                    'icon' => 'analytics',
                    'route' => 'analytics.index',
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
        if (Schema::hasTable('menu_settings')) {
            DB::table('menu_settings')->where('key', 'analytics')->delete();
        }
    }
};
