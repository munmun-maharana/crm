<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('held_by_user_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('assigned_to_name', 160)
                ->nullable()
                ->after('held_by_user_id');
        });

        if (Schema::hasColumn('inventory_items', 'assigned_to_user_id')) {
            $rows = DB::table('inventory_items')->get(['id', 'status', 'assigned_to_user_id']);
            foreach ($rows as $row) {
                if (! $row->assigned_to_user_id) {
                    continue;
                }
                if ($row->status === 'in_stock') {
                    DB::table('inventory_items')->where('id', $row->id)->update([
                        'held_by_user_id' => $row->assigned_to_user_id,
                    ]);
                } elseif ($row->status === 'assigned') {
                    $name = DB::table('users')->where('id', $row->assigned_to_user_id)->value('name');
                    DB::table('inventory_items')->where('id', $row->id)->update([
                        'assigned_to_name' => $name,
                    ]);
                }
            }

            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('assigned_to_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('inventory_items')->whereNotNull('held_by_user_id')->update([
            'assigned_to_user_id' => DB::raw('held_by_user_id'),
        ]);

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('held_by_user_id');
            $table->dropColumn('assigned_to_name');
        });
    }
};
