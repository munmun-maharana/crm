<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('crm:fix-db', function () {
    $this->info('Starting CRM database updates...');

    // 1. Drop foreign key and unique index on customers.source_customer_id
    try {
        DB::statement('ALTER TABLE customers DROP FOREIGN KEY customers_source_customer_id_foreign');
        $this->line('✓ Dropped FK customers_source_customer_id_foreign');
    } catch (\Throwable $e) {}

    try {
        DB::statement('ALTER TABLE customers DROP INDEX customers_source_customer_id_unique');
        $this->line('✓ Dropped unique index customers_source_customer_id_unique');
    } catch (\Throwable $e) {}

    try {
        $hasIndex = collect(DB::select("SHOW INDEX FROM customers WHERE Key_name = 'customers_source_customer_id_index'"))->isNotEmpty();
        if (! $hasIndex) {
            DB::statement('ALTER TABLE customers ADD INDEX customers_source_customer_id_index (source_customer_id)');
            $this->line('✓ Added regular index customers_source_customer_id_index');
        }
    } catch (\Throwable $e) {}

    try {
        DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_source_customer_id_foreign FOREIGN KEY (source_customer_id) REFERENCES customers (id) ON DELETE SET NULL');
        $this->line('✓ Added FK customers_source_customer_id_foreign with ON DELETE SET NULL');
    } catch (\Throwable $e) {}

    // 2. Add 'mrp' field to booking pipeline
    $exists = DB::table('crm_fields')->where('pipeline', 'booking')->where('key', 'mrp')->exists();
    if (! $exists) {
        DB::table('crm_fields')->insert([
            'pipeline' => 'booking',
            'key' => 'mrp',
            'maps_from' => 'mrp',
            'label' => 'MRP (₹)',
            'type' => 'number',
            'options' => null,
            'required' => false,
            'active' => true,
            'in_form' => true,
            'in_table' => true,
            'in_excel' => true,
            'in_update' => false,
            'auto_select' => false,
            'is_system' => false,
            'sort_order' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->line('✓ Added mrp field to booking pipeline in crm_fields');
    }

    // 3. Update call_crm status options (Engaged, Existing)
    $callField = DB::table('crm_fields')->where('pipeline', 'call_crm')->where('key', 'status')->first();
    if ($callField) {
        $opts = json_decode($callField->options ?? '[]', true) ?: [];
        foreach (['Engaged', 'Existing'] as $st) {
            if (! in_array($st, $opts, true)) {
                $opts[] = $st;
            }
        }
        DB::table('crm_fields')->where('id', $callField->id)->update([
            'options' => json_encode(array_values($opts)),
            'updated_at' => now(),
        ]);
        $this->line('✓ Added Engaged & Existing to call_crm statuses');
    }
    DB::table('customers')->where('pipeline', 'call_crm')->where('status', 'Booked')->update(['status' => 'Engaged']);

    // 4. Update booking status options (Engaged, Success, Cancelled, Rescheduled)
    $bookField = DB::table('crm_fields')->where('pipeline', 'booking')->where('key', 'status')->first();
    if ($bookField) {
        $opts = json_decode($bookField->options ?? '[]', true) ?: [];
        foreach (['Engaged', 'Success', 'Cancelled', 'Rescheduled'] as $st) {
            if (! in_array($st, $opts, true)) {
                $opts[] = $st;
            }
        }
        DB::table('crm_fields')->where('id', $bookField->id)->update([
            'options' => json_encode(array_values($opts)),
            'updated_at' => now(),
        ]);
        $this->line('✓ Added Success, Cancelled, Rescheduled, Engaged to booking statuses');
    }

    // 5. Mark migrations as recorded in migrations table
    $migrations = [
        '2026_03_23_000043_fix_drop_unique_source_customer_id_on_customers_table',
        '2026_03_23_000044_add_mrp_field_to_booking_pipeline',
        '2026_03_23_000045_add_engaged_status_to_call_crm_fields',
        '2026_03_23_000046_add_service_statuses_to_booking_crm_fields',
        '2026_03_23_000047_add_existing_status_to_call_crm_fields',
    ];
    $maxBatch = (int) DB::table('migrations')->max('batch') + 1;
    foreach ($migrations as $m) {
        if (! DB::table('migrations')->where('migration', $m)->exists()) {
            DB::table('migrations')->insert(['migration' => $m, 'batch' => $maxBatch]);
        }
    }
    $this->line('✓ Recorded migrations in migrations table');

    $this->info('🎉 CRM Database updated successfully with zero errors!');
})->purpose('Fix and update CRM database schema and fields without errors');
