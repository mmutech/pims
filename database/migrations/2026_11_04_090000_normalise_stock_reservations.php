<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservations are header + line records. The original header carried a single
 * item/dimension/quantity, which cannot describe a multi-line reservation.
 * This migration moves all quantity and stock-target fields to the lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The warehouse foreign key currently rides on the composite index, so
        // the replacement index must exist before the old one is dropped.
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->index(['warehouse_id', 'status'], 'stock_reservations_wh_status_index');
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropForeign(['stock_dimension_id']);
            $table->dropForeign(['item_id']);
            $table->dropForeign(['batch_id']);
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropIndex('stock_reservations_wh_item_status_index');
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropColumn([
                'stock_dimension_id',
                'item_id',
                'batch_id',
                'reserved_quantity',
                'consumed_quantity',
                'released_quantity',
            ]);
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->string('release_reason', 500)->nullable();
            $table->timestamp('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreignId('stock_dimension_id')->nullable()
                ->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('item_id')->nullable()
                ->constrained('items')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()
                ->constrained('item_batches')->restrictOnDelete();
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('consumed_quantity', 18, 4)->default(0);
            $table->decimal('released_quantity', 18, 4)->default(0);
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->index(['warehouse_id', 'item_id', 'status'], 'stock_reservations_wh_item_status_index');
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropIndex('stock_reservations_wh_status_index');
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropColumn(['release_reason', 'closed_at']);
        });
    }
};
