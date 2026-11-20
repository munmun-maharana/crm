<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop foreign key if exists
        try {
            DB::statement('ALTER TABLE customers DROP FOREIGN KEY customers_source_customer_id_foreign');
        } catch (\Throwable $e) {}

        // 2. Drop unique index if exists
        try {
            DB::statement('ALTER TABLE customers DROP INDEX customers_source_customer_id_unique');
        } catch (\Throwable $e) {}

        // 3. Ensure regular index exists on source_customer_id
        try {
            $hasIndex = collect(DB::select("SHOW INDEX FROM customers WHERE Key_name = 'customers_source_customer_id_index'"))->isNotEmpty();
            if (! $hasIndex) {
                DB::statement('ALTER TABLE customers ADD INDEX customers_source_customer_id_index (source_customer_id)');
            }
        } catch (\Throwable $e) {}

        // 4. Re-add foreign key referencing customers(id) with null on delete
        try {
            DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_source_customer_id_foreign FOREIGN KEY (source_customer_id) REFERENCES customers (id) ON DELETE SET NULL');
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
    }
};
