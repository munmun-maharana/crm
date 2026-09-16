<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customer_activities MODIFY COLUMN type ENUM('note','chat','call','whatsapp','email','sms','status','system') NOT NULL DEFAULT 'note'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE customer_activities MODIFY COLUMN type ENUM('note','call','whatsapp','email','sms','status','system') NOT NULL DEFAULT 'note'");
    }
};
