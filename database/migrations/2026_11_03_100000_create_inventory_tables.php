<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 inventory posting core: dimensions, transactions, ledger, balances,
 * reservations and cost layers. PostgreSQL 16 compatible; all identifiers are
 * explicit and short enough for MySQL's 64-character limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->restrictOnDelete();
            $table->foreignId('warehouse_bin_id')->nullable()->constrained('warehouse_bins')->restrictOnDelete();
            $table->string('code', 120)->unique();
            $table->string('type', 30)->default('warehouse')->index();
            $table->boolean('is_available')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('warehouse_id', 'stock_dimensions_warehouse_index');
            $table->index(['warehouse_id', 'type'], 'stock_dimensions_wh_type_index');
        });

        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 40)->unique();
            $table->string('transaction_type', 40)->index();
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->string('idempotency_key', 150)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 20)->default('posted')->index();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->foreignId('reversed_by_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'stock_transactions_source_index');
            $table->index(['warehouse_id', 'posted_at'], 'stock_transactions_warehouse_posted_index');
            $table->index('reversed_by_transaction_id', 'stock_transactions_reversed_by_index');
        });

        Schema::create('stock_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transaction_id')->constrained('stock_transactions')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->foreignId('serial_number_id')->nullable()->constrained('item_serial_numbers')->restrictOnDelete();
            $table->smallInteger('direction');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'stock_dimension_id'], 'stock_tx_items_item_dimension_index');
            $table->index('stock_transaction_id', 'stock_tx_items_transaction_index');
            $table->index('stock_dimension_id', 'stock_tx_items_dimension_index');
            $table->index('batch_id', 'stock_tx_items_batch_index');
        });

        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transaction_id')->constrained('stock_transactions')->restrictOnDelete();
            $table->foreignId('stock_transaction_item_id')->constrained('stock_transaction_items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->foreignId('serial_number_id')->nullable()->constrained('item_serial_numbers')->restrictOnDelete();
            $table->string('movement_type', 40)->index();
            $table->decimal('quantity_in', 18, 4)->default(0);
            $table->decimal('quantity_out', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('value_in', 18, 2)->default(0);
            $table->decimal('value_out', 18, 2)->default(0);
            $table->decimal('balance_quantity_after', 18, 4);
            $table->decimal('balance_value_after', 18, 2);
            $table->timestamp('posted_at')->index();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['warehouse_id', 'item_id'], 'stock_ledger_warehouse_item_index');
            $table->index(['item_id', 'stock_dimension_id'], 'stock_ledger_item_dimension_index');
            $table->index('stock_transaction_item_id', 'stock_ledger_transaction_item_index');
            $table->index('batch_id', 'stock_ledger_batch_index');
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            // Nullable batch_id cannot participate reliably in a cross-database
            // unique key, so a non-null projection (batch_id or 0) is stored.
            $table->unsignedBigInteger('batch_key')->default(0);
            $table->decimal('on_hand_quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('available_quantity', 18, 4)->default(0);
            $table->decimal('average_unit_cost', 18, 6)->default(0);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->unique(
                ['warehouse_id', 'stock_dimension_id', 'item_id', 'batch_key'],
                'inventory_balances_unique',
            );
            $table->index(['warehouse_id', 'item_id'], 'inventory_balances_warehouse_item_index');
            $table->index(['item_id', 'stock_dimension_id'], 'inventory_balances_item_dimension_index');
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->decimal('reserved_quantity', 18, 4);
            $table->decimal('consumed_quantity', 18, 4)->default(0);
            $table->decimal('released_quantity', 18, 4)->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'stock_reservations_source_index');
            $table->index(['warehouse_id', 'item_id', 'status'], 'stock_reservations_wh_item_status_index');
        });

        Schema::create('stock_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_reservation_id')->constrained('stock_reservations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('consumed_quantity', 18, 4)->default(0);
            $table->decimal('released_quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->index('stock_reservation_id', 'stock_res_items_reservation_index');
        });

        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->foreignId('stock_transaction_item_id')->constrained('stock_transaction_items')->restrictOnDelete();
            $table->decimal('original_quantity', 18, 4);
            $table->decimal('remaining_quantity', 18, 4);
            $table->decimal('unit_cost', 18, 6);
            $table->timestamp('received_at');
            $table->unsignedBigInteger('sequence');
            $table->timestamps();

            $table->index(['item_id', 'warehouse_id', 'remaining_quantity'], 'inv_cost_layers_item_wh_remaining_idx');
            $table->index(['item_id', 'warehouse_id', 'sequence'], 'inv_cost_layers_item_wh_sequence_idx');
        });

        Schema::create('inventory_valuation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->string('valuation_method', 30);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['snapshot_date', 'warehouse_id', 'item_id'], 'inv_valuation_snapshot_lookup_index');
        });

        $this->addCheckConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_valuation_snapshots');
        Schema::dropIfExists('inventory_cost_layers');
        Schema::dropIfExists('stock_reservation_items');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('stock_ledger');
        Schema::dropIfExists('stock_transaction_items');
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('stock_dimensions');
    }

    private function addCheckConstraints(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        DB::statement(
            'ALTER TABLE stock_transaction_items ADD CONSTRAINT stock_tx_items_quantity_check CHECK (quantity > 0)'
        );

        DB::statement(
            'ALTER TABLE stock_ledger ADD CONSTRAINT stock_ledger_direction_check CHECK '
            .'(quantity_in >= 0 AND quantity_out >= 0 AND '
            .'((quantity_in > 0 AND quantity_out = 0) OR (quantity_out > 0 AND quantity_in = 0)))'
        );

        DB::statement(
            'ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_quantities_check CHECK '
            .'(on_hand_quantity >= 0 AND reserved_quantity >= 0 AND reserved_quantity <= on_hand_quantity)'
        );

        DB::statement(
            'ALTER TABLE inventory_cost_layers ADD CONSTRAINT inv_cost_layers_quantities_check CHECK '
            .'(original_quantity >= 0 AND remaining_quantity >= 0 AND remaining_quantity <= original_quantity)'
        );
    }
};
