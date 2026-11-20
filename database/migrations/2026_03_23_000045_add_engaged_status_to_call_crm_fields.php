<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['call_crm', 'booking'] as $pipeline) {
            $field = DB::table('crm_fields')
                ->where('pipeline', $pipeline)
                ->where('key', 'status')
                ->first();

            if ($field) {
                $options = json_decode($field->options, true) ?: [];
                if (! in_array('Engaged', $options, true)) {
                    $options[] = 'Engaged';
                    DB::table('crm_fields')
                        ->where('id', $field->id)
                        ->update(['options' => json_encode(array_values($options))]);
                }
            }
        }

        // Also update any existing 'Booked' customers in call_crm to 'Engaged'
        DB::table('customers')
            ->where('pipeline', 'call_crm')
            ->where('status', 'Booked')
            ->update(['status' => 'Engaged']);
    }

    public function down(): void
    {
    }
};
