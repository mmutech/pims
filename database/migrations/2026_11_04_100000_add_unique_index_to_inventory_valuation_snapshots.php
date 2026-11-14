<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One valuation row per day, warehouse and item. The service regenerates the
 * same row instead of appending duplicates, so the database enforces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_valuation_snapshots', function (Blueprint $table) {
            $table->dropIndex('inv_valuation_snapshot_lookup_index');
            $table->unique(
                ['snapshot_date', 'warehouse_id', 'item_id'],
                'inv_valuation_snapshot_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_valuation_snapshots', function (Blueprint $table) {
            $table->dropUnique('inv_valuation_snapshot_unique');
            $table->index(
                ['snapshot_date', 'warehouse_id', 'item_id'],
                'inv_valuation_snapshot_lookup_index',
            );
        });
    }
};
