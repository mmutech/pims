<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 inventory control: stock adjustments, stocktaking, variance analysis
 * and count reconciliation.
 *
 * PostgreSQL 16 compatible. All indexes and foreign keys use explicit short
 * names so the schema also runs on MySQL's 64-character identifier limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('count_type', 20)->default('cycle')->index();
            $table->text('scope_description')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('tolerance_quantity', 18, 4)->default(0);
            $table->decimal('tolerance_value', 18, 2)->default(0);
            $table->timestamp('snapshot_at')->nullable();
            $table->unsignedBigInteger('snapshot_ledger_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'stock_counts_wh_status_idx');
            $table->index(['created_by', 'status'], 'stock_counts_creator_status_idx');
        });

        Schema::create('stock_count_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('item_category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->boolean('include_zero_balances')->default(false);
            $table->timestamps();

            $table->index('stock_count_id', 'stock_count_scopes_count_idx');
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->foreignId('serial_number_id')->nullable()->constrained('item_serial_numbers')->nullOnDelete();
            $table->decimal('system_quantity', 18, 4)->default(0);
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->decimal('variance_quantity', 18, 4)->nullable();
            $table->decimal('system_unit_cost', 18, 6)->default(0);
            $table->decimal('system_value', 18, 2)->default(0);
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->boolean('is_recount')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('stock_count_id', 'stock_count_items_count_idx');
            $table->index(['item_id', 'stock_dimension_id'], 'stock_count_items_item_dim_idx');
            $table->unique(
                ['stock_count_id', 'item_id', 'stock_dimension_id', 'batch_id', 'serial_number_id', 'is_recount'],
                'stock_count_items_unique',
            );
        });

        Schema::create('stock_count_recounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_item_id')->constrained('stock_count_items')->cascadeOnDelete();
            $table->unsignedInteger('recount_number');
            $table->decimal('counted_quantity', 18, 4);
            $table->foreignId('counted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('counted_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_item_id', 'recount_number'], 'stock_count_recounts_unique');
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('stock_count_id')->nullable()->constrained('stock_counts')->nullOnDelete();
            $table->string('adjustment_type', 20)->index();
            $table->string('reason_code', 50)->index();
            $table->text('reason');
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('total_value', 18, 2)->default(0);
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_adjustments')->nullOnDelete();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'stock_adjustments_wh_status_idx');
            $table->index(['stock_count_id', 'status'], 'stock_adjustments_count_status_idx');
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('stock_count_item_id')->nullable()->constrained('stock_count_items')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->foreignId('serial_number_id')->nullable()->constrained('item_serial_numbers')->nullOnDelete();
            $table->decimal('quantity_delta', 18, 4);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('value_delta', 18, 2)->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('stock_transaction_item_id')->nullable()->constrained('stock_transaction_items')->nullOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('stock_adjustment_id', 'stock_adj_items_adj_idx');
            $table->index('stock_count_item_id', 'stock_adj_items_count_item_idx');
        });

        Schema::create('stock_count_variances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('stock_count_item_id')->constrained('stock_count_items')->cascadeOnDelete();
            $table->foreignId('stock_adjustment_id')->nullable()->constrained('stock_adjustments')->nullOnDelete();
            $table->string('variance_type', 30)->index();
            $table->decimal('quantity', 18, 4);
            $table->decimal('value', 18, 2)->default(0);
            $table->boolean('tolerance_breached')->default(false)->index();
            $table->string('resolution', 30)->default('pending')->index();
            $table->foreignId('investigated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('investigated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('stock_count_item_id', 'stock_count_variances_item_unique');
        });

        Schema::create('stock_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('snapshot_quantity', 18, 4)->default(0);
            $table->decimal('movement_quantity', 18, 4)->default(0);
            $table->decimal('adjustment_quantity', 18, 4)->default(0);
            $table->decimal('final_quantity', 18, 4)->default(0);
            $table->decimal('system_quantity', 18, 4)->default(0);
            $table->decimal('difference_quantity', 18, 4)->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->timestamps();

            $table->index(['stock_count_id', 'status'], 'stock_reconciliations_count_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reconciliations');
        Schema::dropIfExists('stock_count_variances');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_count_recounts');
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_count_scopes');
        Schema::dropIfExists('stock_counts');
    }
};
