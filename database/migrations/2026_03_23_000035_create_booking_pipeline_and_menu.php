<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add 'booking' to menu_settings if not already present
        if (Schema::hasTable('menu_settings')) {
            $exists = DB::table('menu_settings')->where('key', 'booking')->exists();
            if (! $exists) {
                // Shift sort orders >= 4 to make room
                DB::table('menu_settings')->where('sort_order', '>=', 4)->increment('sort_order');

                DB::table('menu_settings')->insert([
                    'key' => 'booking',
                    'label' => 'ASSIGN SERVICE',
                    'default_label' => 'ASSIGN SERVICE',
                    'icon' => 'book_online',
                    'route' => 'booking.index',
                    'sort_order' => 4,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Clone call_crm fields for 'booking' pipeline if not present
        if (Schema::hasTable('crm_fields')) {
            $hasBookingFields = DB::table('crm_fields')->where('pipeline', 'booking')->exists();
            if (! $hasBookingFields) {
                $sourceFields = DB::table('crm_fields')
                    ->where('pipeline', 'call_crm')
                    ->get();

                if ($sourceFields->isEmpty()) {
                    $sourceFields = DB::table('crm_fields')
                        ->where('pipeline', 'crm')
                        ->get();
                }

                foreach ($sourceFields as $f) {
                    $arr = (array) $f;
                    unset($arr['id']);
                    $arr['pipeline'] = 'booking';
                    $arr['created_at'] = now();
                    $arr['updated_at'] = now();
                    DB::table('crm_fields')->insert($arr);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_settings')) {
            DB::table('menu_settings')->where('key', 'booking')->delete();
        }
        if (Schema::hasTable('crm_fields')) {
            DB::table('crm_fields')->where('pipeline', 'booking')->delete();
        }
    }
};
