<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $field = DB::table('crm_fields')
            ->where('pipeline', 'call_crm')
            ->where('key', 'status')
            ->first();

        if ($field) {
            $options = json_decode($field->options ?? '[]', true);
            if (! is_array($options)) {
                $options = [];
            }
            if (! in_array('Existing', $options, true)) {
                $options[] = 'Existing';
            }
            DB::table('crm_fields')
                ->where('id', $field->id)
                ->update([
                    'options' => json_encode(array_values($options)),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Keep options intact
    }
};
