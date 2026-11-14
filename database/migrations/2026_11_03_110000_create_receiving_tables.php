<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 receiving and quality: SRAs, delivery verification, inspections,
 * goods received notes, supplier returns. PostgreSQL 16 compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_receipt_advices', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('expected_delivery_date')->nullable();
            $table->string('vehicle_number', 50)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->string('delivery_note_number', 80)->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('purchase_order_id', 'sra_purchase_order_index');
            $table->index(['warehouse_id', 'status'], 'sra_warehouse_status_index');
            $table->index('supplier_id', 'sra_supplier_index');
        });

        Schema::create('store_receipt_advice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sra_id');
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('expected_quantity', 18, 4);
            $table->decimal('delivered_quantity', 18, 4)->default(0);
            $table->string('batch_number', 100)->nullable();
            $table->json('serial_numbers')->nullable();
            $table->text('condition_notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('sra_id', 'sra_items_sra_fk')
                ->references('id')
                ->on('store_receipt_advices')
                ->cascadeOnDelete();

            $table->unique(['sra_id', 'purchase_order_item_id'], 'sra_items_sra_po_item_unique');
            $table->index('item_id', 'sra_items_item_index');
        });

        Schema::create('delivery_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('sra_id')->constrained('store_receipt_advices')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');
            $table->string('status', 30)->default('pending')->index();
            $table->text('discrepancy_summary')->nullable();
            $table->timestamps();

            $table->unique('sra_id', 'delivery_verifications_sra_unique');
        });

        Schema::create('delivery_verification_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verification_id');
            $table->foreignId('sra_item_id')->constrained('store_receipt_advice_items')->restrictOnDelete();
            $table->decimal('verified_quantity', 18, 4);
            $table->decimal('damaged_quantity', 18, 4)->default(0);
            $table->decimal('short_quantity', 18, 4)->default(0);
            $table->string('condition', 20)->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('verification_id', 'delivery_verification_items_verification_fk')
                ->references('id')
                ->on('delivery_verifications')
                ->cascadeOnDelete();

            $table->unique(['verification_id', 'sra_item_id'], 'delivery_verification_items_unique');
        });

        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('sra_id')->constrained('store_receipt_advices')->restrictOnDelete();
            $table->foreignId('delivery_verification_id')->nullable()->constrained('delivery_verifications')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('inspector_id')->constrained('users')->restrictOnDelete();
            $table->date('inspection_date');
            $table->string('status', 30)->default('pending')->index();
            $table->string('method', 50)->nullable();
            $table->decimal('sample_size', 18, 4)->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('sra_id', 'quality_inspections_sra_index');
            $table->index(['warehouse_id', 'status'], 'quality_inspections_wh_status_index');
        });

        Schema::create('quality_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_inspection_id')->constrained('quality_inspections')->cascadeOnDelete();
            $table->foreignId('sra_item_id')->constrained('store_receipt_advice_items')->restrictOnDelete();
            $table->decimal('inspected_quantity', 18, 4);
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->decimal('quarantined_quantity', 18, 4)->default(0);
            $table->string('result', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['quality_inspection_id', 'sra_item_id'], 'quality_inspection_items_unique');
        });

        Schema::create('quality_inspection_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_inspection_item_id')->constrained('quality_inspection_items')->cascadeOnDelete();
            $table->string('finding_type', 50);
            $table->string('severity', 20);
            $table->text('description');
            $table->string('evidence_path', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('quality_inspection_item_id', 'qi_findings_item_index');
        });

        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('sra_id')->constrained('store_receipt_advices')->restrictOnDelete();
            $table->foreignId('quality_inspection_id')->nullable()->constrained('quality_inspections')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('received_date');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('goods_received_notes')->nullOnDelete();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_order_id', 'status'], 'grn_order_status_index');
            $table->index(['sra_id', 'status'], 'grn_sra_status_index');
            $table->index('warehouse_id', 'grn_warehouse_index');
        });

        Schema::create('goods_received_note_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grn_id');
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->foreignId('warehouse_bin_id')->nullable()->constrained('warehouse_bins')->restrictOnDelete();
            $table->foreignId('stock_transaction_item_id')->nullable()->constrained('stock_transaction_items')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('grn_id', 'grn_items_grn_fk')
                ->references('id')
                ->on('goods_received_notes')
                ->cascadeOnDelete();

            $table->unique(['grn_id', 'purchase_order_item_id'], 'grn_items_grn_po_item_unique');
            $table->index('item_id', 'grn_items_item_index');
        });

        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('grn_id')->nullable()->constrained('goods_received_notes')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 30)->default('draft')->index();
            $table->date('return_date');
            $table->string('credit_note_number', 80)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('supplier_id', 'supplier_returns_supplier_index');
            $table->index('grn_id', 'supplier_returns_grn_index');
            $table->index(['warehouse_id', 'status'], 'supplier_returns_wh_status_index');
        });

        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained('supplier_returns')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->json('serial_numbers')->nullable();
            $table->string('condition', 20)->default('good');
            $table->foreignId('stock_transaction_item_id')->nullable()->constrained('stock_transaction_items')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('item_id', 'supplier_return_items_item_index');
        });

        $this->addCheckConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');
        Schema::dropIfExists('goods_received_note_items');
        Schema::dropIfExists('goods_received_notes');
        Schema::dropIfExists('quality_inspection_findings');
        Schema::dropIfExists('quality_inspection_items');
        Schema::dropIfExists('quality_inspections');
        Schema::dropIfExists('delivery_verification_items');
        Schema::dropIfExists('delivery_verifications');
        Schema::dropIfExists('store_receipt_advice_items');
        Schema::dropIfExists('store_receipt_advices');
    }

    private function addCheckConstraints(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        DB::statement(
            'ALTER TABLE store_receipt_advice_items ADD CONSTRAINT sra_items_quantities_check CHECK '
            .'(expected_quantity >= 0 AND delivered_quantity >= 0)'
        );

        DB::statement(
            'ALTER TABLE quality_inspection_items ADD CONSTRAINT qi_items_quantities_check CHECK '
            .'(inspected_quantity >= 0 AND accepted_quantity >= 0 AND rejected_quantity >= 0 '
            .'AND quarantined_quantity >= 0 AND (accepted_quantity + rejected_quantity + quarantined_quantity) <= inspected_quantity)'
        );

        DB::statement(
            'ALTER TABLE goods_received_note_items ADD CONSTRAINT grn_items_quantities_check CHECK '
            .'(accepted_quantity >= 0 AND rejected_quantity >= 0)'
        );

        DB::statement(
            'ALTER TABLE supplier_return_items ADD CONSTRAINT supplier_return_items_qty_check CHECK (quantity > 0)'
        );
    }
};
