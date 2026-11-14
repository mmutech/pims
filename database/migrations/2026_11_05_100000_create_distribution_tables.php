<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 distribution documents: material requisitions, issues, transfers and
 * returns. PostgreSQL 16 compatible; all identifiers are explicit and short
 * enough for MySQL's 64-character limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('requesting_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('business_unit_id')->constrained('business_units')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->text('purpose');
            $table->date('required_by')->nullable();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_unit_id', 'status'], 'mat_req_bu_status_index');
            $table->index(['destination_warehouse_id', 'status'], 'mat_req_wh_status_index');
            $table->index(['requesting_department_id', 'status'], 'mat_req_dept_status_index');
            $table->index('project_id', 'mat_req_project_index');
        });

        Schema::create('material_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_requisition_id')->constrained('material_requisitions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('requested_quantity', 18, 4);
            $table->decimal('approved_quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('issued_quantity', 18, 4)->default(0);
            $table->decimal('outstanding_quantity', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('material_requisition_id', 'mat_req_items_req_index');
            $table->index(['item_id', 'material_requisition_id'], 'mat_req_items_item_req_index');
        });

        Schema::create('material_backorders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_requisition_item_id')->constrained('material_requisition_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('outstanding_quantity', 18, 4);
            $table->string('status', 20)->default('open')->index();
            $table->date('expected_date')->nullable();
            $table->timestamps();

            $table->index(['material_requisition_item_id', 'status'], 'mat_backorder_item_status_index');
        });

        Schema::create('stock_issues', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('material_requisition_id')->constrained('material_requisitions')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('issued_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->date('issue_date');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_issues')->nullOnDelete();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_requisition_id', 'status'], 'stock_issues_req_status_index');
            $table->index(['warehouse_id', 'status'], 'stock_issues_wh_status_index');
            $table->index('issued_to', 'stock_issues_issued_to_index');
        });

        Schema::create('stock_issue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_issue_id')->constrained('stock_issues')->cascadeOnDelete();
            $table->foreignId('material_requisition_item_id')->constrained('material_requisition_items')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->foreignId('stock_reservation_item_id')->nullable()->constrained('stock_reservation_items')->nullOnDelete();
            $table->decimal('issued_quantity', 18, 4);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->foreignId('serial_number_id')->nullable()->constrained('item_serial_numbers')->restrictOnDelete();
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('stock_transaction_item_id')->nullable()->constrained('stock_transaction_items')->nullOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('stock_issue_id', 'stock_issue_items_issue_index');
            $table->index('material_requisition_item_id', 'stock_issue_items_req_item_index');
            $table->index('stock_reservation_item_id', 'stock_issue_items_res_item_index');
        });

        Schema::create('issue_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_issue_id')->unique()->constrained('stock_issues')->cascadeOnDelete();
            $table->foreignId('acknowledged_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at');
            $table->string('signature_path', 500)->nullable();
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->string('status', 30)->default('draft')->index();
            $table->date('required_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_warehouse_id', 'status'], 'stock_transfers_src_status_idx');
            $table->index(['destination_warehouse_id', 'status'], 'stock_transfers_dst_status_idx');
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('requested_quantity', 18, 4);
            $table->decimal('approved_quantity', 18, 4)->default(0);
            $table->decimal('dispatched_quantity', 18, 4)->default(0);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('variance_quantity', 18, 4)->default(0);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('stock_transfer_id', 'stock_transfer_items_transfer_index');
        });

        Schema::create('stock_transfer_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('dispatched_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('dispatched_at');
            $table->string('vehicle_number', 50)->nullable();
            $table->string('driver_name', 150)->nullable();
            $table->string('status', 20)->default('posted')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index('stock_transfer_id', 'stock_transfer_dispatches_trf_index');
        });

        Schema::create('stock_transfer_dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained('stock_transfer_dispatches')->cascadeOnDelete();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->json('serial_numbers')->nullable();
            $table->foreignId('stock_dimension_id')->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('stock_transaction_item_id')->nullable()->constrained('stock_transaction_items')->nullOnDelete();
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->index('dispatch_id', 'stock_transfer_dispatch_items_dispatch_idx');
            $table->index('stock_transfer_item_id', 'stock_transfer_dispatch_items_item_idx');
        });

        Schema::create('stock_transfer_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('dispatch_id')->constrained('stock_transfer_dispatches')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->string('status', 20)->default('posted')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('stock_transfer_id', 'stock_transfer_receipts_trf_index');
            $table->index('dispatch_id', 'stock_transfer_receipts_dispatch_index');
        });

        Schema::create('stock_transfer_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('stock_transfer_receipts')->cascadeOnDelete();
            $table->foreignId('dispatch_item_id')->constrained('stock_transfer_dispatch_items')->restrictOnDelete();
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('damaged_quantity', 18, 4)->default(0);
            $table->decimal('missing_quantity', 18, 4)->default(0);
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->foreignId('stock_dimension_id')->nullable()->constrained('stock_dimensions')->restrictOnDelete();
            $table->foreignId('stock_transaction_item_id')->nullable()->constrained('stock_transaction_items')->nullOnDelete();
            $table->timestamps();

            $table->index('receipt_id', 'stock_transfer_receipt_items_receipt_idx');
            $table->index('dispatch_item_id', 'stock_transfer_receipt_items_dispatch_idx');
        });

        Schema::create('stock_transfer_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('dispatch_item_id')->constrained('stock_transfer_dispatch_items')->restrictOnDelete();
            $table->string('variance_type', 20)->index();
            $table->decimal('quantity', 18, 4);
            $table->string('resolution', 20)->default('pending')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stock_transfer_id', 'resolution'], 'stock_transfer_disc_trf_res_idx');
        });

        Schema::create('material_returns', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->string('return_type', 20)->index();
            $table->foreignId('original_issue_id')->nullable()->constrained('stock_issues')->restrictOnDelete();
            $table->foreignId('original_grn_id')->nullable()->constrained('goods_received_notes')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('returned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'material_returns_wh_status_index');
            $table->index('original_issue_id', 'material_returns_issue_index');
        });

        Schema::create('material_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_return_id')->constrained('material_returns')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('returned_quantity', 18, 4);
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->string('condition', 30)->default('good');
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->restrictOnDelete();
            $table->foreignId('serial_number_id')->nullable()->constrained('item_serial_numbers')->restrictOnDelete();
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->foreignId('stock_dimension_id')->nullable()->constrained('stock_dimensions')->nullOnDelete();
            $table->foreignId('stock_transaction_item_id')->nullable()->constrained('stock_transaction_items')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('material_return_id', 'material_return_items_return_index');
        });

        Schema::create('material_return_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_return_item_id')->constrained('material_return_items')->cascadeOnDelete();
            $table->foreignId('assessor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('assessed_at');
            $table->string('condition', 30);
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->string('disposition', 30);
            $table->text('findings')->nullable();
            $table->string('evidence_path', 500)->nullable();
            $table->timestamps();

            $table->index('material_return_item_id', 'material_return_assess_item_index');
        });

        $this->addCheckConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('material_return_assessments');
        Schema::dropIfExists('material_return_items');
        Schema::dropIfExists('material_returns');
        Schema::dropIfExists('stock_transfer_discrepancies');
        Schema::dropIfExists('stock_transfer_receipt_items');
        Schema::dropIfExists('stock_transfer_receipts');
        Schema::dropIfExists('stock_transfer_dispatch_items');
        Schema::dropIfExists('stock_transfer_dispatches');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('issue_acknowledgements');
        Schema::dropIfExists('stock_issue_items');
        Schema::dropIfExists('stock_issues');
        Schema::dropIfExists('material_backorders');
        Schema::dropIfExists('material_requisition_items');
        Schema::dropIfExists('material_requisitions');
    }

    private function addCheckConstraints(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        DB::statement(
            'ALTER TABLE material_requisition_items ADD CONSTRAINT mat_req_items_qty_check CHECK '
            .'(requested_quantity >= 0 AND approved_quantity >= 0 AND approved_quantity <= requested_quantity '
            .'AND reserved_quantity >= 0 AND issued_quantity >= 0 AND issued_quantity <= approved_quantity)'
        );

        DB::statement(
            'ALTER TABLE material_backorders ADD CONSTRAINT mat_backorders_qty_check CHECK (outstanding_quantity >= 0)'
        );

        DB::statement(
            'ALTER TABLE stock_issue_items ADD CONSTRAINT stock_issue_items_qty_check CHECK (issued_quantity > 0)'
        );

        DB::statement(
            'ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_warehouses_check CHECK '
            .'(source_warehouse_id <> destination_warehouse_id)'
        );

        DB::statement(
            'ALTER TABLE stock_transfer_items ADD CONSTRAINT stock_transfer_items_qty_check CHECK '
            .'(requested_quantity >= 0 AND approved_quantity >= 0 AND dispatched_quantity >= 0 '
            .'AND received_quantity >= 0 AND received_quantity <= dispatched_quantity)'
        );

        DB::statement(
            'ALTER TABLE stock_transfer_dispatch_items ADD CONSTRAINT stock_transfer_dispatch_qty_check CHECK '
            .'(quantity > 0 AND received_quantity >= 0 AND received_quantity <= quantity)'
        );

        DB::statement(
            'ALTER TABLE stock_transfer_receipt_items ADD CONSTRAINT stock_transfer_receipt_qty_check CHECK '
            .'(received_quantity >= 0 AND damaged_quantity >= 0 AND missing_quantity >= 0)'
        );

        DB::statement(
            'ALTER TABLE stock_transfer_discrepancies ADD CONSTRAINT stock_transfer_disc_qty_check CHECK (quantity > 0)'
        );

        DB::statement(
            'ALTER TABLE material_return_items ADD CONSTRAINT material_return_items_qty_check CHECK '
            .'(returned_quantity > 0 AND accepted_quantity >= 0 AND rejected_quantity >= 0 '
            .'AND accepted_quantity + rejected_quantity <= returned_quantity)'
        );

        DB::statement(
            'ALTER TABLE material_return_assessments ADD CONSTRAINT material_return_assess_qty_check CHECK '
            .'(accepted_quantity >= 0 AND rejected_quantity >= 0)'
        );
    }
};
