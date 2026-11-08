<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureLatestSchema();
    }

    private function ensureLatestSchema(): void
    {
        try {
            if (! Schema::hasTable('whatsapp_settings')) {
                Schema::create('whatsapp_settings', function (Blueprint $table) {
                    $table->id();
                    $table->string('phone')->nullable();
                    $table->text('message_template')->nullable();
                    $table->timestamps();
                });
            }

            if (Schema::hasTable('crm_fields') && ! Schema::hasColumn('crm_fields', 'in_update')) {
                Schema::table('crm_fields', function (Blueprint $table) {
                    $table->boolean('in_update')->default(false)->after('in_excel');
                });
            }

            if (Schema::hasTable('crm_fields') && ! Schema::hasColumn('crm_fields', 'auto_select')) {
                Schema::table('crm_fields', function (Blueprint $table) {
                    $table->boolean('auto_select')->default(false)->after('in_update');
                });
            }

            if (! Schema::hasTable('booking_slots')) {
                Schema::create('booking_slots', function (Blueprint $table) {
                    $table->id();
                    $table->date('slot_date')->index();
                    $table->string('start_time', 10);
                    $table->string('end_time', 10);
                    $table->string('label', 80);
                    $table->string('status', 20)->default('available')->index();
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

            if (! Schema::hasTable('booking_slot_settings')) {
                Schema::create('booking_slot_settings', function (Blueprint $table) {
                    $table->id();
                    $table->string('status', 60)->default('confirmed');
                    $table->string('title', 160)->default('Slot Booked');
                    $table->text('body')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable) {
            // Ignore during CLI boot if database is not yet configured
        }
    }
}
